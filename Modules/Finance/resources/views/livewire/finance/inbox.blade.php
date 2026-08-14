<?php

use function Livewire\Volt\{state, layout, computed, title, on};
use Modules\Finance\Models\FinanceAccount;

layout('layouts.app');
title('Kotak Masuk Validasi');

state([
    'showRejectModal' => false,
    'rejectingId' => null,
    'rejectingType' => null, // 'sales' or 'purchase'
    'rejectReason' => '',
]);

$accounts = computed(function () {
    return FinanceAccount::where('is_active', true)
        ->when(!auth()->user()->hasRole('Super Admin'), function ($query) {
            $query->where('user_id', auth()->id());
        })
        ->get();
});

$pendingSales = computed(function () {
    return \Modules\Sales\Models\SalesPayment::with(['salesOrder', 'creator', 'financeAccount'])->where('status', 'pending')->get();
});

$pendingPurchases = computed(function () {
    return \Modules\Purchase\Models\PurchasePayment::with(['purchaseOrder', 'creator', 'financeAccount'])->where('status', 'pending')->get();
});

$approveSales = function ($paymentId) {
    $payment = \Modules\Sales\Models\SalesPayment::find($paymentId);
    if ($payment && $payment->finance_account_id) {
        app(\Modules\Finance\Services\FinanceService::class)->approveSalesPayment($payment, $payment->finance_account_id, auth()->id());
        \Flux::toast('Pembayaran penjualan berhasil divalidasi!', variant: 'success');
    } else {
        \Flux::toast('Rekening tujuan tidak valid atau belum dipilih.', variant: 'danger');
    }
};

$approvePurchase = function ($paymentId) {
    $payment = \Modules\Purchase\Models\PurchasePayment::find($paymentId);
    if ($payment && $payment->finance_account_id) {
        app(\Modules\Finance\Services\FinanceService::class)->approvePurchasePayment($payment, $payment->finance_account_id, auth()->id());
        \Flux::toast('Pembayaran pembelian berhasil divalidasi!', variant: 'success');
    } else {
        \Flux::toast('Rekening sumber tidak valid atau belum dipilih.', variant: 'danger');
    }
};

$confirmReject = function($id, $type) {
    $this->rejectingId = $id;
    $this->rejectingType = $type;
    $this->rejectReason = '';
    $this->showRejectModal = true;
};

$submitReject = function() {
    $this->validate(
        ['rejectReason' => 'required|min:5'],
        ['rejectReason.required' => 'Alasan penolakan wajib diisi.', 'rejectReason.min' => 'Alasan penolakan minimal 5 karakter.']
    );
    
    if ($this->rejectingType === 'sales') {
        $payment = \Modules\Sales\Models\SalesPayment::find($this->rejectingId);
        if ($payment) {
            app(\Modules\Finance\Services\FinanceService::class)->rejectPayment($payment, $this->rejectReason, auth()->id());
            \Flux::toast('Pembayaran penjualan ditolak.', variant: 'warning');
        }
    } else {
        $payment = \Modules\Purchase\Models\PurchasePayment::find($this->rejectingId);
        if ($payment) {
            app(\Modules\Finance\Services\FinanceService::class)->rejectPayment($payment, $this->rejectReason, auth()->id());
            \Flux::toast('Pembayaran pembelian ditolak.', variant: 'warning');
        }
    }
    
    $this->showRejectModal = false;
};

on([
    'echo:kanban,KanbanUpdated' => '$refresh',
    'echo:finance,PaymentSubmitted' => '$refresh'
]);

?>

<div class="space-y-6" x-data="{ previewImage: '', showPreviewModal: false }">
    <div class="flex items-center gap-3 mb-2">
        <div class="w-12 h-12 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
            <flux:icon.inbox-arrow-down class="w-6 h-6" />
        </div>
        <div>
            <flux:heading size="xl">Kotak Masuk Validasi</flux:heading>
            <flux:subheading>Transaksi tertunda dari berbagai divisi yang membutuhkan konfirmasi (ACC) Anda.</flux:subheading>
        </div>
        
        @if($this->pendingSales->count() + $this->pendingPurchases->count() > 0)
            <flux:badge color="rose" class="ml-auto text-lg px-3 py-1">{{ $this->pendingSales->count() + $this->pendingPurchases->count() }} Menunggu</flux:badge>
        @endif
    </div>
    
    @if($this->pendingSales->count() == 0 && $this->pendingPurchases->count() == 0)
        <div class="bg-zinc-50 dark:bg-zinc-800/50 border border-dashed border-zinc-300 dark:border-zinc-700 rounded-2xl p-16 text-center flex flex-col items-center">
            <flux:icon.check-circle class="w-20 h-20 text-emerald-400 mb-6 opacity-50" />
            <h3 class="text-2xl font-black text-zinc-900 dark:text-zinc-100">Semua Transaksi Telah Divalidasi!</h3>
            <p class="text-zinc-500 max-w-lg mx-auto mt-3">Tidak ada transaksi yang mengantre untuk divalidasi saat ini. Anda bisa kembali bersantai atau memeriksa buku besar kas.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            
            <!-- Sales Payments (Uang Masuk) -->
            @foreach($this->pendingSales as $payment)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl flex flex-col overflow-hidden shadow-sm hover:shadow-md hover:border-emerald-300 transition-all duration-300 group">
                    <!-- Header -->
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 px-5 py-3 border-b border-emerald-100 dark:border-emerald-900/50 flex justify-between items-center">
                        <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400">
                            <flux:icon.arrow-down-tray class="w-4 h-4" />
                            <span class="text-xs font-bold uppercase tracking-wider">Uang Masuk</span>
                        </div>
                        <flux:badge color="zinc" size="sm">Penjualan</flux:badge>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="text-xs text-zinc-500 mb-0.5">Referensi SO</div>
                                <div class="font-mono text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $payment->salesOrder->so_number ?? 'SO-Unknown' }}</div>
                            </div>
                            @if($payment->proof_path)
                                <button type="button" @click="previewImage = '{{ Storage::url($payment->proof_path) }}'; showPreviewModal = true" class="shrink-0 w-12 h-12 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 hover:opacity-80 transition-opacity tooltip" title="Lihat Bukti Transfer">
                                    <img src="{{ Storage::url($payment->proof_path) }}" class="w-full h-full object-cover" alt="Bukti" />
                                </button>
                            @else
                                <div class="shrink-0 w-12 h-12 rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 border-dashed flex items-center justify-center">
                                    <flux:icon.photo class="w-5 h-5 text-zinc-300" />
                                </div>
                            @endif
                        </div>
                        
                        <div class="my-3">
                            <div class="text-3xl font-black text-zinc-900 dark:text-white tracking-tight">
                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            </div>
                        </div>
                        
                        <div class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar class="w-4 h-4 text-zinc-400" />
                                <span>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon.credit-card class="w-4 h-4 text-zinc-400" />
                                <span class="uppercase font-medium text-zinc-700 dark:text-zinc-300">{{ $payment->payment_method ?? 'Transfer' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon.building-library class="w-4 h-4 text-zinc-400" />
                                <span>Tujuan: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $payment->financeAccount->name ?? 'Belum dipilih' }}</span></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon.user class="w-4 h-4 text-zinc-400" />
                                <span>Disubmit oleh <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $payment->creator->name ?? 'Sistem' }}</span></span>
                            </div>
                            @if($payment->notes)
                            <div class="flex items-start gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800 mt-2">
                                <flux:icon.chat-bubble-left-ellipsis class="w-4 h-4 text-zinc-400 mt-0.5 shrink-0" />
                                <span class="text-xs leading-relaxed italic">{{ $payment->notes }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Footer / Actions -->
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-100 dark:border-zinc-800 flex gap-2 mt-auto">
                        <flux:button wire:click="confirmReject({{ $payment->id }}, 'sales')" variant="danger" class="w-1/3 text-xs">
                            Tolak
                        </flux:button>
                        
                        <flux:button variant="primary" wire:click="approveSales({{ $payment->id }})" icon="check-circle" class="w-2/3 bg-emerald-600 hover:bg-emerald-700 text-white border-none text-xs">
                            Validasi
                        </flux:button>
                    </div>
                </div>
            @endforeach
            
            <!-- Purchase Payments (Uang Keluar) -->
            @foreach($this->pendingPurchases as $payment)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl flex flex-col overflow-hidden shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-300 group">
                    <!-- Header -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 px-5 py-3 border-b border-blue-100 dark:border-blue-900/50 flex justify-between items-center">
                        <div class="flex items-center gap-2 text-blue-700 dark:text-blue-400">
                            <flux:icon.arrow-up-tray class="w-4 h-4" />
                            <span class="text-xs font-bold uppercase tracking-wider">Uang Keluar</span>
                        </div>
                        <flux:badge color="zinc" size="sm">Pembelian / SPK</flux:badge>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="text-xs text-zinc-500 mb-0.5">Referensi PO</div>
                                <div class="font-mono text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $payment->purchaseOrder->po_number ?? 'PO-Unknown' }}</div>
                            </div>
                            @if($payment->proof_path)
                                <button type="button" @click="previewImage = '{{ Storage::url($payment->proof_path) }}'; showPreviewModal = true" class="shrink-0 w-12 h-12 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 hover:opacity-80 transition-opacity tooltip" title="Lihat Bukti Transfer">
                                    <img src="{{ Storage::url($payment->proof_path) }}" class="w-full h-full object-cover" alt="Bukti" />
                                </button>
                            @else
                                <div class="shrink-0 w-12 h-12 rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 border-dashed flex items-center justify-center">
                                    <flux:icon.document class="w-5 h-5 text-zinc-300" />
                                </div>
                            @endif
                        </div>
                        
                        <div class="my-3">
                            <div class="text-3xl font-black text-zinc-900 dark:text-white tracking-tight">
                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            </div>
                        </div>
                        
                        <div class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400 mt-2">
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar class="w-4 h-4 text-zinc-400" />
                                <span>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon.credit-card class="w-4 h-4 text-zinc-400" />
                                <span class="uppercase font-medium text-zinc-700 dark:text-zinc-300">{{ $payment->payment_method ?? 'Transfer' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon.building-library class="w-4 h-4 text-zinc-400" />
                                <span>Sumber Dana: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $payment->financeAccount->name ?? 'Belum dipilih' }}</span></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon.user class="w-4 h-4 text-zinc-400" />
                                <span>Disubmit oleh <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $payment->creator->name ?? 'Sistem' }}</span></span>
                            </div>
                            @if($payment->notes)
                            <div class="flex items-start gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800 mt-2">
                                <flux:icon.chat-bubble-left-ellipsis class="w-4 h-4 text-zinc-400 mt-0.5 shrink-0" />
                                <span class="text-xs leading-relaxed italic">{{ $payment->notes }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Footer / Actions -->
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-100 dark:border-zinc-800 flex gap-2 mt-auto">
                        <flux:button wire:click="confirmReject({{ $payment->id }}, 'purchase')" variant="danger" class="w-1/3 text-xs">
                            Tolak
                        </flux:button>
                        
                        <flux:button variant="primary" wire:click="approvePurchase({{ $payment->id }})" icon="check-circle" class="w-2/3 bg-blue-600 hover:bg-blue-700 text-white border-none text-xs">
                            Validasi
                        </flux:button>
                    </div>
                </div>
            @endforeach
            
        </div>
    @endif

    <!-- Alpine Modal for Image Preview -->
    <div x-show="showPreviewModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" x-transition.opacity>
        <div @click.away="showPreviewModal = false" class="relative max-w-4xl w-full max-h-[90vh] bg-zinc-900 rounded-2xl overflow-hidden shadow-2xl flex flex-col">
            <div class="flex justify-between items-center p-4 border-b border-zinc-800 bg-zinc-900/50 absolute top-0 left-0 w-full z-10">
                <div class="text-white font-medium text-sm">Pratinjau Bukti Transfer</div>
                <button @click="showPreviewModal = false" class="text-zinc-400 hover:text-white transition-colors bg-black/50 hover:bg-black rounded-full p-2">
                    <flux:icon.x-mark class="w-5 h-5" />
                </button>
            </div>
            <div class="overflow-auto flex-1 p-4 pt-20 flex items-center justify-center">
                <img :src="previewImage" class="max-w-full max-h-[80vh] object-contain rounded" />
            </div>
        </div>
    </div>

    <!-- Livewire Modal for Rejection Reason -->
    <flux:modal wire:model="showRejectModal" class="w-full md:w-[32rem]">
        <div class="flex items-start gap-4 mb-4">
            <div class="flex-shrink-0 w-10 h-10 bg-red-100 text-red-600 rounded-full flex items-center justify-center">
                <flux:icon.x-mark class="w-6 h-6" />
            </div>
            <div>
                <flux:heading size="lg">Tolak Pembayaran</flux:heading>
                <flux:subheading>Pembayaran yang ditolak akan dikembalikan statusnya, dan staf terkait wajib mengunggah bukti ulang.</flux:subheading>
            </div>
        </div>

        <form wire:submit.prevent="submitReject" class="space-y-4">
            <div>
                <flux:textarea wire:model="rejectReason" label="Alasan Penolakan" placeholder="Contoh: Mutasi belum masuk di rekening BCA. Harap cek kembali..." rows="4" required />
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showRejectModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="danger">Konfirmasi Tolak</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
