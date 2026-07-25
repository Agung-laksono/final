<?php

use Livewire\Volt\Component;
use Modules\Sales\Models\SalesReturn;
use Modules\Sales\Models\SalesOrder;
use Flux\Flux;

new class extends Component {
    public $return_id = null;
    public $sales_order_id = '';
    public $return_date = '';
    public $reason = '';
    public $notes = '';
    
    public $available_orders = [];
    
    public function mount($id = null)
    {
        // Load orders that have been completed/fulfilled
        $this->available_orders = SalesOrder::where('status', 'completed')
            ->orderBy('id', 'desc')->get();
            
        $this->return_date = date('Y-m-d');
        
        if ($id) {
            $this->return_id = $id;
            $ret = SalesReturn::findOrFail($id);
            $this->sales_order_id = $ret->sales_order_id;
            $this->return_date = $ret->return_date;
            $this->reason = $ret->reason;
            $this->notes = $ret->notes;
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
        
        if ($this->return_id) {
            $ret = SalesReturn::find($this->return_id);
            $ret->update([
                'sales_order_id' => $this->sales_order_id,
                'customer_id' => $order->customer_id,
                'return_date' => $this->return_date,
                'reason' => $this->reason,
                'notes' => $this->notes,
            ]);
            Flux::toast('Retur berhasil diperbarui.');
        } else {
            SalesReturn::create([
                'return_number' => SalesReturn::generateReturnNumber(),
                'sales_order_id' => $this->sales_order_id,
                'customer_id' => $order->customer_id,
                'return_date' => $this->return_date,
                'status' => 'pending',
                'reason' => $this->reason,
                'notes' => $this->notes,
                'created_by' => auth()->id()
            ]);
            Flux::toast('Retur berhasil dibuat.');
        }
        
        return redirect()->route('sales.returns.index');
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-200">{{ $return_id ? 'Detail Retur' : 'Buat Retur Baru' }}</h1>
        <flux:button href="{{ route('sales.returns.index') }}" variant="ghost" icon="arrow-left">Kembali</flux:button>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select wire:model="sales_order_id" label="Pesanan Penjualan (SO)" placeholder="Pilih Pesanan...">
                    @foreach($available_orders as $order)
                        <flux:select.option value="{{ $order->id }}">{{ $order->so_number }} - {{ $order->customer->name ?? '' }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:input type="date" wire:model="return_date" label="Tanggal Retur" />
            </div>
            
            <flux:input wire:model="reason" label="Alasan Retur" placeholder="Misal: Barang cacat pengiriman" />
            <flux:textarea wire:model="notes" label="Catatan Tambahan" />
            
            <div class="flex justify-end pt-4">
                <flux:button type="submit" variant="primary">{{ $return_id ? 'Simpan Perubahan' : 'Buat Retur' }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
