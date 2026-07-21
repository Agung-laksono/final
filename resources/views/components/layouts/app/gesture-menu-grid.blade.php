<div class="px-4 pb-20 pt-2 space-y-8">

    <!-- Inventory -->
    @can('inventory.view')
    <div>
        <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('INVENTORY') }}</h3>
        <div class="grid grid-cols-4 gap-y-6 gap-x-2">
            <a href="{{ route('inventory') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.chart-pie class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Dashboard</span>
            </a>

            @can('inventory.item.view')
            <a href="{{ route('inventory.items') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.cube class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Barang</span>
            </a>
            @endcan

            @can('inventory.warehouse.view')
            <a href="{{ route('inventory.warehouses') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.building-storefront class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Gudang</span>
            </a>
            @endcan

            @can('inventory.transfer.view')
            <a href="{{ route('inventory.transfers') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <div class="relative">
                        <flux:icon.arrows-right-left class="size-6" />
                        <livewire:layout.sidebar-badge type="inventory_transfer" />
                    </div>
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Transfer</span>
            </a>

            <a href="{{ route('inventory.requests') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <div class="relative">
                        <flux:icon.inbox-arrow-down class="size-6" />
                        <livewire:layout.sidebar-badge type="inventory_request" />
                    </div>
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Permintaan</span>
            </a>
            @endcan

            @can('inventory.stock.create')
            <a href="{{ route('inventory.dispatch') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.arrow-right-end-on-rectangle class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Kedatangan</span>
            </a>

            <a href="{{ route('inventory.production-receipts') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.arrow-down-tray class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Terima QC</span>
            </a>
            @endcan

            @can('production.order.update')
            <a href="{{ route('inventory.fulfillments') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.clipboard-document-check class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Pemenuhan</span>
            </a>
            @endcan

            @can('inventory.view')
            <a href="{{ route('inventory.sales-deliveries') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.truck class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Pengiriman</span>
            </a>
            @endcan

            @can('inventory.movement.view')
            <a href="{{ route('inventory.movements') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.clock class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Riwayat</span>
            </a>
            @endcan

            @can('inventory.opname.view')
            <a href="{{ route('inventory.stock-opname') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.adjustments-horizontal class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Opname</span>
            </a>
            @endcan
        </div>
    </div>
    @endcan

    <!-- Pembelian -->
    @can('purchase.dashboard.view')
    <div>
        <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('PEMBELIAN') }}</h3>
        <div class="grid grid-cols-4 gap-y-6 gap-x-2">
            @can('purchase.dashboard.view')
            <a href="{{ route('purchase.index') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.shopping-cart class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Dashboard</span>
            </a>
            @endcan

            @can('purchase.queue.view')
            <a href="{{ route('purchase.queues.kanban') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <div class="relative">
                        <flux:icon.queue-list class="size-6" />
                        <livewire:layout.sidebar-badge type="purchase_queue" />
                    </div>
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Permintaan</span>
            </a>
            @endcan

            @can('purchase.order.view')
            <a href="{{ route('purchase.orders.kanban') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <div class="relative">
                        <flux:icon.clipboard-document-list class="size-6" />
                        <livewire:layout.sidebar-badge type="purchase_order" />
                    </div>
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Kanban PO</span>
            </a>
            @endcan

            @can('purchase.vendor.view')
            <a href="{{ route('purchase.vendors.index') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.building-office-2 class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Vendor</span>
            </a>
            @endcan
        </div>
    </div>
    @endcan

    <!-- Produksi -->
    @can('production.dashboard.view')
    <div>
        <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('PRODUKSI') }}</h3>
        <div class="grid grid-cols-4 gap-y-6 gap-x-2">
            @can('production.order.view')
            <a href="{{ route('production.orders') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <div class="relative">
                        <flux:icon.wrench-screwdriver class="size-6" />
                        <livewire:layout.sidebar-badge type="production_order" />
                    </div>
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Kanban</span>
            </a>

            <a href="{{ route('production.recipes') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.document-text class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Resep</span>
            </a>
            @endcan
        </div>
    </div>
    @endcan

    <!-- Penjualan -->
    @can('sales.dashboard.view')
    <div>
        <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('PENJUALAN') }}</h3>
        <div class="grid grid-cols-4 gap-y-6 gap-x-2">
            @can('sales.customer.view')
            <a href="{{ route('sales.customers.index') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.user-group class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Pelanggan</span>
            </a>
            @endcan

            @can('sales.order.view')
            <a href="{{ route('sales.orders.index') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <div class="relative">
                        <flux:icon.clipboard-document-list class="size-6" />
                        <livewire:layout.sidebar-badge type="sales_order" />
                    </div>
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Kanban SO</span>
            </a>
            @endcan
        </div>
    </div>
    @endcan

    <!-- Komunikasi -->
    <div>
        <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('KOMUNIKASI') }}</h3>
        <div class="grid grid-cols-4 gap-y-6 gap-x-2">
            <a href="{{ route('chat.index') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.chat-bubble-left-right class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">WhatsApp</span>
            </a>
        </div>
    </div>

    <!-- Keuangan -->
    @canany(['finance.dashboard.view', 'finance.inbox.view'])
    <div>
        <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('KEUANGAN') }}</h3>
        <div class="grid grid-cols-4 gap-y-6 gap-x-2">
            <a href="{{ route('finance.dashboard') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.banknotes class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Buku Kas</span>
            </a>
            
            <a href="{{ route('finance.inbox') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.inbox-arrow-down class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Validasi</span>
            </a>
            
            <a href="{{ route('finance.payables') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.credit-card class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Hutang</span>
            </a>
        </div>
    </div>
    @endcanany

    <!-- Pengaturan -->
    <div>
        <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('LAINNYA') }}</h3>
        <div class="grid grid-cols-4 gap-y-6 gap-x-2">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.home class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Utama</span>
            </a>

            <a href="{{ route('cms.posts.index') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.newspaper class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Artikel</span>
            </a>

            <a href="{{ route('settings.index') }}" wire:navigate class="flex flex-col items-center gap-2 group">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.cog-6-tooth class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">Pengaturan</span>
            </a>

            <!-- Mode Gelap Toggle -->
            <button class="flex flex-col items-center gap-2 group"
                x-data
                x-on:click="
                    let newTheme = $flux.dark ? 'light' : 'dark';
                    if (document.startViewTransition) {
                        document.startViewTransition(() => $flux.appearance = newTheme);
                    } else {
                        $flux.appearance = newTheme;
                    }
                ">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <flux:icon.moon x-show="!$flux.dark" class="size-6" />
                    <flux:icon.sun x-cloak x-show="$flux.dark" class="size-6" />
                </div>
                <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight" x-text="$flux.dark ? 'Mode Terang' : 'Mode Gelap'"></span>
            </button>
        </div>
    </div>
</div>
