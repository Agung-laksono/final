<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Beranda - {{ config('app.name', 'Laravel') }}</title>

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

    <!-- Memuat Google Fonts: Lora untuk Heading (Serif) dan Inter untuk Body (Sans-serif) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Konfigurasi kustom Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Lora', 'serif'],
                    },
                    colors: {
                        brand: {
                            blue: '#2b4c65',
                            dark: '#1e3547'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        .fade-in {
            animation: fadeIn 0.8s ease-out forwards;
            opacity: 0;
            transform: translateY(10px);
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pageExit {
            to {
                opacity: 0;
                transform: scale(0.97) translateY(-15px);
            }
        }

        .page-exit {
            animation: pageExit 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            pointer-events: none;
        }

        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
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

<body class="bg-[#fcfdfd] dark:bg-gray-900 text-gray-800 dark:text-gray-200 font-sans antialiased selection:bg-brand-blue selection:text-white fixed inset-0 flex flex-col overflow-hidden transition-colors duration-300">

    <main class="flex-grow flex flex-col items-center px-4 sm:px-6 lg:px-8 w-full max-w-7xl mx-auto pb-10">
        <!-- Spacer Atas (Menyerap 30% sisa layar) -->
        <div style="flex-grow: 3;" class="w-full shrink-0"></div>

        <!-- Logo Section -->
        <header class="w-full flex justify-center mb-3 md:mb-5 fade-in shrink-0">
            <img src="{{ asset('images/logo.webp') }}" alt="Romlah ERP Logo" class="h-8 sm:h-10 md:h-12 object-contain drop-shadow-sm">
        </header>

        <!-- Heading Section -->
        <section class="relative z-10 text-center max-w-4xl mx-auto mb-2 md:mb-4 px-3 fade-in delay-100 shrink-0">
            <h1 class="font-serif text-2xl sm:text-4xl lg:text-5xl text-gray-900 dark:text-white leading-tight mb-2 tracking-tight drop-shadow-md transition-colors duration-300">
                Romlah ERP ✨ untuk Usaha Mebel Anda.
            </h1>
            <p class="text-sm sm:text-base lg:text-lg text-gray-800 dark:text-gray-300 font-semibold px-4 drop-shadow transition-colors duration-300">
                Kendalikan Produksi, Stok, Payroll, Keuangan, Marketing hingga Penjualan dalam Satu Genggaman.
            </p>
        </section>

        <!-- Content Area -->
        <section class="w-full flex flex-col items-center justify-center">
            <!-- Main Illustration Image -->
            <div class="w-full max-w-2xl md:max-w-4xl lg:max-w-5xl relative fade-in delay-200 flex flex-col items-center justify-center px-2 sm:px-4 -mt-10 sm:-mt-16 md:-mt-20 lg:-mt-28 z-0">
                <img src="{{ asset('images/romlah-erp.webp') }}" alt="Ilustrasi Proses Bisnis Mebel" class="w-full h-auto max-h-[55vh] md:max-h-[60vh] lg:max-h-[70vh] object-contain mx-auto mix-blend-multiply dark:mix-blend-normal dark:opacity-90 transition-all duration-300">
                
                <!-- Button Area (Pulled up into the image via negative margin) -->
                <div class="relative z-20 flex justify-center fade-in delay-300 w-full -mt-8 sm:-mt-12 md:-mt-16 lg:-mt-16 mb-4">
                    <a href="{{ route('login') }}" 
                       onclick="event.preventDefault(); let btn=this, txt=btn.querySelector('.btn-text'), spn=btn.querySelector('.spinner'); txt.classList.add('opacity-0'); spn.classList.remove('hidden'); btn.classList.add('pointer-events-none', 'opacity-90'); document.body.classList.add('page-exit'); setTimeout(()=>{ window.location.href = btn.href; }, 500); setTimeout(()=>{ txt.classList.remove('opacity-0'); spn.classList.add('hidden'); btn.classList.remove('pointer-events-none', 'opacity-90'); document.body.classList.remove('page-exit'); }, 3000);"
                       class="group relative inline-flex items-center justify-center px-8 sm:px-10 py-3 sm:py-3.5 font-semibold text-white transition-all duration-200 bg-gradient-to-b from-[#3a607f] to-[#203a55] border border-transparent rounded-full shadow-xl hover:shadow-2xl hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#203a55] dark:shadow-[0_0_20px_rgba(58,96,127,0.4)]">
                        <span class="btn-text tracking-wide text-sm sm:text-base transition-opacity duration-200">MASUK <span class="hidden xl:inline">SEKARANG</span></span>
                        <!-- Spinner SVG -->
                        <svg class="spinner hidden absolute animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div class="absolute inset-0 h-full w-full rounded-full bg-white opacity-0 transition-opacity duration-200 group-hover:opacity-10 pointer-events-none"></div>
                    </a>
                </div>
            </div>
        </section>

        <!-- Spacer Bawah (Menyerap 70% sisa layar) -->
        <div style="flex-grow: 7;" class="w-full shrink-0"></div>
    </main>

    <!-- Footer Section (Fixed Bottom) -->
    <footer class="fixed bottom-1 sm:bottom-2 left-0 w-full text-center py-2 text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 font-medium z-50 pointer-events-none transition-colors duration-300">
        <p class="pointer-events-auto inline-block bg-[#fcfdfd]/80 dark:bg-gray-900/80 px-2 rounded-full backdrop-blur-sm transition-colors duration-300">Powered by <span class="text-brand-blue dark:text-[#7ba9d1] font-semibold">Jihan Digital</span> | Romlah ERP {{ date('Y') }}</p>
    </footer>

    <!-- Disable Back Button Script -->
    <script>
        // Mencegah fungsi tombol back secara agresif (memaksa user menggunakan menu UI)
        history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function(event) {
            // Hentikan event agar tidak ditangkap oleh router browser
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            
            // Paksa tetap di URL saat ini
            history.pushState(null, null, window.location.href);
        }, true); // Gunakan fase capturing agar dieksekusi lebih dulu
    </script>
</body>
</html>
