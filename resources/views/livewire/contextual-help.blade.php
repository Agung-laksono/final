<?php

use function Livewire\Volt\{state, mount, on};
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\CMS\Models\CmsPost;

state([
    'currentRoute' => '',
    'expectedSlug' => '',
    'post' => null,
    'show' => false,
]);

mount(function () {
    $this->currentRoute = Route::currentRouteName();
    
    // Jangan tampilkan tombol bantuan di halaman CMS Edit atau Create itu sendiri
    if (Str::startsWith($this->currentRoute, 'cms.posts')) {
        $this->expectedSlug = '';
        return;
    }

    if ($this->currentRoute) {
        // Contoh: sales.orders.create -> panduan-sales-orders-create
        $this->expectedSlug = 'panduan-' . Str::slug(str_replace('.', '-', $this->currentRoute));
        $this->post = CmsPost::where('slug', $this->expectedSlug)
                             ->where('status', 'published')
                             ->first();
    }
});

$toggleHelp = function () {
    if ($this->post) {
        $this->show = true;
    } else {
        // Jika tidak ada post, periksa apakah user bisa buat
        if (auth()->user()->can('cms.post.create') || auth()->user()->hasRole('Super Admin')) {
            $this->redirect(route('cms.posts.create', ['slug' => $this->expectedSlug, 'title' => 'Panduan: ' . ucwords(str_replace(['.', '-'], ' ', $this->currentRoute))]), navigate: true);
        } else {
            \Flux::toast('Panduan untuk halaman ini belum tersedia.', variant: 'warning');
        }
    }
};

?>

<div>
    @if($expectedSlug)
        <div x-data="{ 
                showButton: localStorage.getItem('hideHelpForever') !== 'true',
                showMenu: false,
                hideTemporarily() {
                    this.showButton = false;
                    this.showMenu = false;
                },
                hideForever() {
                    localStorage.setItem('hideHelpForever', 'true');
                    this.showButton = false;
                    this.showMenu = false;
                    alert('Tombol Bantuan Pintar telah dinonaktifkan permanen di perangkat ini. (Hapus data cache browser jika ingin mengembalikannya).');
                }
             }" 
             x-show="showButton"
             @click.away="showMenu = false"
             class="fixed bottom-6 right-6 z-50 flex flex-col items-end">
            
            <!-- Context Menu -->
            <div x-show="showMenu" 
                 x-transition
                 x-cloak
                 class="mb-3 w-56 bg-white dark:bg-zinc-800 rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden origin-bottom-right">
                <div class="px-3 py-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-100 dark:border-zinc-800">
                    Pengaturan Bantuan
                </div>
                <button @click="hideTemporarily" class="w-full text-left px-4 py-3 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors flex items-center gap-3 border-b border-zinc-100 dark:border-zinc-800">
                    <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Sembunyikan Sementara
                </button>
                <button @click="hideForever" class="w-full text-left px-4 py-3 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors flex items-center gap-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                    Jangan Tampilkan Lagi
                </button>
            </div>

            <!-- Main Button with Context Menu Trigger -->
            <div @contextmenu.prevent="showMenu = true" class="relative">
                @if($post)
                    <flux:button wire:click="toggleHelp" variant="primary" class="!rounded-full !w-12 !h-12 !p-0 shadow-lg hover:shadow-xl transition-all" tooltip="Bantuan Halaman Ini">
                        <flux:icon.question-mark-circle class="w-7 h-7" />
                    </flux:button>
                @else
                    @if(auth()->user()->can('cms.post.create') || auth()->user()->hasRole('Super Admin'))
                        <flux:button wire:click="toggleHelp" variant="subtle" class="!rounded-full !w-12 !h-12 !p-0 shadow opacity-50 hover:opacity-100 transition-all border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800" tooltip="Buat Panduan Halaman Ini">
                            <flux:icon.question-mark-circle class="w-7 h-7 text-zinc-400 dark:text-zinc-500 hover:text-indigo-500" />
                        </flux:button>
                    @endif
                @endif
            </div>
        </div>

        <!-- Custom Alpine Modal yang dijamin 100% bisa ditutup -->
        <div x-data="{ open: @entangle('show') }" x-cloak>
            <!-- Backdrop -->
            <div x-show="open" 
                 x-transition.opacity.duration.300ms
                 @click="open = false"
                 class="fixed inset-0 bg-zinc-900/60 backdrop-blur-sm z-[9998]">
            </div>
            
            <!-- Modal Container -->
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-full md:translate-y-0 md:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 md:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 md:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-full md:translate-y-0 md:scale-95"
                 class="fixed inset-0 z-[9999] flex items-end md:items-center justify-center p-0 md:p-4 pointer-events-none">
                 
                <!-- Modal Panel -->
                <div class="bg-white dark:bg-zinc-900 w-full md:w-[80vw] lg:w-[1000px] xl:w-[1200px] h-[100dvh] md:h-auto max-h-[100dvh] md:max-h-[90vh] overflow-y-auto rounded-none md:rounded-2xl shadow-2xl relative pointer-events-auto md:border border-zinc-200 dark:border-zinc-800 flex flex-col">
                    
                    <!-- Tombol Silang (X) di Kanan Atas -->
                    <button @click="open = false" class="absolute top-4 right-4 md:top-6 md:right-6 text-zinc-400 hover:text-red-500 transition-colors p-2 bg-zinc-100 dark:bg-zinc-800 rounded-full z-10">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>

                    @if($post)
                        <div class="p-6 md:p-8">
                            <div class="flex items-start gap-4 border-b border-zinc-200 dark:border-zinc-700 pb-5 mb-6">
                                <div class="flex-shrink-0 w-14 h-14 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                </div>
                                <div class="flex-1 pr-8">
                                    <h2 class="text-2xl md:text-3xl font-bold text-zinc-900 dark:text-white">{{ $post->title }}</h2>
                                    <div class="flex items-center gap-2 mt-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">Panduan</span>
                                        @if($post->category)
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $post->category->name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="prose prose-base md:prose-lg dark:prose-invert max-w-none prose-img:rounded-xl prose-img:border prose-img:border-zinc-200 dark:prose-img:border-zinc-700 [&_iframe]:w-full [&_iframe]:aspect-video [&_iframe]:rounded-xl mb-8 overflow-hidden">
                                {!! $post->content !!}
                            </div>

                            <div class="flex justify-end border-t border-zinc-200 dark:border-zinc-700 pt-5 mt-8">
                                <button @click="open = false" class="px-6 py-2.5 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-medium rounded-lg transition-colors">
                                    Tutup Panduan
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
