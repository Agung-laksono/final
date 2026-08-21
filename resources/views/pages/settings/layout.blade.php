<style>
    @media (min-width: 768px) {
        .settings-sidebar {
            width: 220px !important;
            flex-shrink: 0;
        }
    }
</style>
<div class="flex items-start max-md:flex-col md:gap-8">

    {{-- NAV: Desktop = sidebar vertikal | Mobile = horizontal scrollable pill tabs --}}
    <div class="settings-sidebar w-full">

        {{-- Desktop nav --}}
        <div class="hidden md:block">
            <flux:navlist aria-label="{{ __('Settings') }}">
                @can('profile.view')
                <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profil') }}</flux:navlist.item>
                @endcan
                <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Keamanan') }}</flux:navlist.item>
                <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Tampilan') }}</flux:navlist.item>
                @can('users.view')
                    <flux:navlist.item :href="route('settings.users')" wire:navigate>{{ __('Users & Roles') }}</flux:navlist.item>
                @endcan
                @role('Super Admin')
                    <flux:navlist.item :href="route('settings.navigation')" :current="request()->routeIs('settings.navigation')" wire:navigate>{{ __('Nav Icons') }}</flux:navlist.item>
                    <flux:navlist.item :href="route('settings.integrations')" wire:navigate>{{ __('Integrasi') }}</flux:navlist.item>
                    <flux:navlist.item :href="route('settings.workflow')" :current="request()->routeIs('settings.workflow')" wire:navigate>Workflow</flux:navlist.item>
                    <flux:navlist.item :href="route('settings.brands')" wire:navigate>{{ __('Brands') }}</flux:navlist.item>
                    <flux:navlist.item :href="route('settings.pwa')" wire:navigate>PWA</flux:navlist.item>
                    <flux:navlist.item :href="route('settings.system')" wire:navigate class="text-red-500 hover:text-red-600 dark:text-red-400">System & Reset</flux:navlist.item>
                @endrole
            </flux:navlist>
        </div>

        {{-- Mobile nav: horizontal scrollable pill tabs --}}
        <div class="md:hidden w-full -mx-4 px-4 overflow-x-auto" style="scrollbar-width:none;-ms-overflow-style:none;">
            <div class="flex items-center gap-1.5 pb-2 w-max">
                @can('profile.view')
                <a href="{{ route('profile.edit') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('profile.edit') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                    Profil
                </a>
                @endcan
                <a href="{{ route('security.edit') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('security.edit') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                    Keamanan
                </a>
                <a href="{{ route('appearance.edit') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('appearance.edit') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z"/></svg>
                    Tampilan
                </a>
                @can('users.view')
                <a href="{{ route('settings.users') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('settings.users') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                    Users & Roles
                </a>
                @endcan
                @role('Super Admin')
                <a href="{{ route('settings.navigation') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('settings.navigation') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
                    Nav Icons
                </a>
                <a href="{{ route('settings.integrations') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('settings.integrations') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                    Integrasi
                </a>
                <a href="{{ route('settings.workflow') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('settings.workflow') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                    Workflow
                </a>
                <a href="{{ route('settings.brands') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('settings.brands') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                    Brands
                </a>
                <a href="{{ route('settings.pwa') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('settings.pwa') ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                    PWA
                </a>
                <a href="{{ route('settings.system') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-all {{ request()->routeIs('settings.system') ? 'bg-red-600 text-white' : 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    System
                </a>
                @endrole
            </div>
        </div>

    </div>

    {{-- Divider (mobile only) --}}
    <div class="md:hidden w-full h-px bg-zinc-200 dark:bg-zinc-700 my-3"></div>

    {{-- Content Area --}}
    <div class="flex-1 min-w-0 w-full">
        @if($heading ?? false)
        <div class="mb-5">
            <flux:heading>{{ $heading }}</flux:heading>
            <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>
        </div>
        @endif
        <div class="w-full">
            {{ $slot }}
        </div>
    </div>

</div>
