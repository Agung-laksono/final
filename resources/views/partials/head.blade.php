<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<!-- PWA Meta Tags -->
<link rel="manifest" href="{{ route('pwa.manifest') }}">
@php
    $themeColors = \Illuminate\Support\Facades\Cache::rememberForever('setting_pwa_theme_colors', function () {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) return ['light' => '#ffffff', 'dark' => '#18181b'];
        return [
            'light' => \App\Models\Setting::where('key', 'pwa_theme_color_light')->value('value') ?? '#ffffff',
            'dark'  => \App\Models\Setting::where('key', 'pwa_theme_color_dark')->value('value') ?? '#18181b'
        ];
    });
@endphp
<meta name="theme-color" id="meta-theme-color" content="{{ $themeColors['light'] }}">
<script>
    (function() {
        const lightColor = '{{ $themeColors['light'] }}';
        const darkColor = '{{ $themeColors['dark'] }}';
        
        function updateThemeColor() {
            const isDark = document.documentElement.classList.contains('dark');
            document.getElementById('meta-theme-color').setAttribute('content', isDark ? darkColor : lightColor);
        }

        // Update when DOM is ready
        document.addEventListener('DOMContentLoaded', updateThemeColor);
        
        // Observe changes to the 'dark' class on the html element
        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        
        // Initial run
        updateThemeColor();
    })();
</script>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Inventory">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<!-- Library Cetak Label QR Code (Frontend) -->
<script src="{{ asset('js/qrcode.min.js') }}"></script>

<!-- PRE-LOAD TinyMCE Skin CSS: Harus dimuat di sini agar tidak ada CSS injection
     saat runtime yang bisa memicu browser reflow dan merusak layout Kanban. -->
<link rel="stylesheet" href="{{ asset('vendor/tinymce/skins/ui/oxide/skin.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/tinymce/skins/ui/oxide-dark/skin.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/tinymce/skins/ui/oxide/content.min.css') }}">

@php
    $clarityId = Illuminate\Support\Facades\Cache::rememberForever('setting_clarity_id', function () {
        // Safe check in case the table doesn't exist yet during initial deployment
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) return null;
        return \App\Models\Setting::where('key', 'clarity_id')->value('value');
    });
@endphp

@if(!empty($clarityId))
<!-- Microsoft Clarity Analytics -->
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "{{ $clarityId }}");
</script>

@auth
<script type="text/javascript">
    // Menyematkan identitas staf ke rekaman video
    clarity("set", "User Name", "{{ auth()->user()->name }}");
    clarity("set", "User Email", "{{ auth()->user()->email }}");
    clarity("identify", "{{ auth()->user()->email }}");
</script>
@endauth
@endif
