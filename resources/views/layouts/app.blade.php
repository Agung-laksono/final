@php
    // Cek apakah direquest di dalam iframe secara eksplisit atau via browser header
    $isIframe = request()->query('iframe') || request()->header('X-Is-Iframe') || request()->header('Sec-Fetch-Dest') === 'iframe';
    $layoutMode = $isIframe ? 'iframe' : request()->cookie('layout_mode', 'floating'); // Options: 'sidebar', 'floating', 'gesture'
@endphp

@if($layoutMode === 'iframe')
    <x-layouts::app.iframe :title="$title ?? null">
        {{ $slot }}
    </x-layouts::app.iframe>
@elseif($layoutMode === 'floating')
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
