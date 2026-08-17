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
    <flux:main container>
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

            <x-table.wrapper>
    <flux:table class="table-mobile-cards">
                <flux:table.columns>
                    <flux:table.column>Judul</flux:table.column>
                    <flux:table.column>Kategori</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Penulis</flux:table.column>
                    <flux:table.column>Tanggal</flux:table.column>
                    <flux:table.column>Aksi</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($posts as $post)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if($post->is_pinned)
                                    <flux:badge color="amber" icon="star" class="shrink-0" />
                                @endif
                                <span class="font-medium truncate max-w-[200px]" title="{{ $post->title }}">{{ $post->title }}</span>
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
                    @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-6 text-zinc-500">Tidak ada data ditemukan.</flux:table.cell>
                    </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
</x-table.wrapper>
            <div class="mt-4">
                {{ $posts->links() }}
            </div>
        </flux:card>
    </flux:main>
</div>
