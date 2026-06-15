<?php
use function Livewire\Volt\{state, on, with, usesFileUploads};
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
    'payment_date' => '',
    'proof' => null,
    'notes' => '',
]);

on(['open-payment-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::with('payments')->find($orderId);
    if ($this->order) {
        $this->amount = '';
        $this->proof = null;
        $this->notes = '';
        $this->payment_date = now()->format('Y-m-d');
        $this->show = true;
    }
}]);

$savePayment = function () {
    abort_unless(auth()->user()->can('sales.payment.create'), 403);
    
    $this->validate([
        'amount' => 'required|numeric|min:1',
        'payment_method' => 'required|string',
        'payment_date' => 'required|date',
        'proof' => 'nullable|image|max:2048',
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
        'payment_date' => $this->payment_date,
        'proof_path' => $proofPath,
        'notes' => $this->notes,
        'created_by' => auth()->id(),
        'status' => 'pending', // Menunggu validasi Finance
    ]);

    \Flux::toast('Bukti pembayaran berhasil diunggah. Menunggu validasi Finance.', variant: 'success');
    
    $this->order->load('payments'); // Reload
    $this->amount = '';
    $this->proof = null;
    $this->notes = '';
    $this->dispatch('status-updated');
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

<flux:modal wire:model="show" class="w-full md:w-[40rem] md:max-w-2xl">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center">
                <flux:icon.banknotes class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Pembayaran SO <strong>{{ $order->so_number }}</strong></flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    Total Tagihan: <strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong>
                </flux:subheading>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Bagian Kiri: Form Input --}}
            <div class="space-y-4">
                @can('sales.payment.create')
                    <div>
                        <flux:label class="mb-2">Nominal Bayar <span class="text-red-500">*</span></flux:label>
                        <x-rupiah-input wire:model="amount" placeholder="Contoh: 5000000" required />
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <flux:input type="date" wire:model="payment_date" label="Tanggal" required />
                        <flux:select wire:model="payment_method" label="Metode">
                            <option value="transfer">Transfer Bank</option>
                            <option value="cash">Tunai (Cash)</option>
                            <option value="credit_card">Kartu Kredit</option>
                            <option value="qris">QRIS</option>
                        </flux:select>
                    </div>
                    
                    <div>
                        <flux:label class="mb-2">Bukti Transfer (Opsional)</flux:label>
                        <x-image-cropper id="payment-cropper" wire:model="proof" :image="$proof && is_string($proof) && !str_starts_with($proof, 'data:image') ? Storage::url($proof) : null" accept="image/*" />
                    </div>
                    
                    <flux:textarea wire:model="notes" label="Catatan" placeholder="Keterangan tambahan..." />
                    
                    <flux:button variant="primary" wire:click="savePayment" icon="arrow-up-tray" class="w-full">Unggah Bukti Bayar</flux:button>
                @else
                    <div class="flex flex-col items-center justify-center h-full text-center p-6 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl">
                        <flux:icon.lock-closed class="w-8 h-8 text-zinc-400 mb-2" />
                        <span class="text-sm text-zinc-500">Anda berada di mode Validasi (Finance).<br>Gunakan panel riwayat di sebelah kanan untuk memverifikasi pembayaran dari Sales.</span>
                    </div>
                @endcan
            </div>

            {{-- Bagian Kanan: Riwayat Pembayaran --}}
            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200 mb-3">Riwayat Pembayaran</h3>
                
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
                                    @can('sales.payment.validate')
                                        <div class="flex gap-2 mt-3 pt-3 border-t border-amber-200 dark:border-amber-800">
                                            <flux:button size="sm" variant="primary" wire:click="verifyPayment({{ $payment->id }})" class="w-full bg-emerald-600 hover:bg-emerald-700 border-none text-white text-xs">ACC / Valid</flux:button>
                                            <flux:button size="sm" variant="danger" wire:click="rejectPayment({{ $payment->id }})" class="w-full text-xs">Tolak</flux:button>
                                        </div>
                                    @else
                                        <div class="mt-2 text-[10px] italic text-amber-600 dark:text-amber-500">Menunggu pengecekan Finance...</div>
                                    @endcan
                                @endif
                            </div>
                            @if($payment->proof_path)
                                <a href="{{ Storage::url($payment->proof_path) }}" target="_blank" class="shrink-0 w-16 h-16 mt-2 bg-zinc-100 dark:bg-zinc-800 rounded-md overflow-hidden hover:opacity-80 transition-opacity border border-zinc-200 dark:border-zinc-700" title="Lihat Bukti">
                                    <img src="{{ Storage::url($payment->proof_path) }}" class="w-full h-full object-cover" />
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-zinc-500 text-xs py-4 italic">Belum ada pembayaran</div>
                    @endforelse
                </div>
                
                @php
                    // Hitung hanya yang sudah diverifikasi
                    $terbayar = $order->payments()->where('status', 'verified')->sum('amount');
                    $sisa = $order->total_amount - $terbayar;
                @endphp
                <div class="mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-700 text-sm">
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                        <span>Telah Dibayar:</span>
                        <span class="font-medium text-emerald-600 dark:text-emerald-400">Rp {{ number_format($terbayar, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between mt-1 font-bold">
                        <span class="text-zinc-800 dark:text-zinc-200">Sisa Tagihan:</span>
                        <span class="{{ $sisa <= 0 ? 'text-zinc-400' : 'text-red-600 dark:text-red-400' }}">Rp {{ number_format(max(0, $sisa), 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <flux:button variant="ghost" wire:click="$set('show', false)">Tutup</flux:button>
        </div>
    </div>
    @endif
</flux:modal>
