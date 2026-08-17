@php
    // Cek apakah direquest di dalam iframe secara eksplisit atau via browser header
    $isIframe = request()->query('iframe') || request()->header('X-Is-Iframe') || request()->header('Sec-Fetch-Dest') === 'iframe';
    $layoutMode = $isIframe ? 'iframe' : request()->cookie('layout_mode', 'floating'); // Options: 'sidebar', 'floating', 'dock'
@endphp

@if($layoutMode === 'iframe')
    <x-layouts::app.iframe :title="$title ?? null">
        {{ $slot }}
    </x-layouts::app.iframe>
@elseif($layoutMode === 'floating')
    <x-layouts::app.floating :title="$title ?? null">
        <flux:main class="max-sm:!px-4 max-sm:!py-6">
            {{ $slot }}
        </flux:main>
    </x-layouts::app.floating>
@elseif($layoutMode === 'dock')
    <x-layouts::app.dock :title="$title ?? null">
        <flux:main class="max-sm:!px-4 max-sm:!py-6">
            {{ $slot }}
        </flux:main>
    </x-layouts::app.dock>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main class="max-sm:!px-4 max-sm:!py-6">
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif

{{-- Global Image Lightbox Component --}}
<x-image-lightbox />

{{-- Global Contextual Help Button --}}
<livewire:contextual-help />

{{-- Global Barcode Scanner --}}
<div x-data @barcode-scanned.window="if (window.Livewire) { window.Livewire.dispatch('barcode-scanned', { code: $event.detail.code }); }"></div>
<x-camera-scanner />

</script>
