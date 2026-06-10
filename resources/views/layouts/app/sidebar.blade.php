<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 transition-all duration-300 ease-in-out">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="#" wire:navigate />
                <flux:sidebar.collapse />
            </flux:sidebar.header>
            <livewire:layout.notification-bell class="hidden md:block" />

            <!-- Inventory -->
             @can('inventory.view')
            <flux:sidebar.nav>
                <div class="in-data-flux-sidebar-collapsed-desktop:hidden px-3 py-2 text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                    {{ __('INVENTORY') }}
                </div>
                </div>
                    <flux:sidebar.item icon="chart-pie" :href="route('inventory')" :current="request()->routeIs('inventory')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @can('inventory.item.view')
                    <flux:sidebar.item icon="cube" :href="route('inventory.items')" :current="request()->routeIs('inventory.items')" wire:navigate>
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
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('settings.index')" :current="request()->routeIs('settings.*') || request()->routeIs('profile.*') || request()->routeIs('security.*') || request()->routeIs('appearance.*')" wire:navigate>
                        {{ __('Pengaturan') }}
                    </flux:sidebar.item>
            </flux:sidebar.nav>
            @endcan
            <flux:sidebar.nav class="mt-4">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item 
                    class="cursor-pointer"
                    tooltip="Layar Penuh"
                    x-data="{ isFullscreen: false }"
                    x-on:fullscreenchange.document="isFullscreen = !!document.fullscreenElement"
                    x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"
                >
                    <x-slot:icon>
                        <flux:icon.arrows-pointing-out x-show="!isFullscreen" variant="outline" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                        <flux:icon.arrows-pointing-in x-cloak x-show="isFullscreen" variant="outline" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                    </x-slot:icon>

                    <span x-show="!isFullscreen">{{ __('Layar Penuh') }}</span>
                    <span x-cloak x-show="isFullscreen">{{ __('Keluar Layar Penuh') }}</span>
                </flux:sidebar.item>

                <flux:sidebar.item 
                    class="cursor-pointer"
                    tooltip="Ganti Tema"
                    x-on:click="
                        let newTheme = $flux.dark ? 'light' : 'dark';
                        if (document.startViewTransition) {
                            document.startViewTransition(() => $flux.appearance = newTheme);
                        } else {
                            $flux.appearance = newTheme;
                        }
                    "
                >
                    <x-slot:icon>
                        <flux:icon.moon x-show="!$flux.dark" variant="outline" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                        <flux:icon.sun x-cloak x-show="$flux.dark" variant="outline" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                    </x-slot:icon>
                    
                    <span x-show="!$flux.dark">{{ __('Mode Gelap') }}</span>
                    <span x-cloak x-show="$flux.dark">{{ __('Mode Terang') }}</span>
                </flux:sidebar.item>
            </flux:sidebar.nav>
            <div class="hidden md:block">
                <x-desktop-user-menu :name="auth()->user()->name" />
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="md:hidden">
            <flux:sidebar.toggle class="md:hidden" icon="bars-2" inset="left" />

            <flux:spacer />
            <livewire:layout.notification-bell />
            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    :avatar="auth()->user()->avatarUrl()"
                    icon-trailing="chevron-down"
                />
                <flux:menu>
                  <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                    :src="auth()->user()->avatarUrl()"
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
        @stack('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.hook('request', ({ options }) => {
                    options.headers['X-Tab-Focused'] = document.hasFocus() ? '1' : '0';
                    options.headers['X-Current-Path'] = window.location.pathname;
                });
            });
        </script>
    </body>
</html>
