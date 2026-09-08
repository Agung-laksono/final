<div class="h-full" x-data="{ activeTab: 'ir' }">
    <div class="flex space-x-2 border-b border-zinc-200 dark:border-zinc-700 mb-4">
        <button x-on:click="activeTab = 'ir'" :class="activeTab === 'ir' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="px-4 py-2 border-b-2 font-semibold transition-colors">Inventory Requests</button>
        <button x-on:click="activeTab = 'fulfillment'" :class="activeTab === 'fulfillment' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="px-4 py-2 border-b-2 font-semibold transition-colors">Fulfillment Produksi</button>
        <button x-on:click="activeTab = 'dispatch'" :class="activeTab === 'dispatch' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="px-4 py-2 border-b-2 font-semibold transition-colors">Dispatch (Pengiriman)</button>
    </div>

    <div x-show="activeTab === 'ir'">
        @livewire('request.kanban')
    </div>

    <div x-show="activeTab === 'fulfillment'" x-cloak>
        @livewire('fulfillments')
    </div>

    <div x-show="activeTab === 'dispatch'" x-cloak>
        @livewire('dispatch.index')
    </div>
</div>
