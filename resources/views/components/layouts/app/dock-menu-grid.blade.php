<div id="drawer-menu-container" class="px-4 pb-20 pt-2 space-y-8">

    <!-- Profil & Notifikasi (Minimalis) -->
    <div x-data="{ showNotifs: false }">
        <div class="flex items-center justify-between px-1 mb-2">
            <div class="flex items-center gap-2.5">
                <flux:avatar size="sm" :name="auth()->user()->name" :src="auth()->user()->avatarUrl()" class="w-8 h-8 text-xs" />
                <span class="text-[13px] font-medium text-zinc-700 dark:text-zinc-300">{{ auth()->user()->name }}</span>
            </div>
            <div class="flex items-center gap-1">
                {{-- Bell toggle button --}}
                @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                <button @click="showNotifs = !showNotifs" class="relative flex items-center gap-1.5 px-2 py-1.5 rounded-lg text-zinc-400 transition-colors" :class="showNotifs ? 'text-blue-500 bg-blue-50 dark:bg-blue-500/10' : 'hover:text-zinc-600 dark:hover:text-zinc-200'">
                    <flux:icon.bell class="w-[16px] h-[16px]" />
                    <span class="text-[12px] font-medium">Notifikasi</span>
                    @if($unread > 0)
                        <span class="w-2 h-2 bg-blue-500 rounded-full border-2 border-zinc-50 dark:border-zinc-900"></span>
                    @endif
                </button>

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" title="Keluar" class="p-2 text-zinc-400 hover:text-red-500 transition-colors">
                        <flux:icon.arrow-right-start-on-rectangle class="w-[18px] h-[18px]" />
                    </button>
                </form>
            </div>
        </div>

        {{-- Notifikasi Inline (toggle) --}}
        <div x-show="showNotifs"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             x-cloak
             class="mb-2">
            <livewire:layout.dock-notification-strip />
        </div>
    </div>

    @php
        $navGroups = \App\Services\NavigationService::getGrouped();
        $sectionOrder = ['INVENTORY','PEMBELIAN','PRODUKSI','PENJUALAN','KOMUNIKASI','KEUANGAN'];
        $sectionPermissions = [
            'INVENTORY'   => 'inventory.view',
            'PEMBELIAN'   => 'purchase.dashboard.view',
            'PRODUKSI'    => 'production.dashboard.view',
            'PENJUALAN'   => 'sales.dashboard.view',
            'KOMUNIKASI'  => null,
            'KEUANGAN'    => null,
        ];
        $badgeItems = [
            'inventory.transfers'    => 'inventory_transfer',
            'inventory.requests'     => 'inventory_request',
            'purchase.queues.kanban' => 'purchase_queue',
            'purchase.orders.kanban' => 'purchase_order',
            'production.orders'      => 'production_order',
            'sales.orders.index'     => 'sales_order',
            'finance.payables'       => 'finance_payables',
            'finance.inbox'          => 'finance_inbox',
            'finance.transfers'      => 'finance_transfer',
        ];
    @endphp

    @foreach($sectionOrder as $section)
        @if(isset($navGroups[$section]) && count($navGroups[$section]) > 0)
            @php
                $items = $navGroups[$section];
                $sectionPerm = $sectionPermissions[$section] ?? null;
            @endphp
            @if(!$sectionPerm || auth()->user()->can($sectionPerm))

            <div>
                <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __($section) }}</h3>
                <div class="grid grid-cols-4 md:grid-cols-6 xl:grid-cols-8 gap-y-6 gap-x-2">
                    @foreach($items as $item)
                        @php
                            $routeExists = \Illuminate\Support\Facades\Route::has($item['route_name']);
                            $hasBadge = isset($badgeItems[$item['route_name']]);
                        @endphp
                        @if($routeExists)
                            @if(!$item['permission'] || auth()->user()->can($item['permission']))
                            
                            <a href="{{ route($item['route_name']) }}" data-drawer-url="{{ route($item['route_name'], [], false) }}" wire:navigate class="flex flex-col items-center gap-2 md:gap-3 group">
                                <div class="w-12 h-12 md:w-14 md:h-14 xl:w-16 xl:h-16 rounded-2xl md:rounded-[1.25rem] xl:rounded-3xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                    @if($hasBadge)
                                        <div class="relative">
                                            @if(($item['icon_type'] ?? 'flux') === 'image' && $item['image_path'])
                                                <img src="{{ Storage::url($item['image_path']) }}" class="size-6 md:size-7 xl:size-8 object-contain group-hover:scale-110 transition-transform duration-300 group-hover:-rotate-3 group-active:scale-95" />
                                            @else
                                                <flux:icon :icon="$item['icon']" class="size-6 md:size-7 xl:size-8 group-hover:scale-110 transition-transform duration-300 group-hover:-rotate-3 group-active:scale-95" />
                                            @endif
                                            <livewire:layout.sidebar-badge :type="$badgeItems[$item['route_name']]" />
                                        </div>
                                    @else
                                        @if(($item['icon_type'] ?? 'flux') === 'image' && $item['image_path'])
                                            <img src="{{ Storage::url($item['image_path']) }}" class="size-6 md:size-7 xl:size-8 object-contain group-hover:scale-110 transition-transform duration-300 group-hover:-rotate-3 group-active:scale-95" />
                                        @else
                                            <flux:icon :icon="$item['icon']" class="size-6 md:size-7 xl:size-8 group-hover:scale-110 transition-transform duration-300 group-hover:-rotate-3 group-active:scale-95" />
                                        @endif
                                    @endif
                                </div>
                                <span class="text-[10px] md:text-xs xl:text-[13px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">{{ __($item['label']) }}</span>
                            </a>

                            @endif
                        @endif
                    @endforeach
                </div>
            </div>

            @endif
        @endif
    @endforeach

    <!-- LAINNYA & Pengaturan -->
    @if(isset($navGroups['LAINNYA']))
    <div>
        <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('LAINNYA') }}</h3>
        <div class="grid grid-cols-4 gap-y-6 gap-x-2">
            @foreach($navGroups['LAINNYA'] as $item)
                @php $routeExists = \Illuminate\Support\Facades\Route::has($item['route_name']); @endphp
                @if($routeExists)
                    <a href="{{ route($item['route_name']) }}" data-drawer-url="{{ route($item['route_name'], [], false) }}" wire:navigate class="flex flex-col items-center gap-2 group">
                        <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                            @if(($item['icon_type'] ?? 'flux') === 'image' && $item['image_path'])
                                <img src="{{ Storage::url($item['image_path']) }}" class="size-6 object-contain group-hover:scale-110 transition-transform duration-300 group-hover:-rotate-3 group-active:scale-95" />
                            @else
                                <flux:icon :icon="$item['icon']" class="size-6 group-hover:scale-110 transition-transform duration-300 group-hover:-rotate-3 group-active:scale-95" />
                            @endif
                        </div>
                        <span class="text-[10px] font-medium text-center text-zinc-600 dark:text-zinc-400 leading-tight">{{ __($item['label']) }}</span>
                    </a>
                @endif
            @endforeach

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
    @endif
</div>

