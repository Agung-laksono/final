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
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.floating>
@elseif($layoutMode === 'dock')
    <x-layouts::app.dock :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.dock>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif

{{-- Global Image Lightbox Component --}}
<x-image-lightbox />

<script>
    document.addEventListener('livewire:navigated', () => {
        applyMobileCardLabels();
    });
    document.addEventListener('DOMContentLoaded', () => {
        applyMobileCardLabels();
    });

    // Run when Flux Modals are opened, since modals load DOM elements dynamically
    document.addEventListener('alpine:init', () => {
        // We can just set an interval or observer, but MutationObserver is best
        const observer = new MutationObserver((mutations) => {
            let shouldApply = false;
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length > 0) {
                    shouldApply = true;
                }
            });
            if (shouldApply) applyMobileCardLabels();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });

    function applyMobileCardLabels() {
        document.querySelectorAll('table.table-mobile-cards').forEach(table => {
            if (table.hasAttribute('data-labels-applied')) return;
            
            let headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent.trim());
            });
            
            table.querySelectorAll('tbody tr').forEach(tr => {
                tr.querySelectorAll('td').forEach((td, index) => {
                    if(headers[index] && !td.hasAttribute('data-label')) {
                        td.setAttribute('data-label', headers[index]);
                    }
                });
            });
            table.setAttribute('data-labels-applied', 'true');
        });
    }
</script>
