<?php

use Livewire\Volt\Component;
use Modules\CMS\Models\CmsPost;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new class extends Component {
    public function layout()
    {
        return 'layouts.app';
    }
    public CmsPost $post;

    public function mount(CmsPost $post)
    {
        $user = Auth::user();
        
        // Cek permission
        $canRead = false;
        
        if ($post->roles->isEmpty() && $post->users->isEmpty()) {
            $canRead = true; // Publik
        } elseif ($post->users->contains($user->id)) {
            $canRead = true; // User spesifik
        } elseif ($post->roles->intersect($user->roles)->isNotEmpty()) {
            $canRead = true; // Role
        }

        if (!$canRead) {
            abort(403, 'Anda tidak memiliki akses untuk membaca dokumen ini.');
        }

        // Catat read receipt
        if (!$post->readers()->where('users.id', $user->id)->exists()) {
            $post->readers()->attach($user->id, ['read_at' => now()]);
        }
        
        $this->post = $post;
    }
};
?>
<div>
    <flux:main container>
        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item href="/dashboard">Dashboard</flux:breadcrumbs.item>
            <flux:breadcrumbs.item href="{{ route('docs.index') }}">Buku Panduan</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $post->title }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:card class="p-0 overflow-hidden mb-8">
            @if($post->cover_image)
                <div class="w-full h-[400px] relative">
                    <img src="{{ Storage::url($post->cover_image) }}" class="w-full h-full object-cover" alt="Cover" />
                    <div class="absolute inset-0 bg-gradient-to-t from-zinc-900/80 to-transparent flex flex-col justify-end p-8">
                        <div class="flex items-center gap-3 mb-4">
                            <flux:badge color="{{ $post->category?->type == 'documentation' ? 'blue' : ($post->category?->type == 'announcement' ? 'zinc' : 'green') }}">
                                {{ $post->category?->name ?? 'Pengumuman' }}
                            </flux:badge>
                            @if($post->is_pinned)
                                <flux:badge color="amber" icon="star" />
                            @endif
                            <span class="text-sm text-zinc-300 flex items-center gap-1">
                                <flux:icon.clock class="w-4 h-4" /> {{ $post->created_at->format('d M Y') }}
                            </span>
                        </div>
                        <h1 class="text-3xl md:text-5xl font-bold text-white mb-2">{{ $post->title }}</h1>
                        <div class="flex items-center gap-2 text-zinc-300 text-sm">
                            <flux:avatar :src="$post->author?->avatarUrl()" :name="$post->author?->name" size="sm" />
                            <span>Oleh {{ $post->author?->name ?? 'Sistem' }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-8 pb-4 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center gap-3 mb-4">
                        <flux:badge color="{{ $post->category?->type == 'documentation' ? 'blue' : ($post->category?->type == 'announcement' ? 'zinc' : 'green') }}">
                            {{ $post->category?->name ?? 'Pengumuman' }}
                        </flux:badge>
                        @if($post->is_pinned)
                            <flux:badge color="amber" icon="star" />
                        @endif
                        <span class="text-sm text-zinc-500 flex items-center gap-1">
                            <flux:icon.clock class="w-4 h-4" /> {{ $post->created_at->format('d M Y') }}
                        </span>
                    </div>
                    <flux:heading size="xl" level="1" class="mb-4 !text-4xl font-bold">{{ $post->title }}</flux:heading>
                    <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400 text-sm">
                        <flux:avatar :src="$post->author?->avatarUrl()" :name="$post->author?->name" size="sm" />
                        <span>Oleh {{ $post->author?->name ?? 'Sistem' }}</span>
                    </div>
                </div>
            @endif

            <div class="p-8 md:p-12 lg:px-24">
                <div class="prose dark:prose-invert max-w-none prose-img:rounded-xl prose-a:text-indigo-600 dark:prose-a:text-indigo-400 prose-headings:text-zinc-900 dark:prose-headings:text-zinc-100">
                    {!! $post->content !!}
                </div>
            </div>
            
            <div class="px-8 py-6 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <div class="text-sm text-zinc-500">
                    Terakhir diperbarui: {{ $post->updated_at->format('d M Y - H:i') }}
                </div>
                <flux:button href="{{ route('docs.index') }}" variant="ghost" icon="arrow-left">Kembali ke Panduan</flux:button>
            </div>
        </flux:card>
    </flux:main>
</div>
