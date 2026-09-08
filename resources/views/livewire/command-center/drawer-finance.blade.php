<div class="h-full" x-data="{ activeTab: 'dashboard' }">
    <div class="flex space-x-2 border-b border-zinc-200 dark:border-zinc-700 mb-4">
        <button x-on:click="activeTab = 'dashboard'" :class="activeTab === 'dashboard' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="px-4 py-2 border-b-2 font-semibold transition-colors">Dashboard Keuangan</button>
        <button x-on:click="activeTab = 'payables'" :class="activeTab === 'payables' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="px-4 py-2 border-b-2 font-semibold transition-colors">Hutang (Payables)</button>
        <button x-on:click="activeTab = 'inbox'" :class="activeTab === 'inbox' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'" class="px-4 py-2 border-b-2 font-semibold transition-colors">Inbox Validasi</button>
    </div>

    <div x-show="activeTab === 'dashboard'">
        @livewire('finance.dashboard')
    </div>

    <div x-show="activeTab === 'payables'" x-cloak>
        @livewire('finance.payables')
    </div>

    <div x-show="activeTab === 'inbox'" x-cloak>
        @livewire('finance.inbox')
    </div>
</div>
