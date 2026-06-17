<?php
use function Livewire\Volt\{state, on, rules};
use Modules\Purchase\Models\PurchaseQueue;

state([
    'show' => false,
    'queue_id' => null,
    'item_name' => '',
    'requested_qty' => 0,
    'approved_qty' => 0,
    'notes' => '',
]);

rules([
    'approved_qty' => 'required|numeric|min:0|max:100000',
    'notes' => 'nullable|string|max:500',
]);

on(['open-approval-modal' => function ($id) {
    // Apabila payload yang dikirim dari JS berbentuk { id: 1 },
    // Livewire kadang meneruskannya sebagai array ke parameter pertama.
    if (is_array($id)) {
        $id = $id['id'] ?? null;
    }
    
    if (!$id) return;

    $queue = PurchaseQueue::with('item')->find($id);
    if ($queue) {
        $this->queue_id = $queue->id;
        $this->item_name = $queue->item->name ?? 'Unknown';
        $this->requested_qty = $queue->requested_qty;
        $this->approved_qty = $queue->requested_qty; // Default to requested
        $this->notes = $queue->notes ?? '';
        $this->show = true;
    }
}]);

$saveApproval = function () {
    $this->validate();
    
    $queue = PurchaseQueue::find($this->queue_id);
    if ($queue) {
        // Jika qty 0, maka tolak
        if ($this->approved_qty == 0) {
            $queue->status = 'rejected';
        } else {
            $queue->status = 'approved';
        }
        
        $queue->approved_qty = $this->approved_qty;
        $queue->notes = $this->notes;
        $queue->save();
        
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('purchase_queue');
        $this->show = false;
        \Flux::toast('Persetujuan berhasil disimpan.', variant: 'success');
    }
};

$close = function () {
    $this->show = false;
};
?>

<div>
    <flux:modal wire:model="show" class="md:w-[450px]">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <flux:heading size="lg">Persetujuan Antrean</flux:heading>
                <button type="button" wire:click="close" class="text-zinc-400 hover:text-zinc-500">
                    <flux:icon.x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="space-y-5">
                <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-100 dark:border-zinc-700">
                    <div class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider mb-1">Barang</div>
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $item_name }}</div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:input label="Jumlah Diminta" wire:model="requested_qty" readonly class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 cursor-not-allowed" />
                    </div>
                    <div>
                        <flux:input type="number" label="Jumlah Disetujui" wire:model="approved_qty" required min="0" />
                        <div class="text-[10px] text-zinc-500 mt-1">Ketik 0 jika ditolak seluruhnya.</div>
                    </div>
                </div>

                <div>
                    <flux:textarea label="Catatan Persetujuan (Opsional)" wire:model="notes" rows="3" placeholder="Misal: ACC separuh karena budget menipis..." />
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="close">Batal</flux:button>
                <flux:button variant="primary" wire:click="saveApproval">Simpan Persetujuan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
