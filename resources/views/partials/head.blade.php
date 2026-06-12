<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<!-- PWA Meta Tags -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#ffffff">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#18181b">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Inventory">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

<!-- Library Cetak Label QR Code (Frontend) -->
<script src="{{ asset('js/qrcode.min.js') }}"></script>

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
