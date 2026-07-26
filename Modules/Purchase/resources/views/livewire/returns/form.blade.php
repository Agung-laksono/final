<?php

use Livewire\Volt\Component;
use Modules\Purchase\Models\PurchaseReturn;
use Modules\Purchase\Models\PurchaseOrder;
use Flux\Flux;

new class extends Component {
    public $return_id = null;
    public $purchase_order_id = '';
    public $return_date = '';
    public $reason = '';
    public $notes = '';
    
    public $available_orders = [];
    
    public function mount($id = null)
    {
        // Load orders that have been received
        $this->available_orders = PurchaseOrder::whereIn('status', ['received', 'completed'])
            ->orderBy('id', 'desc')->get();
            
        $this->return_date = date('Y-m-d');
        
        if ($id) {
            $this->return_id = $id;
            $ret = PurchaseReturn::findOrFail($id);
            $this->purchase_order_id = $ret->purchase_order_id;
            $this->return_date = $ret->return_date;
            $this->reason = $ret->reason;
            $this->notes = $ret->notes;
        }
    }
    
    public function save()
    {
        $this->validate([
            'purchase_order_id' => 'required',
            'return_date' => 'required|date',
            'reason' => 'required'
        ]);
        
        $order = PurchaseOrder::find($this->purchase_order_id);
        
        if ($this->return_id) {
            $ret = PurchaseReturn::find($this->return_id);
            $ret->update([
                'purchase_order_id' => $this->purchase_order_id,
                'vendor_id' => $order->vendor_id,
                'return_date' => $this->return_date,
                'reason' => $this->reason,
                'notes' => $this->notes,
            ]);
            Flux::toast('Retur berhasil diperbarui.');
        } else {
            PurchaseReturn::create([
                'return_number' => PurchaseReturn::generateReturnNumber(),
                'purchase_order_id' => $this->purchase_order_id,
                'vendor_id' => $order->vendor_id,
                'return_date' => $this->return_date,
                'status' => 'pending',
                'reason' => $this->reason,
                'notes' => $this->notes,
                'created_by' => auth()->id()
            ]);
            Flux::toast('Retur berhasil dibuat.');
        }
        
        return redirect()->route('purchase.returns.index');
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-200">{{ $return_id ? 'Detail Retur' : 'Buat Retur Pembelian' }}</h1>
        <flux:button href="{{ route('purchase.returns.index') }}" wire:navigate variant="ghost" icon="arrow-left">Kembali</flux:button>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select wire:model="purchase_order_id" label="Pesanan Pembelian (PO)" placeholder="Pilih PO...">
                    @foreach($available_orders as $order)
                        <flux:select.option value="{{ $order->id }}">{{ $order->po_number }} - {{ $order->vendor->name ?? '' }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:input type="date" wire:model="return_date" label="Tanggal Retur" />
            </div>
            
            <flux:input wire:model="reason" label="Alasan Retur" placeholder="Misal: Barang tidak sesuai pesanan" />
            <flux:textarea wire:model="notes" label="Catatan Tambahan" />
            
            <div class="flex justify-end pt-4">
                <flux:button type="submit" variant="primary">{{ $return_id ? 'Simpan Perubahan' : 'Buat Retur' }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
