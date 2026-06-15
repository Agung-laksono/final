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
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
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
    ]);

    // Update status pembayaran SO
    $totalPaid = $this->order->payments()->sum('amount') + $this->amount;
    
    if ($totalPaid >= $this->order->total_amount) {
        $this->order->payment_status = 'paid';
    } elseif ($totalPaid > 0) {
        $this->order->payment_status = 'partial';
    }
    
    $this->order->save();

    \Flux::toast('Pembayaran berhasil dicatat.', variant: 'success');
    
    $this->order->load('payments'); // Reload
    $this->amount = '';
    $this->proof = null;
    $this->notes = '';
    $this->dispatch('status-updated');
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
                
                <flux:button variant="primary" wire:click="savePayment" icon="plus" class="w-full">Catat Pembayaran</flux:button>
            </div>

            {{-- Bagian Kanan: Riwayat Pembayaran --}}
            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200 mb-3">Riwayat Pembayaran</h3>
                
                <div class="space-y-3 max-h-[300px] overflow-y-auto custom-scrollbar pr-2">
                    @forelse($order->payments as $payment)
                        <div class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800 text-sm flex gap-3">
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                                    <span class="text-[10px] text-zinc-400">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</span>
                                </div>
                                <div class="flex gap-2 text-xs text-zinc-500">
                                    <span class="uppercase font-medium text-emerald-600 dark:text-emerald-400">{{ $payment->payment_method }}</span>
                                    <span>•</span>
                                    <span class="truncate">{{ $payment->notes ?: 'Tanpa catatan' }}</span>
                                </div>
                            </div>
                            @if($payment->proof_path)
                                <a href="{{ Storage::url($payment->proof_path) }}" target="_blank" class="shrink-0 w-12 h-12 bg-zinc-100 dark:bg-zinc-800 rounded-md overflow-hidden hover:opacity-80 transition-opacity" title="Lihat Bukti">
                                    <img src="{{ Storage::url($payment->proof_path) }}" class="w-full h-full object-cover" />
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-zinc-500 text-xs py-4 italic">Belum ada pembayaran</div>
                    @endforelse
                </div>
                
                @php
                    $terbayar = $order->payments->sum('amount');
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
