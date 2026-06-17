<?php

use function Livewire\Volt\{state, on, computed};
use Modules\Purchase\Models\PurchaseQueue;

state([
    'show' => false,
    'selected_queues' => [],
]);

$approvedQueues = computed(function () {
    return PurchaseQueue::with('item.unit')->where('status', 'approved')->latest()->get();
});

on(['open-consolidation-modal' => function () {
    $this->selected_queues = [];
    $this->show = true;
}]);

$proceedToPO = function () {
    if (empty($this->selected_queues)) {
        \Flux::toast('Pilih minimal satu antrean untuk dibuatkan PO.', 'error');
        return;
    }
    
    // Redirect ke halaman Create PO dengan membawa parameter queues
    $queueIds = implode(',', $this->selected_queues);
    return redirect()->route('purchase.orders.create', ['queues' => $queueIds]);
};

?>

<div>
    <flux:modal wire:model="show" class="md:w-[700px]" wire:ignore.self>
        <div class="border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-5 flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center shrink-0">
                <flux:icon.document-duplicate class="w-5 h-5" />
            </div>
            <div>
                <flux:heading size="lg">Konsolidasi Purchase Order</flux:heading>
                <flux:subheading>Pilih tiket antrean mana saja yang ingin digabungkan ke dalam satu Purchase Order.</flux:subheading>
            </div>
        </div>

        @if(count($this->approvedQueues) > 0)
            <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                @foreach($this->approvedQueues as $q)
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer transition-colors"
                           :class="{ 'border-blue-500 bg-blue-50/30 dark:bg-blue-900/10': @js(in_array($q->id, $this->selected_queues)) }">
                        <div class="mt-1">
                            <flux:checkbox wire:model.live="selected_queues" value="{{ $q->id }}" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 truncate pr-2">{{ $q->item->name }}</div>
                                <div class="font-mono text-xs text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded shrink-0">#ANT-{{ str_pad($q->id, 4, '0', STR_PAD_LEFT) }}</div>
                            </div>
                            <div class="text-xs text-zinc-500 mt-1 flex items-center gap-2">
                                <span class="font-bold text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-900/20 px-1.5 py-0.5 rounded">Disetujui: {{ $q->approved_qty ?? $q->requested_qty }} {{ $q->item->unit->name ?? 'Unit' }}</span>
                                <span class="text-zinc-300">&bull;</span>
                                <span>Sumber: {{ ucwords(str_replace('_', ' ', $q->source_type)) }}</span>
                            </div>
                            @if($q->notes)
                                <div class="text-[11px] text-zinc-400 mt-1 italic truncate"><flux:icon.document-text class="w-3 h-3 inline mr-1"/>{{ $q->notes }}</div>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>
        @else
            <div class="py-12 flex flex-col items-center justify-center text-zinc-400 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                <flux:icon.inbox class="w-12 h-12 mb-3 text-zinc-300" />
                <p>Tidak ada tiket antrean yang berstatus Disetujui.</p>
            </div>
        @endif

        <div class="flex justify-between items-center mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <div class="text-sm text-zinc-500 font-medium">
                Terpilih: <span class="text-blue-600 dark:text-blue-400 font-bold" x-text="$wire.selected_queues.length"></span> tiket
            </div>
            <div class="flex gap-3">
                <flux:button wire:click="$set('show', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="proceedToPO" icon="arrow-right">Lanjut ke PO</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
