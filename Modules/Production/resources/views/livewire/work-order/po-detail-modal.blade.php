<?php
use function Livewire\Volt\{state, on, computed, updated};
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchasePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

state([
    'show' => false,
    'po' => null,
    
    // Payment Form
    'payment_amount' => '',
    'payment_date' => '',
    'payment_method' => 'transfer',
    'finance_account_id' => '',
    'proof' => null,
    'payment_notes' => '',
]);

$accounts = computed(function () {
    return \Modules\Finance\Models\FinanceAccount::where('is_active', true)->get();
});

$totalPaid = computed(function () {
    if (!$this->po) return 0;
    return $this->po->payments()->whereIn('status', ['verified', 'pending'])->sum('amount');
});

$remainingBalance = computed(function () {
    if (!$this->po) return 0;
    return max(0, $this->po->total_amount - $this->totalPaid);
});

$paymentProgress = computed(function () {
    if (!$this->po || $this->po->total_amount == 0) return 0;
    return min(100, ($this->totalPaid / $this->po->total_amount) * 100);
});

on(['open-po-detail-modal' => function ($poId) {
    $this->loadData($poId);
    $this->show = true;
    },
    'echo:kanban.purchase_order,KanbanUpdated' => function () {
        if ($this->po) {
            $this->po = PurchaseOrder::with(['vendor', 'items.item', 'payments.user'])->find($this->po->id);
        }
    }
]);

updated(['payment_amount' => function ($value) {
    if (!$this->po || empty($value)) return;

    $this->validate(
        ['payment_amount' => 'numeric|max:' . $this->remainingBalance],
        ['payment_amount.max' => 'Nominal melebihi sisa tagihan (Maks: Rp ' . number_format($this->remainingBalance, 0, ',', '.') . ').']
    );
}]);

$loadData = function ($poId) {
    $this->po = PurchaseOrder::with(['vendor', 'items.item', 'payments.user'])->find($poId);
    $this->payment_amount = $this->remainingBalance;
};

$addPayment = function () {
    abort_unless(auth()->user()->can('purchase.order.update'), 403);
    
    $rules = [
        'payment_amount' => 'required|numeric|min:1|max:' . $this->remainingBalance,
        'payment_date' => 'required|date',
        'payment_method' => 'required|string',
        'finance_account_id' => 'required|exists:finance_accounts,id',
        'proof' => 'required',
    ];

    if ($this->proof && !is_string($this->proof)) {
        $rules['proof'] = 'image|max:2048';
    }

    $this->validate($rules, [
        'payment_amount.max' => 'Nominal melebihi sisa tagihan (Maks: Rp ' . number_format($this->remainingBalance, 0, ',', '.') . ').',
    ]);

    if ($this->po && $this->remainingBalance > 0) {
        $proofPath = null;
        if ($this->proof) {
            if (is_string($this->proof) && str_starts_with($this->proof, 'data:image')) {
                list($type, $data) = explode(';', $this->proof);
                list(, $data)      = explode(',', $data);
                $data = base64_decode($data);
                
                $filename = 'purchase_payments/' . uniqid() . '.webp';
                Storage::disk('public')->put($filename, $data);
                $proofPath = $filename;
            } else {
                $proofPath = $this->proof->store('purchase_payments', 'public');
            }
        }

        DB::transaction(function () use ($proofPath) {
            $payment = $this->po->payments()->create([
                'amount' => $this->payment_amount,
                'payment_date' => $this->payment_date,
                'payment_method' => $this->payment_method,
                'finance_account_id' => $this->finance_account_id,
                'proof_path' => $proofPath,
                'notes' => $this->payment_notes,
                'created_by' => auth()->id(),
                'status' => 'pending' // Menunggu validasi Finance
            ]);
        });
        
        \App\Events\PaymentSubmitted::safeDispatch('Pembayaran PO ' . $this->po->po_number . ' menunggu validasi');
        
        $financeUsers = \App\Models\User::withPermissionOrSuperAdmin(['finance.notifikasi.view', 'sales.payment.validate'])->get();
        \Illuminate\Support\Facades\Notification::send($financeUsers, new \App\Notifications\PaymentSubmittedNotification($this->po->po_number, $this->payment_amount, auth()->user(), 'purchase', $this->payment_method, $this->po->vendor->name ?? '-'));
        $this->loadData($this->po->id);
        
        // Auto-mark as LUNAS in notes or status if fully paid? We can just check remainingBalance
        if ($this->remainingBalance <= 0) {
            \Flux::toast('Tagihan telah LUNAS!', variant: 'success');
        } else {
            \Flux::toast('Pembayaran berhasil dicatat.', variant: 'success');
        }
        
        $this->reset(['payment_notes']);
        $this->payment_date = date('Y-m-d');
        
        $this->dispatch('status-updated');
    }
};

$cancelSPK = function () {
    abort_unless(auth()->user()->can('purchase.order.update'), 403);
    
    if ($this->po && $this->po->status !== 'completed') {
        DB::transaction(function () {
            // Revert production orders status back to waiting_vendor
            \Modules\Production\Models\ProductionOrder::where('purchase_order_id', $this->po->id)
                ->update([
                    'status' => 'waiting_vendor',
                    'purchase_order_id' => null,
                    'vendor_cost' => 0,
                    'phase_type' => null
                ]);
            
            // Delete payments
            $this->po->payments()->delete();
            // Delete PO items
            $this->po->items()->delete();
            // Delete PO
            $this->po->delete();
        });
        
        \Flux::toast('SPK berhasil dibatalkan dan dikembalikan ke Antrean.', variant: 'warning');
        $this->show = false;
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
    }
};

?>

<div>
<flux:modal wire:model="show" class="md:w-[800px] max-w-full">
    @if($this->po)
        <div class="space-y-6" x-data="{ showPreviewModal: false, previewImage: '' }">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg" class="flex items-center gap-2">
                        Detail SPK Maklon: {{ $this->po->po_number }}
                        @if($this->remainingBalance <= 0 && $this->po->total_amount > 0)
                            <span class="bg-emerald-100 text-emerald-700 text-xs px-2 py-0.5 rounded-full font-bold">LUNAS</span>
                        @endif
                    </flux:heading>
                    <flux:subheading>Vendor: <strong>{{ $this->po->vendor->name }}</strong></flux:subheading>
                </div>
                <div class="flex items-center gap-2 pr-8 z-10 relative">
                    <flux:button size="sm" variant="subtle" icon="printer" wire:click="$dispatch('open-po-print-modal', { poId: {{ $this->po->id }} })">Print SPK</flux:button>
                    @if($this->po->status !== 'completed' && $this->po->payments->isEmpty())
                        <flux:dropdown>
                            <flux:button size="sm" icon="ellipsis-vertical" variant="ghost" class="px-2" />
                            <flux:menu>
                                <flux:menu.item wire:click="cancelSPK" wire:confirm="Yakin ingin membatalkan SPK ini? Seluruh pesanan terkait akan dikembalikan ke Antrean Proses." icon="trash" class="text-red-500 hover:text-red-600">Batalkan SPK</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            </div>

            <div x-data="{ tab: 'dashboard' }" class="space-y-6">
                
                <!-- Tab Navigation -->
                <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-px overflow-x-auto no-scrollbar">
                    <button @click="tab = 'dashboard'" :class="tab === 'dashboard' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="whitespace-nowrap px-4 py-2 border-b-2 font-bold text-sm transition-colors flex items-center gap-2">
                        <flux:icon.home class="w-4 h-4" />
                        Ringkasan
                    </button>
                    <button @click="tab = 'pekerjaan'" :class="tab === 'pekerjaan' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="whitespace-nowrap px-4 py-2 border-b-2 font-bold text-sm transition-colors flex items-center gap-2">
                        <flux:icon.clipboard-document-list class="w-4 h-4" />
                        Rincian Pekerjaan & Panduan
                    </button>
                    <button @click="tab = 'pembayaran'" :class="tab === 'pembayaran' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="whitespace-nowrap px-4 py-2 border-b-2 font-bold text-sm transition-colors flex items-center gap-2">
                        <flux:icon.currency-dollar class="w-4 h-4" />
                        Pembayaran & Keuangan
                    </button>
                </div>

                <!-- TAB 1: DASHBOARD -->
                <div x-show="tab === 'dashboard'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-6">
                    <!-- Info Tambahan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-center gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center shrink-0">
                                <flux:icon.calendar class="w-5 h-5" />
                            </div>
                            <div>
                                <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">Tenggat Waktu</div>
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $this->po->expected_delivery_date ? \Carbon\Carbon::parse($this->po->expected_delivery_date)->format('d M Y') : 'Tidak ditentukan' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                                <flux:icon.phone class="w-5 h-5" />
                            </div>
                            <div>
                                <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">Kontak Vendor</div>
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $this->po->vendor->phone ?? 'Tidak ada kontak' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress & Summary -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-800/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <span class="text-zinc-500 text-sm">Total Tagihan</span><br>
                            <strong class="text-xl text-zinc-900 dark:text-zinc-100">Rp {{ number_format($this->po->total_amount, 0, ',', '.') }}</strong>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-800/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <span class="text-zinc-500 text-sm">Sudah Dibayar</span><br>
                            <strong class="text-xl text-emerald-600">Rp {{ number_format($this->totalPaid, 0, ',', '.') }}</strong>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-800/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <span class="text-zinc-500 text-sm">Sisa Utang</span><br>
                            <strong class="text-xl text-red-600">Rp {{ number_format($this->remainingBalance, 0, ',', '.') }}</strong>
                        </div>
                        
                        <div class="col-span-1 md:col-span-3">
                            <div class="flex justify-between text-xs text-zinc-500 mb-1">
                                <span>Progress Pembayaran</span>
                                <span>{{ number_format($this->paymentProgress, 1) }}%</span>
                            </div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2.5">
                                <div class="bg-emerald-500 h-2.5 rounded-full transition-all duration-500" style="width: {{ $this->paymentProgress }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="text-sm bg-blue-50 dark:bg-blue-900/20 p-5 rounded-xl border border-blue-200 dark:border-blue-800/50">
                        <div class="flex items-center gap-2 mb-3">
                            <flux:icon.information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            <strong class="text-blue-800 dark:text-blue-300 text-base">Catatan / Instruksi SPK Global</strong>
                        </div>
                        <div class="prose prose-sm max-w-none prose-p:my-0 prose-img:rounded-lg text-blue-800 dark:text-blue-300">
                            {!! $this->po->notes ?: 'Tidak ada catatan global.' !!}
                        </div>
                    </div>
                </div>

                <!-- TAB 2: PEKERJAAN -->
                <div x-show="tab === 'pekerjaan'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="space-y-6">
                    <div class="space-y-4">
                        @foreach($this->po->items as $item)
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden bg-white dark:bg-zinc-900/30 shadow-sm">
                                <div class="bg-zinc-50 dark:bg-zinc-800/80 p-4 border-b border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                                    <div>
                                        <div class="font-bold text-lg text-zinc-900 dark:text-zinc-100">
                                            @if($item->item->alias)
                                                {{ $item->item->alias }} <span class="text-sm text-zinc-500 normal-case font-medium ml-1">- {{ $item->item->name }}</span>
                                            @else
                                                {{ $item->item->name }}
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 px-2 py-0.5 rounded font-mono">ID: {{ $item->id }}</span>
                                            <span class="text-sm text-zinc-500">Qty: <strong class="text-zinc-900 dark:text-zinc-200">{{ $item->quantity }}</strong> &nbsp;•&nbsp; Rp {{ number_format($item->unit_price, 0, ',', '.') }}/pcs</span>
                                        </div>
                                    </div>
                                    <div class="text-left sm:text-right">
                                        <div class="text-xs text-zinc-500 uppercase tracking-wider mb-1">Subtotal Jasa</div>
                                        <div class="font-bold text-xl text-emerald-600 dark:text-emerald-400">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                                
                                <!-- Status Pengerjaan Vendor -->
                                <div class="bg-white dark:bg-zinc-900/50 p-3 px-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                                    <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider flex items-center gap-1"><flux:icon.check-badge class="w-3 h-3" /> Status Pekerjaan Vendor</div>
                                    @if($item->received_quantity >= $item->quantity)
                                        <span class="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 font-bold px-2 py-0.5 rounded text-xs flex items-center gap-1">
                                            <flux:icon.check class="w-3 h-3" /> Selesai
                                        </span>
                                    @elseif($item->received_quantity > 0)
                                        <span class="bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 font-bold px-2 py-0.5 rounded text-xs flex items-center gap-1">
                                            <flux:icon.clock class="w-3 h-3" /> Sebagian Selesai ({{ $item->received_quantity }}/{{ $item->quantity }})
                                        </span>
                                    @else
                                        <span class="bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-bold px-2 py-0.5 rounded text-xs flex items-center gap-1">
                                            <flux:icon.arrow-path class="w-3 h-3 animate-spin-slow" /> Sedang Dikerjakan
                                        </span>
                                    @endif
                                </div>
                                @if($item->notes)
                                    <div class="p-5 bg-white dark:bg-zinc-900/30">
                                        <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                                            <flux:icon.document-text class="w-4 h-4" />
                                            Panduan Pengerjaan
                                        </div>
                                        <!-- Menggunakan prose untuk merender HTML dari TinyMCE dengan sempurna dan lebar (full width) -->
                                        <div class="prose max-w-none prose-zinc dark:prose-invert prose-p:my-1 prose-headings:my-3 prose-img:rounded-xl">
                                            {!! $item->notes !!}
                                        </div>
                                    </div>
                                @else
                                    <div class="p-5 bg-white dark:bg-zinc-900/30 text-zinc-400 text-sm italic flex items-center justify-center">
                                        Tidak ada catatan panduan khusus untuk barang ini.
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- TAB 3: PEMBAYARAN -->
                <div x-show="tab === 'pembayaran'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Riwayat -->
                        <div>
                            <flux:heading size="md" class="mb-4">Riwayat Pembayaran</flux:heading>
                            @if($this->po->payments->isEmpty())
                                <div class="text-sm text-zinc-500 italic p-6 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl text-center border border-dashed border-zinc-300 dark:border-zinc-700">Belum ada pembayaran sama sekali.</div>
                            @else
                                <div class="space-y-3">
                                    @foreach($this->po->payments as $payment)
                                        <div class="flex items-start gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:border-emerald-300 transition-colors">
                                            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                                <flux:icon.currency-dollar class="w-5 h-5" />
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex justify-between items-start mb-1">
                                                    <strong class="text-base text-zinc-900 dark:text-zinc-100">Rp {{ number_format($payment->amount, 0, ',', '.') }}</strong>
                                                    <span class="text-xs font-medium text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M y') }}</span>
                                                </div>
                                                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $payment->notes ?: 'Transfer/Kas' }}</div>
                                                <div class="text-xs text-zinc-400 mt-2">Diterima oleh: {{ $payment->user?->name ?? 'Sistem' }}</div>
                                                @if($payment->status === 'pending')
                                                    <div class="mt-2 text-[10px] italic text-amber-600 dark:text-amber-500 border-t border-amber-200 dark:border-amber-800 pt-2">Menunggu pengecekan Finance...</div>
                                                @elseif($payment->status === 'rejected' && $payment->rejection_reason)
                                                    <div class="mt-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded border border-red-100 dark:border-red-900/50">
                                                        <strong class="block mb-0.5">Alasan Penolakan:</strong>
                                                        {{ $payment->rejection_reason }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if($payment->proof_path)
                                                <button type="button" @click="$dispatch('preview-image', '{{ Storage::url($payment->proof_path) }}')" class="shrink-0 w-16 h-16 bg-zinc-100 dark:bg-zinc-800 rounded-md overflow-hidden hover:opacity-80 transition-opacity border border-zinc-200 dark:border-zinc-700 focus:outline-none" title="Lihat Bukti">
                                                    <img src="{{ Storage::url($payment->proof_path) }}" class="w-full h-full object-cover" />
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Input Pembayaran Baru -->
                        <div>
                            @if($this->remainingBalance > 0)
                                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-5 shadow-sm relative overflow-hidden">
                                    <!-- Aksen atas -->
                                    <div class="absolute top-0 left-0 w-full h-1 bg-emerald-500"></div>
                                    
                                    <div class="flex items-center justify-between mb-5">
                                        <flux:heading size="md">Catat Pembayaran Baru</flux:heading>
                                        <button type="button" wire:click="$set('payment_amount', {{ $this->remainingBalance }})" class="text-xs font-bold bg-emerald-100 text-emerald-700 hover:bg-emerald-200 px-2 py-1 rounded transition-colors">
                                            LUNAS: Rp {{ number_format($this->remainingBalance, 0, ',', '.') }}
                                        </button>
                                    </div>
                                    <form wire:submit.prevent="addPayment" class="space-y-4">
                                        <div>
                                            <flux:label class="mb-2">Nominal Bayar <span class="text-red-500">*</span></flux:label>
                                            <x-rupiah-input wire:model.live.debounce.300ms="payment_amount" placeholder="Contoh: 5.000.000" required />
                                            <flux:error name="payment_amount" />
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <flux:input type="date" wire:model="payment_date" label="Tanggal" required />
                                            <flux:select wire:model="finance_account_id" label="Sumber Dana (Kas)" placeholder="Pilih rekening sumber dana..." required>
                                                @foreach($this->accounts as $acc)
                                                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->type }})</option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        <div>
                                            <flux:error name="finance_account_id" />
                                        </div>
                                        <div>
                                            <flux:label class="mb-2">Bukti Pembayaran <span class="text-red-500">*</span></flux:label>
                                            <x-image-cropper id="purchase-cropper" wire:model="proof" :image="$proof && is_string($proof) && !str_starts_with($proof, 'data:image') ? Storage::url($proof) : null" accept="image/*" />
                                        </div>
                                        <flux:textarea wire:model="payment_notes" label="Catatan Tambahan (Opsional)" placeholder="Keterangan..." />
                                        
                                        <flux:button type="submit" variant="primary" icon="check" class="w-full">Proses Pembayaran</flux:button>
                                    </form>
                                </div>
                            @else
                                <div class="bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300 p-8 rounded-xl border border-emerald-200 dark:border-emerald-800/50 flex flex-col items-center justify-center text-center gap-3">
                                    <flux:icon.check-badge class="w-16 h-16 text-emerald-500" />
                                    <div>
                                        <strong class="block text-xl">Tagihan Sudah Lunas!</strong>
                                        <span class="text-sm opacity-80 mt-1 block">Tidak ada sisa utang yang harus dibayar ke vendor ini.</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="$set('show', false)"> Tutup </flux:button>
            </div>
        </div>
    @endif
</flux:modal>

<flux:modal name="preview-modal" class="w-full max-w-4xl p-0 bg-transparent shadow-none border-none">
    <div x-data="{ previewImage: '' }" @preview-image.window="previewImage = $event.detail; $flux.modal('preview-modal').show()" class="relative flex flex-col items-center justify-center p-4">
        <button type="button" @click="$flux.modal('preview-modal').close()" class="absolute top-0 right-0 text-white hover:text-zinc-300 bg-black/50 rounded-full p-2 focus:outline-none transition-colors hover:bg-black/70 z-10">
            <flux:icon.x-mark class="w-6 h-6" />
        </button>
        <img :src="previewImage" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl">
    </div>
</flux:modal>
</div>
