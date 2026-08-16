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
        <div class="fixed bottom-6 right-6 z-50">
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

        <flux:modal wire:model="show" position="right" class="w-full sm:w-[32rem]">
            @if($post)
                <div class="p-6">
                    <div class="flex items-start gap-4 border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-6">
                        <div class="flex-shrink-0 w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center">
                            <flux:icon.book-open class="w-6 h-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="xl">{{ $post->title }}</flux:heading>
                            <div class="flex items-center gap-2 mt-2">
                                <flux:badge size="sm" color="indigo">Panduan</flux:badge>
                                @if($post->category)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $post->category->name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="prose prose-sm dark:prose-invert max-w-none prose-img:rounded-xl prose-img:border prose-img:border-zinc-200 dark:prose-img:border-zinc-700">
                        {!! $post->content !!}
                    </div>
                </div>
            @endif
        </flux:modal>
    @endif
</div>
