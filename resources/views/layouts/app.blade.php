@php
    // Anda bisa menghubungkan ini ke database/session (User Preferences) di masa depan.
    $layoutMode = request()->cookie('layout_mode', 'floating'); // Options: 'sidebar', 'floating'
@endphp

@if($layoutMode === 'floating')
    <x-layouts::app.floating :title="$title ?? null">
        <flux:main class="p-0 sm:p-6 lg:p-8">
            {{ $slot }}
        </flux:main>
    </x-layouts::app.floating>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif
