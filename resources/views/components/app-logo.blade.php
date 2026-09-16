@php
    $appName = \Illuminate\Support\Facades\Cache::rememberForever('setting_pwa_name', function () {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) return config('app.name', 'Laravel');
        return \App\Models\Setting::where('key', 'pwa_name')->value('value') ?? config('app.name', 'Laravel');
    });

    $pwaIconPath = \Illuminate\Support\Facades\Cache::rememberForever('setting_pwa_icon', function () {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) return null;
        return \App\Models\Setting::where('key', 'pwa_icon')->value('value');
    });
    $appIconUrl = $pwaIconPath ? asset('storage/' . $pwaIconPath) : null;
@endphp

@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="{{ $appName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
            @if($appIconUrl)
                <img src="{{ $appIconUrl }}" alt="{{ $appName }}" class="w-full h-full object-cover">
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $appName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
            @if($appIconUrl)
                <img src="{{ $appIconUrl }}" alt="{{ $appName }}" class="w-full h-full object-cover">
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
