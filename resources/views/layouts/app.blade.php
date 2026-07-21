@php
    // Anda bisa menghubungkan ini ke database/session (User Preferences) di masa depan.
    $layoutMode = request()->cookie('layout_mode', 'floating'); // Options: 'sidebar', 'floating'
@endphp

@if($layoutMode === 'floating')
    <x-layouts::app.floating :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.floating>
@elseif($layoutMode === 'gesture')
    <x-layouts::app.gesture :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.gesture>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif

{{-- Global Image Lightbox Component --}}
<x-image-lightbox />
