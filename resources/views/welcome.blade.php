<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Beranda - {{ config('app.name', 'Romlah ERP') }}</title>

    @php
        $pwaIconPath = \Illuminate\Support\Facades\Cache::rememberForever('setting_pwa_icon', function () {
            if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) return null;
            return \App\Models\Setting::where('key', 'pwa_icon')->value('value');
        });
        $appIconUrl = $pwaIconPath ? asset('storage/' . $pwaIconPath) : null;
    @endphp

    @if($appIconUrl)
        <link rel="icon" href="{{ $appIconUrl }}">
        <link rel="apple-touch-icon" href="{{ $appIconUrl }}">
    @else
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @endif

    <!-- Memuat Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Memuat Google Fonts: Outfit untuk Heading dan Inter untuk Body -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Konfigurasi kustom Tailwind -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            blue: '#2b4c65',
                            dark: '#1e3547',
                            primary: '#3b82f6',
                            accent: '#6366f1'
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'blob': 'blob 7s infinite',
                        'fade-in-up': 'fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-15px)' },
                        },
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        .page-exit {
            animation: pageExit 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            pointer-events: none;
        }
        @keyframes pageExit {
            to {
                opacity: 0;
                transform: scale(0.97) translateY(-15px);
            }
        }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }

        /* Grid Background pattern */
        .bg-grid-pattern {
            background-image: linear-gradient(to right, rgba(0,0,0,0.05) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(0,0,0,0.05) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .dark .bg-grid-pattern {
            background-image: linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px);
        }
    </style>
    <script>
        // Sinkronisasi Dark Mode mengikuti tema utama sistem / aplikasi (localStorage)
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>

<body class="bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 font-sans antialiased selection:bg-brand-primary selection:text-white min-h-screen overflow-x-hidden transition-colors duration-300 flex flex-col">

    <!-- Background Decoration -->
    <div class="fixed inset-0 z-0 pointer-events-none overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern opacity-50 [mask-image:radial-gradient(ellipse_at_center,black,transparent_80%)]"></div>
        <!-- Blobs -->
        <div class="absolute top-0 -left-4 w-72 h-72 bg-purple-300 dark:bg-purple-900/40 rounded-full mix-blend-multiply dark:mix-blend-overlay filter blur-xl opacity-70 animate-blob"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-blue-300 dark:bg-blue-900/40 rounded-full mix-blend-multiply dark:mix-blend-overlay filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-emerald-300 dark:bg-emerald-900/40 rounded-full mix-blend-multiply dark:mix-blend-overlay filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
    </div>

    <!-- Navigation -->
    <nav class="relative z-50 w-full py-4 sm:py-6 px-6 lg:px-12 flex justify-between items-center opacity-0 animate-fade-in-up shrink-0">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo-icon.webp') }}" alt="Logo" class="h-8 sm:h-10 object-contain drop-shadow-sm">
            <span class="font-heading font-bold text-xl tracking-tight text-slate-900 dark:text-white">Romlah ERP</span>
        </div>
        <div class="flex gap-4">
            <button onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light')" class="p-2.5 rounded-full bg-white/50 dark:bg-slate-800/50 backdrop-blur-sm border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <!-- Sun Icon -->
                <svg class="w-5 h-5 hidden dark:block text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <!-- Moon Icon -->
                <svg class="w-5 h-5 block dark:hidden text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
            <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-full font-semibold text-sm bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:bg-slate-800 dark:hover:bg-slate-100 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                Masuk
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="relative z-10 w-full max-w-7xl mx-auto px-6 lg:px-12 pt-4 sm:pt-8 pb-12 flex-grow flex flex-col lg:flex-row items-center justify-between gap-12 lg:gap-8">
        
        <!-- Text Section -->
        <div class="w-full lg:w-1/2 flex flex-col justify-center text-center lg:text-left z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium text-xs sm:text-sm mb-6 mx-auto lg:mx-0 opacity-0 animate-fade-in-up delay-100 w-max border border-blue-200 dark:border-blue-800/50 backdrop-blur-md">
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-500 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                </span>
                Sistem ERP Mebel Generasi Baru
            </div>
            
            <h1 class="font-heading text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-slate-900 dark:text-white leading-[1.1] mb-6 opacity-0 animate-fade-in-up delay-200">
                Romlah ERP <br class="hidden sm:block"> 
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600 dark:from-blue-400 dark:to-purple-400">
                    untuk Usaha Mebel
                </span> 
                Anda.
            </h1>
            
            <p class="text-base sm:text-lg text-slate-600 dark:text-slate-400 mb-10 max-w-2xl mx-auto lg:mx-0 opacity-0 animate-fade-in-up delay-300 leading-relaxed">
                Kendalikan <span class="font-semibold text-blue-600 dark:text-blue-400">Produksi</span>, <span class="font-semibold text-indigo-600 dark:text-indigo-400">Manajemen Stok</span>, <span class="font-semibold text-emerald-600 dark:text-emerald-400">Payroll</span>, <span class="font-semibold text-amber-600 dark:text-amber-400">Keuangan</span>, <span class="font-semibold text-rose-600 dark:text-rose-400">Marketing</span>, hingga <span class="font-semibold text-purple-600 dark:text-purple-400">Penjualan</span> cukup dalam satu genggaman.
            </p>
            
            <div class="flex flex-col items-center lg:items-start gap-4 mx-auto lg:mx-0 opacity-0 animate-fade-in-up delay-400">
                <span class="text-sm sm:text-base font-bold text-slate-800 drop-shadow-[0_0_8px_rgba(0,0,0,0.2)] dark:text-white dark:drop-shadow-[0_0_12px_rgba(255,255,255,0.8)] tracking-wide -mb-1">Tingkatkan efisiensi bisnis Anda sekarang.</span>
                <a href="{{ route('login') }}" 
                   onclick="event.preventDefault(); let btn=this, txt=btn.querySelector('.btn-text'), spn=btn.querySelector('.spinner'); txt.classList.add('opacity-0'); spn.classList.remove('hidden'); btn.classList.add('pointer-events-none', 'scale-95'); document.body.classList.add('page-exit'); setTimeout(()=>{ window.location.href = btn.href; }, 500); setTimeout(()=>{ txt.classList.remove('opacity-0'); spn.classList.add('hidden'); btn.classList.remove('pointer-events-none', 'scale-95'); document.body.classList.remove('page-exit'); }, 3000);"
                   class="group relative inline-flex items-center justify-center w-full sm:w-auto px-8 py-4 font-semibold text-white transition-all duration-300 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 rounded-full shadow-[0_0_20px_rgba(79,70,229,0.3)] hover:shadow-[0_0_30px_rgba(79,70,229,0.5)] hover:-translate-y-1">
                    <span class="btn-text flex items-center gap-2 transition-opacity duration-200">
                        Masuk Dashboard
                        <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </span>
                    <!-- Spinner SVG -->
                    <svg class="spinner hidden absolute animate-spin h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </a>
            </div>
        </div>

        <!-- Illustration Section (Bento Grid) -->
        <div class="w-full lg:w-1/2 flex justify-center lg:justify-end z-10 opacity-0 animate-fade-in-up delay-300">
            <div class="grid grid-cols-2 gap-4 w-full max-w-lg lg:max-w-xl relative animate-float">
                <!-- Glowing backdrop -->
                <div class="absolute inset-0 bg-blue-500/20 dark:bg-purple-500/20 filter blur-[80px] rounded-full transform scale-90"></div>

                <!-- Bento Item 1: Produksi (Large Square) -->
                <div class="col-span-1 row-span-2 bg-white/70 dark:bg-slate-800/60 backdrop-blur-xl border border-white/40 dark:border-slate-700/50 p-6 rounded-3xl shadow-xl hover:-translate-y-2 transition-transform duration-300 group">
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center mb-6 text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-lg">Pusat Produksi</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 mb-4">Kendali mesin dan SPK Maklon secara real-time.</p>
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">98% Efisiensi</span>
                    </div>
                </div>

                <!-- Bento Item 2: Penjualan & Keuangan (Wide Rectangle) -->
                <div class="col-span-1 row-span-1 bg-white/70 dark:bg-slate-800/60 backdrop-blur-xl border border-white/40 dark:border-slate-700/50 p-5 rounded-3xl shadow-xl hover:-translate-y-1 transition-transform duration-300">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-bold text-slate-800 dark:text-white text-sm">Arus Kas</h3>
                        <span class="text-[10px] font-bold px-2 py-1 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 rounded-full">+24%</span>
                    </div>
                    <div class="font-heading font-bold text-2xl text-slate-900 dark:text-white">Rp 2.4 M</div>
                    <div class="w-full bg-slate-100 dark:bg-slate-700 h-1.5 rounded-full mt-3 overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-400 to-emerald-600 w-[75%] h-full rounded-full"></div>
                    </div>
                </div>

                <!-- Bento Item 3: Stok Barang (Square) -->
                <div class="col-span-1 row-span-1 bg-gradient-to-br from-indigo-500 to-purple-600 p-5 rounded-3xl shadow-xl text-white hover:-translate-y-1 transition-transform duration-300 relative overflow-hidden group">
                    <!-- Glass shine effect -->
                    <div class="absolute inset-0 bg-white/20 transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
                    <svg class="w-8 h-8 mb-3 text-indigo-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <h3 class="font-bold text-sm">Gudang & Stok</h3>
                    <p class="text-xs text-indigo-100 mt-1">4.200 Item Ready</p>
                </div>
                
                <!-- Bento Item 4: Floating Pill (Col Span 2) -->
                <div class="col-span-2 bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border border-white/50 dark:border-slate-700 p-4 rounded-full shadow-lg flex items-center justify-between px-6 hover:shadow-xl transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="font-medium text-sm text-slate-700 dark:text-slate-300">Menunggu ACC Finance</span>
                    </div>
                    <span class="flex items-center gap-1 text-xs font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-3 py-1 rounded-full">
                        3 PO Baru
                    </span>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer Section -->
    <footer class="w-full text-center py-6 mt-auto z-50 shrink-0">
        <p class="inline-block text-[11px] sm:text-xs font-medium text-slate-500 dark:text-slate-400">
            Powered by <span class="text-blue-600 dark:text-blue-400 font-bold">Jihan Digital</span> &copy; {{ date('Y') }}
        </p>
    </footer>

    <!-- Disable Back Button Script -->
    <script>
        history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function(event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            history.pushState(null, null, window.location.href);
        }, true);
    </script>
</body>
</html>
