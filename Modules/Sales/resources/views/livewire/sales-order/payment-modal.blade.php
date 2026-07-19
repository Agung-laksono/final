<?php
use function Livewire\Volt\{state, on, with, usesFileUploads, computed, updated};
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesPayment;
use Illuminate\Support\Facades\Storage;

usesFileUploads();

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    
    'amount' => '',
    'payment_method' => 'transfer',
    'finance_account_id' => '',
    'payment_date' => '',
    'proof' => null,
    'notes' => '',
]);

$accounts = computed(function () {
    $query = \Modules\Finance\Models\FinanceAccount::where('is_active', true);
    
    $user = auth()->user();
    $isSuperAdmin = $user->hasRole('Super Admin');
    $isKepala     = $user->hasAnyRole(['Kepala Sales', 'Manager']);
    $isFinance    = $user->hasAnyRole(['Kepala Finance', 'Staf Finance']);
    
    // Super Admin & Finance: semua rekening
    if ($isSuperAdmin || $isFinance) {
        return $query->get();
    }
    
    // Kepala Sales: rekening sesuai brand dari SO yang sedang dibuka
    if ($isKepala) {
        if ($this->order && $this->order->brand_id) {
            $brand = \App\Models\Brand::with('financeAccounts')->find($this->order->brand_id);
            if ($brand && $brand->financeAccounts->isNotEmpty()) {
                $query->whereIn('id', $brand->financeAccounts->pluck('id'));
            }
            // Jika brand SO tidak punya rekening, tampilkan semua
        }
        return $query->get();
    }
    
    // Staf Sales biasa: rekening dari brand milik user
    if ($user->brand_id) {
        $brand = \App\Models\Brand::with('financeAccounts')->find($user->brand_id);
        if ($brand && $brand->financeAccounts->isNotEmpty()) {
            $query->whereIn('id', $brand->financeAccounts->pluck('id'));
        } else {
            $query->where('id', -1); // Tidak ada rekening di brand ini
        }
    } else {
        $query->where('id', -1); // User tidak punya brand
    }
    
    return $query->get();
});

on(['open-payment-modal' => function ($orderId) {
    $order = SalesOrder::find($orderId);
    if (!$order) return;
    
    $isOwn = $order->created_by === auth()->id();
    $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager', 'Kepala Gudang', 'Staf Gudang', 'Kepala Finance', 'Staf Finance']);
    if (!$isOwn && !$isManagerial) {
        \Flux::toast('Anda tidak memiliki akses untuk transaksi pembayaran pesanan ini.', 'danger');
        return;
    }

    $this->orderId = $orderId;
    $this->order = SalesOrder::with('payments', 'brand')->find($orderId);
    $this->amount = '';
    $this->proof = null;
    $this->notes = '';
    $this->payment_date = now()->format('Y-m-d');
    
    if ($this->accounts->count() == 1) {
        $this->finance_account_id = $this->accounts->first()->id;
    } else {
        $this->finance_account_id = '';
    }

    $this->show = true;
    },
    'echo:kanban.sales_order,KanbanUpdated' => function () {
        if ($this->orderId) {
            $this->order = SalesOrder::with('payments')->find($this->orderId);
        }
    }
]);

updated(['amount' => function ($value) {
    if (!$this->order || empty($value)) return;

    $terbayar = $this->order->payments()->whereIn('status', ['verified', 'pending'])->sum('amount');
    $sisa = $this->order->total_amount - $terbayar;
    
    $this->validate(
        ['amount' => 'numeric|max:' . max(0, $sisa)],
        ['amount.max' => 'Nominal melebihi sisa tagihan (Maks: Rp ' . number_format(max(0, $sisa), 0, ',', '.') . ').']
    );
}]);

$savePayment = function () {
    abort_unless(auth()->user()->can('sales.payment.create'), 403);
    if (!$this->order) return;

    $terbayar = $this->order->payments()->whereIn('status', ['verified', 'pending'])->sum('amount');
    $sisa = $this->order->total_amount - $terbayar;
    
    $rules = [
        'amount' => 'required|numeric|min:1|max:' . max(0, $sisa),
        'payment_method' => 'required|string',
        'finance_account_id' => 'required|exists:finance_accounts,id',
        'payment_date' => 'required|date',
        'proof' => 'required',
    ];
    
    if ($this->proof && !is_string($this->proof)) {
        $rules['proof'] = 'image|max:2048';
    }
    
    $this->validate($rules, [
        'amount.max' => 'Nominal melebihi sisa tagihan (Maks: Rp ' . number_format(max(0, $sisa), 0, ',', '.') . ').',
    ]);

    if (!$this->order) return;

    $proofPath = null;
    if ($this->proof) {
        if (is_string($this->proof) && str_starts_with($this->proof, 'data:image')) {
            list($type, $data) = explode(';', $this->proof);
            list(, $data)      = explode(',', $data);
            $data = base64_decode($data);
            
            $filename = 'sales_payments/' . uniqid() . '.webp';
            Storage::disk('public')->put($filename, $data);
            $proofPath = $filename;
        } else {
            $proofPath = $this->proof->store('sales_payments', 'public');
        }
    }

    SalesPayment::create([
        'sales_order_id' => $this->order->id,
        'amount' => $this->amount,
        'payment_method' => $this->payment_method,
        'finance_account_id' => $this->finance_account_id,
        'payment_date' => $this->payment_date,
        'proof_path' => $proofPath,
        'notes' => $this->notes,
        'created_by' => auth()->id(),
        'status' => 'pending', // Menunggu validasi Finance
    ]);

    \App\Events\PaymentSubmitted::safeDispatch('Pembayaran SO ' . $this->order->so_number . ' menunggu validasi');

    $financeUsers = \App\Models\User::withPermissionOrSuperAdmin(['sales.payment.validate'])->get();
    \Illuminate\Support\Facades\Notification::send($financeUsers, new \App\Notifications\PaymentSubmittedNotification($this->order->so_number, $this->amount, auth()->user(), 'sales', $this->payment_method, $this->order->customer->name ?? '-'));
    \Flux::toast('Bukti pembayaran berhasil diunggah. Menunggu validasi Finance.', variant: 'success');
    
    $this->order->load('payments'); // Reload
    $this->amount = '';
    $this->proof = null;
    $this->notes = '';
    $this->payment_date = now()->format('Y-m-d');
    $this->payment_method = 'transfer';
    
    $this->dispatch('status-updated');
    $this->dispatch('reset-cropper');
    $this->dispatch('payment-saved');
};

$verifyPayment = function ($paymentId) {
    abort_unless(auth()->user()->can('sales.payment.validate'), 403);
    
    $payment = SalesPayment::find($paymentId);
    if ($payment && $payment->status === 'pending') {
        $payment->status = 'verified';
        $payment->verified_by = auth()->id();
        $payment->verified_at = now();
        $payment->save();

        // Update status SO berdasarkan pembayaran yang sudah diverifikasi
        $totalVerified = $this->order->payments()->where('status', 'verified')->sum('amount');
        
        if ($totalVerified >= $this->order->total_amount) {
            $this->order->payment_status = 'paid';
        } elseif ($totalVerified > 0) {
            $this->order->payment_status = 'partial';
        }
        $this->order->save();

        \Flux::toast('Pembayaran diverifikasi!', variant: 'success');
        $this->order->load('payments');
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
    }
};

$rejectPayment = function ($paymentId) {
    abort_unless(auth()->user()->can('sales.payment.validate'), 403);
    
    $payment = SalesPayment::find($paymentId);
    if ($payment && $payment->status === 'pending') {
        $payment->status = 'rejected';
        $payment->verified_by = auth()->id();
        $payment->verified_at = now();
        // rejection_reason bisa ditambahkan nanti jika perlu popup modal
        $payment->save();

        \Flux::toast('Pembayaran ditolak.', variant: 'danger');
        $this->order->load('payments');
    }
};

?>

<div>
<flux:modal wire:model="show" class="w-full md:w-[32rem] md:max-w-xl">
    @if($order)
    @php
        $terbayar = $order->payments()->whereIn('status', ['verified', 'pending'])->sum('amount');
        $sisa = $order->total_amount - $terbayar;
    @endphp
    <div class="p-4 sm:p-6" x-data="{ showPreviewModal: false, previewImage: '', tab: '{{ $order && $order->payments()->count() === 0 && $sisa > 0 ? "form" : "history" }}' }" @payment-saved.window="tab = 'history'">
        <div class="flex items-start gap-3 sm:gap-4">
            <div class="flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center">
                <flux:icon.banknotes class="w-4 h-4 sm:w-5 sm:h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg" class="text-base sm:text-lg leading-tight sm:leading-normal">Pembayaran SO <strong>{{ $order->so_number }}</strong></flux:heading>
                <div class="mt-1.5 sm:mt-2 flex flex-wrap items-center gap-3 sm:gap-4">
                    <flux:subheading class="!mt-0 text-xs sm:text-sm">
                        Total Tagihan: <strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong>
                    </flux:subheading>
                    
                    @if($order->customer)
                    <div class="flex items-center gap-2 sm:gap-2.5">
                        @if(isset($order->customer->image) && $order->customer->image)
                            <button type="button" @click="$dispatch('open-lightbox', { url: '{{ Storage::url($order->customer->image) }}' })" class="shrink-0 hover:opacity-80 transition-opacity focus:outline-none" title="Lihat Foto Profil">
                                <img src="{{ Storage::url($order->customer->image) }}" alt="{{ $order->customer->name }}" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover border border-zinc-200 dark:border-zinc-700 shadow-sm" />
                            </button>
                        @else
                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-[10px] sm:text-xs font-bold text-emerald-700 dark:text-emerald-400 shrink-0 uppercase shadow-sm border border-emerald-200 dark:border-emerald-800/50">
                                {{ substr($order->customer->name, 0, 2) }}
                            </div>
                        @endif
                        <div class="flex flex-col leading-tight">
                            <span class="text-xs sm:text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $order->customer->name }}</span>
                            @if($order->customer->phone)
                                <span class="text-[10px] sm:text-xs text-zinc-500">{{ $order->customer->phone }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-4 sm:mt-6" x-cloak>
            <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-px mb-4 sm:mb-6 overflow-x-auto no-scrollbar">
                <button type="button" @click="tab = 'history'" :class="tab === 'history' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="whitespace-nowrap px-2 sm:px-4 py-2 border-b-2 font-bold text-xs sm:text-sm transition-colors flex items-center gap-1.5 sm:gap-2">
                    <flux:icon.clock class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    <span class="sm:hidden">Riwayat</span>
                    <span class="hidden sm:inline">Riwayat Pembayaran</span>
                </button>
                @if($sisa > 0)
                <button type="button" @click="tab = 'form'" :class="tab === 'form' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="whitespace-nowrap px-2 sm:px-4 py-2 border-b-2 font-bold text-xs sm:text-sm transition-colors flex items-center gap-1.5 sm:gap-2">
                    <flux:icon.plus-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    <span class="sm:hidden">Catat</span>
                    <span class="hidden sm:inline">Catat Pembayaran</span>
                </button>
                @endif
            </div>

            @if($sisa > 0)
                {{-- TAB: FORM INPUT --}}
                <div x-show="tab === 'form'" style="display: none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-4">
                    @can('sales.payment.create')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <div class="flex justify-between items-end mb-1 sm:mb-2">
                                <flux:label class="!mb-0 text-sm">Nominal Bayar <span class="text-red-500">*</span></flux:label>
                                <span class="text-[10px] font-bold text-rose-500">-{{ number_format($sisa, 0, ',', '.') }}</span>
                            </div>
                            <x-rupiah-input wire:model.live.debounce.300ms="amount" placeholder="Contoh: 5.000.000" required />
                            <flux:error name="amount" />
                        </div>
                        <div>
                            <flux:select wire:model="finance_account_id" label="Rekening Tujuan (Kas)" placeholder="Pilih rekening tujuan..." required>
                                @foreach($this->accounts as $acc)
                                    <option value="{{ $acc->id }}">
                                        {{ $acc->name }} ({{ $acc->account_number ?: $acc->type }})
                                    </option>
                                @endforeach
                            </flux:select>
                            <flux:error name="finance_account_id" />
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <div>
                                <flux:input type="date" wire:model="payment_date" label="Tanggal" required />
                                <flux:error name="payment_date" />
                            </div>
                            
                            <div>
                                <flux:textarea wire:model="notes" label="Catatan Jurnal / Referensi" placeholder="Keterangan tambahan..." />
                                <flux:error name="notes" />
                            </div>
                        </div>
                        
                        <div class="flex flex-col">
                            <flux:label class="mb-1 sm:mb-2 text-sm">Bukti Transfer / Kasbon <span class="text-red-500">*</span></flux:label>
                            <div class="flex-1 min-h-[200px]">
                                <x-image-cropper id="payment-cropper" wire:model="proof" :image="$proof && is_string($proof) && !str_starts_with($proof, 'data:image') ? $proof : null" accept="image/*" />
                            </div>
                            <flux:error name="proof" />
                        </div>
                    </div>
                    
                    <div class="pt-2 sm:pt-4">
                        <flux:button variant="primary" wire:click="savePayment" wire:target="savePayment" wire:loading.attr="disabled" icon="arrow-up-tray" class="w-full">Proses & Catat Jurnal</flux:button>
                    </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-full text-center p-6 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <flux:icon.lock-closed class="w-8 h-8 text-zinc-400 mb-2" />
                            <span class="text-sm text-zinc-500">Anda berada di mode Validasi (Finance).<br>Gunakan panel riwayat di sebelah kanan untuk memverifikasi pembayaran dari Sales.</span>
                        </div>
                    @endcan
                </div>
            @endif

            {{-- TAB: RIWAYAT PEMBAYARAN --}}
            <div x-show="tab === 'history'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                    @forelse($order->payments()->latest()->get() as $payment)
                        <div class="bg-white dark:bg-zinc-900 p-3 rounded-lg border {{ $payment->status === 'pending' ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/10' : 'border-zinc-100 dark:border-zinc-800' }} text-sm flex gap-3 relative overflow-hidden">
                            @if($payment->status === 'pending')
                                <div class="absolute top-0 right-0 bg-amber-500 text-white text-[9px] px-2 py-0.5 rounded-bl-lg font-bold tracking-wide">PENDING VERIFIKASI</div>
                            @elseif($payment->status === 'rejected')
                                <div class="absolute top-0 right-0 bg-red-500 text-white text-[9px] px-2 py-0.5 rounded-bl-lg font-bold tracking-wide">DITOLAK</div>
                            @endif

                            <div class="flex-1 mt-3">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                                    <span class="text-[10px] text-zinc-400">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</span>
                                </div>
                                <div class="flex gap-2 text-xs text-zinc-500">
                                    <span class="uppercase font-medium text-emerald-600 dark:text-emerald-400">{{ $payment->payment_method }}</span>
                                    <span>•</span>
                                    <span class="truncate">{{ $payment->notes ?: 'Tanpa catatan' }}</span>
                                </div>
                                
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
                                <button type="button" @click="$dispatch('open-lightbox', { url: '{{ Storage::url($payment->proof_path) }}' })" class="shrink-0 w-16 h-16 mt-2 bg-zinc-100 dark:bg-zinc-800 rounded-md overflow-hidden hover:opacity-80 transition-opacity border border-zinc-200 dark:border-zinc-700 focus:outline-none" title="Lihat Bukti">
                                    <img src="{{ Storage::url($payment->proof_path) }}" class="w-full h-full object-cover" />
                                </button>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-zinc-500 text-xs py-4 italic">Belum ada pembayaran</div>
                    @endforelse
                </div>
                
                @php
                    $terbayarHistory = $order->payments()->where('status', 'verified')->sum('amount');
                    $sisaHistory = $order->total_amount - $terbayarHistory;
                @endphp
                <div class="mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-700 text-sm">
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                        <span>Telah Dibayar (Tervalidasi):</span>
                        <span class="font-medium text-emerald-600 dark:text-emerald-400">Rp {{ number_format($terbayarHistory, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between mt-1 font-bold">
                        <span class="text-zinc-800 dark:text-zinc-200">Sisa Tagihan (Belum Tervalidasi):</span>
                        <span class="{{ $sisaHistory <= 0 ? 'text-zinc-400' : 'text-red-600 dark:text-red-400' }}">Rp {{ number_format(max(0, $sisaHistory), 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</flux:modal>

</div>
