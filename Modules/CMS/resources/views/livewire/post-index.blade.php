<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\CMS\Models\CmsPost;
new class extends Component {
    public function layout()
    {
        return 'layouts.app';
    }
    use WithPagination;

    public $search = '';

    public function with(): array
    {
        return [
            'posts' => CmsPost::with(['category', 'author'])
                ->where('title', 'like', '%' . $this->search . '%')
                ->orderBy('is_pinned', 'desc')
                ->latest()
                ->paginate(10)
        ];
    }
    
    public function deletePost(CmsPost $post)
    {
        $post->delete();
        $this->resetPage();
    }
};
?>
<div>

        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item href="/dashboard">Dashboard</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Kelola CMS</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <flux:heading size="xl" level="1">Pengumuman & Dokumentasi</flux:heading>
                <flux:subheading>Kelola artikel publik, pengumuman internal, dan dokumentasi sistem.</flux:subheading>
            </div>
            <flux:button href="{{ route('cms.posts.create') }}" variant="primary" icon="plus" class="w-full sm:w-auto">Buat Baru</flux:button>
        </div>

        <flux:card>
            <div class="flex items-center gap-4 mb-4 w-full">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari judul artikel..." class="w-full sm:max-w-md" />
            </div>

            @if($posts->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 px-4 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl my-4 bg-zinc-50 dark:bg-zinc-800/50">
                    <flux:icon.document-text class="w-12 h-12 text-zinc-400 mb-4" />
                    <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Belum ada artikel</h3>
                    <p class="text-zinc-500 dark:text-zinc-400 text-center max-w-sm mt-1">Tidak ada data ditemukan untuk pencarian ini.</p>
                </div>
            @else
                <!-- Desktop/Tablet Table View -->
                <div class="hidden md:block">
                    <x-table.wrapper>
                        <flux:table>
                            <flux:table.columns>
                            <flux:table.column>Judul</flux:table.column>
                            <flux:table.column>Kategori</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Penulis</flux:table.column>
                            <flux:table.column>Tanggal</flux:table.column>
                            <flux:table.column>Aksi</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach($posts as $post)
                            <flux:table.row>
                                <flux:table.cell class="max-sm:!pl-0 max-sm:!mb-2">
                                    <div class="flex items-center gap-3">
                                        @if($post->cover_image)
                                            <img src="{{ Storage::disk('public')->url($post->cover_image) }}" class="w-10 h-10 rounded-md object-cover border border-zinc-200 dark:border-zinc-700" alt="Thumbnail">
                                        @else
                                            <div class="w-10 h-10 rounded-md bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shrink-0">
                                                <flux:icon.document-text class="w-5 h-5 text-zinc-400" />
                                            </div>
                                        @endif
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-2">
                                                @if($post->is_pinned)
                                                    <flux:badge color="amber" icon="star" class="shrink-0" />
                                                @endif
                                                <span class="font-medium truncate max-w-[200px]" title="{{ $post->title }}">{{ $post->title }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="{{ $post->category?->type == 'documentation' ? 'blue' : ($post->category?->type == 'announcement' ? 'zinc' : 'green') }}" size="sm">
                                        {{ $post->category?->name ?? 'Tanpa Kategori' }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($post->status == 'published')
                                        <flux:badge color="green" size="sm">Published</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">Draft</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $post->author?->name ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $post->created_at->format('d M Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" inset="top bottom" />
                                        <flux:menu>
                                            <flux:menu.item href="{{ route('cms.posts.edit', $post->id) }}" icon="pencil">Edit</flux:menu.item>
                                            <flux:menu.item wire:click="deletePost({{ $post->id }})" wire:confirm="Yakin ingin menghapus artikel ini?" icon="trash" variant="danger">Hapus</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </x-table.wrapper>
                </div>
                
                <!-- Mobile List View -->
                <div class="md:hidden space-y-3">
                    @foreach($posts as $post)
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 flex gap-3 relative shadow-sm">
                        @if($post->cover_image)
                            <img src="{{ Storage::disk('public')->url($post->cover_image) }}" class="w-20 h-20 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 shrink-0" alt="Thumbnail">
                        @else
                            <div class="w-20 h-20 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shrink-0">
                                <flux:icon.photo class="w-8 h-8 text-zinc-400 opacity-50" />
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1.5">
                                @if($post->is_pinned)
                                    <flux:icon.star class="w-3.5 h-3.5 text-amber-500 fill-amber-500" />
                                @endif
                                <span class="text-[11px] font-bold tracking-wide uppercase text-zinc-500 dark:text-zinc-400">{{ $post->category?->name ?? 'Umum' }}</span>
                                @if($post->status == 'published')
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                @else
                                    <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                                @endif
                            </div>
                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $post->title }}</h4>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-2 leading-relaxed">
                                {!! Str::limit(strip_tags($post->content), 120) !!}
                            </p>
                            <div class="flex items-center gap-2 mt-2 text-[11px] text-zinc-500 dark:text-zinc-500">
                                <span>{{ $post->author?->name ?? 'Sistem' }}</span>
                                <span class="w-1 h-1 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                <span>{{ $post->created_at->format('d M Y') }}</span>
                            </div>
                        </div>
                        <div>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="-mr-2 -mt-1 text-zinc-400 hover:text-zinc-600" />
                                <flux:menu>
                                    <flux:menu.item href="{{ route('cms.posts.edit', $post->id) }}" icon="pencil">Edit</flux:menu.item>
                                    <flux:menu.item wire:click="deletePost({{ $post->id }})" wire:confirm="Yakin ingin menghapus artikel ini?" icon="trash" variant="danger">Hapus</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
            <div class="mt-4">
                {{ $posts->links() }}
            </div>
        </flux:card>

</div>
