<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\CmsCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new class extends Component {
    public function layout()
    {
        return 'layouts.app';
    }
    use WithPagination;

    public $search = '';
    public $category_filter = '';

    public function with(): array
    {
        $user = Auth::user();
        
        $query = CmsPost::with(['category', 'author'])
            ->where('status', 'published')
            ->where(function($q) use ($user) {
                // If post has no roles and no users, it's public
                $q->whereDoesntHave('roles')
                  ->whereDoesntHave('users')
                  // Or user is directly targeted
                  ->orWhereHas('users', function($q2) use ($user) {
                      $q2->where('users.id', $user->id);
                  })
                  // Or user's role is targeted
                  ->orWhereHas('roles', function($q2) use ($user) {
                      $q2->whereIn('roles.id', $user->roles->pluck('id'));
                  });
            });

        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        if ($this->category_filter) {
            $query->where('category_id', $this->category_filter);
        }

        return [
            'posts' => $query->orderBy('is_pinned', 'desc')->latest()->paginate(12),
            'categories' => CmsCategory::all()
        ];
    }
};
?>
<div>

        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item href="/dashboard">Dashboard</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Buku Panduan</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex justify-between items-center mb-6">
            <div>
                <flux:heading size="xl" level="1">Pusat Bantuan & Panduan</flux:heading>
                <flux:subheading>Temukan dokumentasi sistem, panduan kerja, dan pengumuman terbaru.</flux:subheading>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari panduan..." class="md:w-96" />
            <flux:select wire:model.live="category_filter" placeholder="Semua Kategori" class="md:w-64">
                <flux:select.option value="">Semua Kategori</flux:select.option>
                @foreach($categories as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @forelse($posts as $post)
            <a href="{{ route('docs.show', $post->id) }}" wire:navigate class="group flex flex-col bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                @if($post->cover_image)
                    <img src="{{ Storage::url($post->cover_image) }}" class="w-full h-48 object-cover border-b border-zinc-200 dark:border-zinc-700" alt="Cover" />
                @else
                    <div class="w-full h-48 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-300 dark:text-indigo-700 border-b border-zinc-200 dark:border-zinc-700 flex flex-col items-center justify-center">
                        <flux:icon.newspaper class="w-12 h-12 mb-2" />
                        <span class="text-sm font-medium">Dokumen</span>
                    </div>
                @endif
                
                <div class="p-5 flex flex-col flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <flux:badge color="{{ $post->category?->type == 'documentation' ? 'blue' : ($post->category?->type == 'announcement' ? 'zinc' : 'green') }}" size="sm">
                            {{ $post->category?->name ?? 'Pengumuman' }}
                        </flux:badge>
                        @if($post->is_pinned)
                            <flux:badge color="amber" size="sm" icon="star" />
                        @endif
                    </div>
                    
                    <h3 class="font-bold text-lg text-zinc-900 dark:text-zinc-100 mb-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors line-clamp-2">
                        {{ $post->title }}
                    </h3>
                    
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 line-clamp-2 mb-4 flex-1">
                        {!! strip_tags($post->content) !!}
                    </p>
                    
                    <div class="flex items-center justify-between mt-auto pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2 text-xs text-zinc-500">
                            <flux:icon.user class="w-3 h-3" />
                            <span>{{ $post->author?->name ?? 'Sistem' }}</span>
                        </div>
                        <span class="text-xs text-zinc-400">{{ $post->created_at->format('d M Y') }}</span>
                    </div>
                </div>
            </a>
            @empty
                <div class="col-span-full py-12 text-center text-zinc-500">
                    <flux:icon.book-open class="w-12 h-12 mx-auto mb-4 opacity-50" />
                    <p class="text-lg">Tidak ada dokumen yang ditemukan.</p>
                </div>
            @endforelse
        </div>
        
        <div class="mt-8">
            {{ $posts->links() }}
        </div>

</div>
