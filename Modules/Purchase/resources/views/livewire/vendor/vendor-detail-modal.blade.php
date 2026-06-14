<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Modules\Purchase\Models\Vendor;
use Carbon\Carbon;
use Flux\Flux;

new class extends Component {
    public $vendor_id = null;
    public $vendor = null;
    
    // Analytics Metrics
    public $totalTransactions = 0;
    public $orderFrequency = 0;
    public $averageLeadTime = 0;
    
    // History
    public $recentPOs = [];

    #[On('open-vendor-detail')]
    public function openModal($id)
    {
        $this->vendor_id = $id;
        $this->vendor = Vendor::with(['purchaseOrders' => function($q) {
            $q->whereNotIn('status', ['rejected', 'cancelled'])->latest('order_date');
        }, 'purchaseOrders.receipts'])->find($id);
        
        if (!$this->vendor) {
            return;
        }

        $this->calculateAnalytics();
        
        Flux::modal('vendor-detail-modal')->show();
    }
    
    private function calculateAnalytics()
    {
        $pos = $this->vendor->purchaseOrders;
        
        $this->orderFrequency = $pos->count();
        $this->totalTransactions = $pos->sum('total_amount');
        
        // Calculate Lead Time
        $totalDays = 0;
        $receiptCount = 0;
        
        foreach ($pos as $po) {
            // Find the first receipt for this PO
            $firstReceipt = $po->receipts->sortBy('receipt_date')->first();
            if ($firstReceipt && $po->order_date) {
                $orderDate = Carbon::parse($po->order_date);
                $receiptDate = Carbon::parse($firstReceipt->receipt_date);
                
                // Diff in days (at least 0)
                $diff = max(0, $orderDate->diffInDays($receiptDate));
                $totalDays += $diff;
                $receiptCount++;
            }
        }
        
        $this->averageLeadTime = $receiptCount > 0 ? round($totalDays / $receiptCount, 1) : 0;
        
        // Recent 5 POs
        $this->recentPOs = clone $pos;
        $this->recentPOs = $this->recentPOs->take(5);
    }
};
?>

<div>
    <flux:modal name="vendor-detail-modal" class="md:w-[700px] max-w-4xl space-y-6">
        @if($vendor)
            <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-800 pb-4">
                <div class="flex items-center gap-4">
                    <flux:avatar src="{{ $vendor->image ? Storage::url($vendor->image) : '' }}" fallback="{{ substr($vendor->name, 0, 2) }}" size="lg" />
                    <div>
                        <flux:heading size="lg">{{ $vendor->name }}</flux:heading>
                        <flux:subheading class="flex items-center gap-2 mt-1">
                            <flux:badge size="sm" color="blue">{{ $vendor->type }}</flux:badge>
                            <span><flux:icon.phone class="w-3.5 h-3.5 inline-block" /> {{ $vendor->phone ?? '-' }}</span>
                        </flux:subheading>
                    </div>
                </div>
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" />
                </flux:modal.close>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:card class="p-4 flex flex-col gap-1 items-start bg-zinc-50 dark:bg-zinc-800/50">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Total Transaksi</span>
                    <h3 class="text-xl font-bold text-zinc-900 dark:text-white truncate w-full" title="Rp {{ number_format($totalTransactions, 0, ',', '.') }}">Rp {{ number_format($totalTransactions, 0, ',', '.') }}</h3>
                </flux:card>
                
                <flux:card class="p-4 flex flex-col gap-1 items-start bg-zinc-50 dark:bg-zinc-800/50">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Frekuensi Order</span>
                    <h3 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $orderFrequency }} <span class="text-sm font-normal text-zinc-500">PO</span></h3>
                </flux:card>
                
                <flux:card class="p-4 flex flex-col gap-1 items-start bg-zinc-50 dark:bg-zinc-800/50">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Rata-Rata Lead Time</span>
                    <div class="flex items-end gap-1">
                        <h3 class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($averageLeadTime, 1, ',', '.') }}</h3>
                        <span class="text-sm font-medium text-zinc-500 mb-0.5">Hari</span>
                    </div>
                </flux:card>
            </div>
            
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-zinc-800 dark:text-zinc-200">Riwayat Pesanan Terakhir</h4>
                    <span class="text-xs text-zinc-500">Menampilkan maks. 5 PO terbaru</span>
                </div>
                
                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Tanggal</flux:table.column>
                            <flux:table.column>No. PO</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Nilai</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($recentPOs as $po)
                                <flux:table.row>
                                    <flux:table.cell class="whitespace-nowrap">{{ \Carbon\Carbon::parse($po->order_date)->format('d M Y') }}</flux:table.cell>
                                    <flux:table.cell class="font-medium text-cyan-600 dark:text-cyan-400">{{ $po->po_number }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="match($po->status) {
                                            'completed' => 'green',
                                            'processing' => 'amber',
                                            'approved' => 'blue',
                                            'rejected', 'cancelled' => 'red',
                                            default => 'zinc',
                                        }">{{ ucfirst($po->status) }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap font-medium text-zinc-900 dark:text-white">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="text-center py-6 text-zinc-400">Belum ada riwayat pesanan ke vendor ini.</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
            
            <div class="pt-4 flex justify-end gap-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost">Tutup</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="pencil-square" wire:click="$dispatch('open-vendor-modal', { id: {{ $vendor->id }} })" x-on:click="$flux.modal('vendor-detail-modal').close()">
                    Edit Vendor
                </flux:button>
            </div>
        @else
            <div class="py-8 flex items-center justify-center">
                <flux:icon.arrow-path class="w-6 h-6 animate-spin text-zinc-400" />
            </div>
        @endif
    </flux:modal>
</div>
