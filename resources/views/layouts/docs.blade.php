<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} Docs</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        <style>
            /* Custom markdown styles since typography plugin is disabled */
            .markdown-body h1 { font-size: 2.25rem; font-weight: 800; margin-bottom: 1.5rem; letter-spacing: -0.025em; color: #111827; }
            .markdown-body h2 { font-size: 1.5rem; font-weight: 700; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem; color: #1f2937; }
            .markdown-body h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.75rem; color: #374151; }
            .markdown-body p { margin-bottom: 1.25rem; line-height: 1.75; color: #4b5563; }
            .markdown-body ul { list-style-type: disc; margin-left: 1.5rem; margin-bottom: 1.25rem; color: #4b5563; }
            .markdown-body ol { list-style-type: decimal; margin-left: 1.5rem; margin-bottom: 1.25rem; color: #4b5563; }
            .markdown-body li { margin-bottom: 0.5rem; line-height: 1.5; }
            .markdown-body strong { font-weight: 600; color: #111827; }
            .markdown-body blockquote { border-left: 4px solid #e5e7eb; padding-left: 1rem; color: #6b7280; font-style: italic; margin-bottom: 1.25rem; }
            .markdown-body code { background-color: #f3f4f6; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.875em; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: #ef4444; }
            .markdown-body pre { background-color: #1f2937; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin-bottom: 1.25rem; }
            .markdown-body pre code { background-color: transparent; color: #e5e7eb; padding: 0; border-radius: 0; }
            .markdown-body a { color: #3b82f6; text-decoration: none; }
            .markdown-body a:hover { text-decoration: underline; }
            .markdown-body hr { margin-top: 2rem; margin-bottom: 2rem; border: 0; border-top: 1px solid #e5e7eb; }
            
            .dark .markdown-body h1 { color: #f9fafb; }
            .dark .markdown-body h2 { border-bottom-color: #374151; color: #f3f4f6; }
            .dark .markdown-body h3 { color: #e5e7eb; }
            .dark .markdown-body p { color: #d1d5db; }
            .dark .markdown-body ul, .dark .markdown-body ol { color: #d1d5db; }
            .dark .markdown-body strong { color: #f9fafb; }
            .dark .markdown-body blockquote { border-left-color: #4b5563; color: #9ca3af; }
            .dark .markdown-body code { background-color: #374151; color: #f87171; }
            .dark .markdown-body pre { background-color: #111827; }
            .dark .markdown-body pre code { color: #d1d5db; }
            .dark .markdown-body hr { border-top-color: #374151; }
        </style>
    </head>
    <body class="bg-white dark:bg-zinc-900 min-h-screen text-zinc-800 dark:text-zinc-200 antialiased font-sans">
        
        <!-- Navbar -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-zinc-900/80 backdrop-blur border-b border-zinc-200 dark:border-zinc-800 h-16 flex items-center px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4 w-full justify-between">
                <div class="flex items-center gap-2">
                    <!-- Mobile Sidebar Toggle -->
                    <button class="lg:hidden p-2 text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" onclick="document.getElementById('docs-sidebar').classList.toggle('-translate-x-full')">
                        <flux:icon.bars-3 class="w-6 h-6" />
                    </button>
                    <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-lg text-zinc-900 dark:text-white">
                        <flux:icon.book-open class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                        <span>Dokumentasi</span>
                    </a>
                </div>
                
                <div class="flex items-center gap-4">
                    <a href="{{ route('dashboard') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white transition-colors">Ke Aplikasi &rarr;</a>
                </div>
            </div>
        </header>

        <div class="max-w-8xl mx-auto flex w-full">
            <!-- Sidebar -->
            <aside id="docs-sidebar" class="fixed inset-y-0 left-0 pt-16 z-30 w-72 bg-zinc-50 dark:bg-zinc-900/50 border-r border-zinc-200 dark:border-zinc-800 transform -translate-x-full lg:translate-x-0 lg:static lg:block transition-transform duration-300 ease-in-out overflow-y-auto">
                <nav class="p-6">
                    {{ $sidebar ?? '' }}
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 min-w-0 py-10 px-4 sm:px-6 lg:px-8 xl:pr-24 lg:pl-12">
                {{ $slot }}
            </main>
        </div>
        
        <script type="module">
            import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';
            mermaid.initialize({ startOnLoad: false, theme: document.documentElement.classList.contains('dark') ? 'dark' : 'default' });
            
            document.addEventListener("DOMContentLoaded", function() {
                const elements = document.querySelectorAll('code.language-mermaid');
                const isDesktop = window.innerWidth >= 1024;
                
                elements.forEach((el) => {
                    let mermaidCode = el.textContent;
                    
                    // Fitur Auto-Orientation / 2-Mode:
                    // Jika layar besar (Desktop) dan BUKAN diagram Struktur Organisasi Murni (yang butuh ke bawah)
                    if (isDesktop && !mermaidCode.includes('classDef topLevel')) {
                        mermaidCode = mermaidCode.replace(/^graph TD/gm, 'graph LR');
                    } 
                    // Jika layar kecil (Mobile), paksa semua diagram menurun dari atas ke bawah
                    else if (!isDesktop) {
                        mermaidCode = mermaidCode.replace(/^graph LR/gm, 'graph TD');
                    }
                    
                    const div = document.createElement('div');
                    div.className = 'mermaid flex justify-center w-full my-8 overflow-x-auto';
                    div.textContent = mermaidCode;
                    el.parentNode.replaceWith(div);
                });
                mermaid.run();
            });
        </script>
    </body>
</html>
