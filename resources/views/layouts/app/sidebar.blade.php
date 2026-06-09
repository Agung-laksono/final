<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 transition-all duration-300 ease-in-out">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <div class="in-data-flux-sidebar-collapsed-desktop:hidden px-3 py-2 text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                    {{ __('Platform') }}
                </div>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
            
            <flux:sidebar.nav class="mt-4">
                <div class="in-data-flux-sidebar-collapsed-desktop:hidden px-3 py-2 text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                    {{ __('INVENTORY') }}
                </div>
                    @can('inventory.item.view')
                    <flux:sidebar.item icon="cube" :href="route('inventory')" :current="request()->routeIs('inventory')" wire:navigate>
                        {{ __('Barang') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.warehouse.view')
                    <flux:sidebar.item icon="building-storefront" :href="route('inventory.warehouses')" :current="request()->routeIs('inventory.warehouses')" wire:navigate>
                        {{ __('Gudang') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.transfer.view')
                    <flux:sidebar.item icon="arrows-right-left" :href="route('inventory.transfers')" :current="request()->routeIs('inventory.transfers*')" wire:navigate>
                        {{ __('Transfer Barang') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.movement.view')
                    <flux:sidebar.item icon="clock" :href="route('inventory.movements')" :current="request()->routeIs('inventory.movements*')" wire:navigate>
                        {{ __('Riwayat Mutasi') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.opname.view')
                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('inventory.stock-opname')" :current="request()->routeIs('inventory.stock-opname')" wire:navigate>
                        {{ __('Opname') }}
                    </flux:sidebar.item>
                    @endcan
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('inventory.settings')" :current="request()->routeIs('inventory.settings')" wire:navigate>
                        {{ __('Pengaturan') }}
                    </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item 
                    icon="arrows-pointing-out" 
                    class="cursor-pointer"
                    x-data 
                    x-on:click="
                        if (!document.fullscreenElement) {
                            document.documentElement.requestFullscreen();
                        } else {
                            if (document.exitFullscreen) {
                                document.exitFullscreen();
                            }
                        }
                    "
                >
                    {{ __('Layar Penuh') }}
                </flux:sidebar.item>

                <flux:sidebar.item 
                    icon="moon" 
                    class="cursor-pointer"
                    x-data 
                    x-on:click="
                        const toggleTheme = () => {
                            document.documentElement.classList.toggle('dark');
                            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                        };
                        
                        if (!document.startViewTransition) {
                            toggleTheme();
                        } else {
                            document.startViewTransition(() => toggleTheme());
                        }
                    "
                >
                    {{ __('Mode Gelap') }}
                </flux:sidebar.item>
                
                <livewire:layout.notification-bell />
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.index')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
