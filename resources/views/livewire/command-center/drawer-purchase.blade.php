<div class="h-full" x-data="{ activeTab: 'queue' }">
    <div class="flex space-x-2 border-b border-zinc-200 dark:border-zinc-700 mb-4">
        <button x-on:click="activeTab = 'queue'" :class="activeTab === 'queue' ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="px-4 py-2 border-b-2 font-semibold transition-colors">Antrean Pembelian (Queue)</button>
        <button x-on:click="activeTab = 'po'" :class="activeTab === 'po' ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="px-4 py-2 border-b-2 font-semibold transition-colors">Purchase Order (PO)</button>
    </div>

    <div x-show="activeTab === 'queue'">
        @livewire('queue.kanban')
    </div>

    <div x-show="activeTab === 'po'" x-cloak>
        @livewire('order.kanban')
    </div>
</div>
