<?php

use Livewire\Volt\Component;
use Modules\Sales\Models\SalesReturn;
use Modules\Sales\Models\SalesReturnItem;
use Modules\Sales\Models\SalesOrder;
use App\Services\InventoryService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $return_id = null;
    public $sales_order_id = '';
    public $return_date = '';
    public $reason = '';
    public $notes = '';
    public $status = 'pending';
    
    // Items state
    public $return_items = []; // [sales_order_item_id => ['selected' => bool, 'qty' => int, 'condition' => string]]
    
    public $available_orders = [];
    public $selected_order = null;
    
    public function mount($id = null)
    {
        // Load orders that have been completed/fulfilled
        $this->available_orders = SalesOrder::where('status', 'completed')
            ->orderBy('id', 'desc')->get();
            
        $this->return_date = date('Y-m-d');
        
        if ($id) {
            $this->return_id = $id;
            $ret = SalesReturn::with('items.salesOrderItem.item')->findOrFail($id);
            $this->sales_order_id = $ret->sales_order_id;
            $this->return_date = $ret->return_date;
            $this->reason = $ret->reason;
            $this->notes = $ret->notes;
            $this->status = $ret->status;
            
            $this->selected_order = SalesOrder::with('items.item')->find($this->sales_order_id);
            
            // Populate return items
            foreach ($ret->items as $item) {
                $this->return_items[$item->sales_order_item_id] = [
                    'selected' => true,
                    'qty' => $item->quantity,
                    'condition' => $item->condition,
                ];
            }
        }
    }
    
    public function updatedSalesOrderId($value)
    {
        $this->selected_order = SalesOrder::with('items.item')->find($value);
        $this->return_items = [];
        if ($this->selected_order) {
            foreach ($this->selected_order->items as $item) {
                $this->return_items[$item->id] = [
                    'selected' => false,
                    'qty' => $item->qty,
                    'condition' => 'good',
                ];
            }
        }
    }
    
    public function save()
    {
        $this->validate([
            'sales_order_id' => 'required',
            'return_date' => 'required|date',
            'reason' => 'required'
        ]);
        
        $order = SalesOrder::find($this->sales_order_id);
        
        DB::transaction(function () use ($order) {
            if ($this->return_id) {
                $ret = SalesReturn::find($this->return_id);
                $ret->update([
                    'sales_order_id' => $this->sales_order_id,
                    'customer_id' => $order->customer_id,
                    'return_date' => $this->return_date,
                    'reason' => $this->reason,
                    'notes' => $this->notes,
                ]);
                
                // Hapus item lama
                $ret->items()->delete();
                $returnModel = $ret;
            } else {
                $returnModel = SalesReturn::create([
                    'return_number' => SalesReturn::generateReturnNumber(),
                    'sales_order_id' => $this->sales_order_id,
                    'customer_id' => $order->customer_id,
                    'return_date' => $this->return_date,
                    'status' => 'pending',
                    'reason' => $this->reason,
                    'notes' => $this->notes,
                    'created_by' => auth()->id()
                ]);
            }
            
            // Simpan item retur
            foreach ($this->return_items as $soi_id => $data) {
                if (!empty($data['selected']) && $data['qty'] > 0) {
                    $soItem = $order->items->where('id', $soi_id)->first();
                    if ($soItem) {
                        SalesReturnItem::create([
                            'sales_return_id' => $returnModel->id,
                            'sales_order_item_id' => $soi_id,
                            'item_id' => $soItem->item_id,
                            'quantity' => $data['qty'],
                            'condition' => $data['condition'] ?? 'good',
                            'action_requested' => 'refund',
                        ]);
                    }
                }
            }
        });
        
        Flux::toast($this->return_id ? 'Retur berhasil diperbarui.' : 'Retur berhasil dibuat.');
        
        return redirect()->route('sales.returns.index');
    }
    
    public function approve(InventoryService $inventoryService)
    {
        if ($this->status !== 'pending') return;
        
        $ret = SalesReturn::with('items.item')->findOrFail($this->return_id);
        
        DB::transaction(function () use ($ret, $inventoryService) {
            $ret->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
            ]);
            
            // 1. Integrasi: Kembalikan Stok ke Gudang (Inventory)
            $warehouseId = \Modules\Inventory\Models\Warehouse::first()->id ?? 1; // Asumsi Gudang Utama
            foreach ($ret->items as $retItem) {
                $notes = "Retur Penjualan: {$ret->return_number} (Kondisi: {$retItem->condition})";
                $inventoryService->adjustStock(
                    $retItem->item_id,
                    $warehouseId,
                    $retItem->quantity,
                    'in',
                    $ret->return_number,
                    $notes
                );
            }
            
            // 2. Integrasi: Notifikasi/Pembuatan Refund ke Finance
            // Opsional: Buat pengeluaran FinanceTransaction atau antrean internal transfer
            // Saat ini kita tandai bahwa SO ini perlu direfund.
            $ret->salesOrder->update(['payment_status' => 'refund_pending']);
        });
        
        Flux::toast('Retur berhasil disetujui. Stok dikembalikan dan Refund masuk ke Finance.', variant: 'success');
        $this->status = 'approved';
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-200">{{ $return_id ? 'Detail Retur' : 'Buat Retur Baru' }}</h1>
        <div class="flex gap-2">
            @if($return_id && $status === 'pending')
                <flux:button variant="primary" icon="check" wire:click="approve" wire:confirm="Anda yakin menyetujui retur ini? Stok akan otomatis dikembalikan ke gudang.">Setujui Retur</flux:button>
            @endif
            <flux:button href="{{ route('sales.returns.index') }}" wire:navigate variant="ghost" icon="arrow-left">Kembali</flux:button>
        </div>
    </div>

    @if($status === 'approved')
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-xl flex items-center gap-3">
            <flux:icon.check-circle class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            <div>
                <h3 class="font-bold text-emerald-800 dark:text-emerald-300">Retur Disetujui</h3>
                <p class="text-sm text-emerald-600 dark:text-emerald-400">Stok telah dikembalikan ke Gudang dan permintaan Refund telah diteruskan ke Finance.</p>
            </div>
        </div>
    @endif

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select wire:model.live="sales_order_id" label="Pesanan Penjualan (SO)" placeholder="Pilih Pesanan..." :disabled="$status !== 'pending'">
                    @foreach($available_orders as $order)
                        <flux:select.option value="{{ $order->id }}">{{ $order->so_number }} - {{ $order->customer->name ?? '' }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:input type="date" wire:model="return_date" label="Tanggal Retur" :disabled="$status !== 'pending'" />
            </div>
            
            @if($selected_order)
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                <div class="bg-zinc-50 dark:bg-zinc-800/50 px-4 py-2 border-b border-zinc-200 dark:border-zinc-700">
                    <h3 class="font-bold text-sm text-zinc-700 dark:text-zinc-300">Pilih Barang yang Diretur</h3>
                </div>
                <div class="p-4 space-y-3">
                    @foreach($selected_order->items as $item)
                        <div class="flex items-center gap-4 bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            <flux:checkbox wire:model="return_items.{{ $item->id }}.selected" :disabled="$status !== 'pending'" />
                            <div class="flex-1">
                                <div class="font-bold text-sm">{{ $item->item->name }}</div>
                                <div class="text-xs text-zinc-500">Dibeli: {{ $item->qty }} {{ $item->item->unit->name ?? 'pcs' }}</div>
                            </div>
                            @if(isset($return_items[$item->id]['selected']) && $return_items[$item->id]['selected'])
                            <div class="flex items-center gap-3">
                                <div class="w-24">
                                    <flux:input type="number" wire:model="return_items.{{ $item->id }}.qty" min="1" max="{{ $item->qty }}" size="sm" :disabled="$status !== 'pending'" />
                                </div>
                                <div class="w-32">
                                    <flux:select wire:model="return_items.{{ $item->id }}.condition" size="sm" :disabled="$status !== 'pending'">
                                        <flux:select.option value="good">Bagus (Good)</flux:select.option>
                                        <flux:select.option value="damaged">Rusak (Damaged)</flux:select.option>
                                        <flux:select.option value="wrong_item">Salah Barang</flux:select.option>
                                    </flux:select>
                                </div>
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
            
            <flux:input wire:model="reason" label="Alasan Retur" placeholder="Misal: Barang cacat pengiriman" :disabled="$status !== 'pending'" />
            <flux:textarea wire:model="notes" label="Catatan Tambahan" :disabled="$status !== 'pending'" />
            
            @if($status === 'pending')
            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="primary">{{ $return_id ? 'Simpan Perubahan' : 'Buat Retur' }}</flux:button>
            </div>
            @endif
        </form>
    </flux:card>
</div>

