<?php

use Livewire\Volt\Component;
use Modules\CMS\Models\CmsPost;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public function with(): array
    {
        $user = Auth::user();
        
        $posts = CmsPost::with(['category', 'author'])
            ->where('status', 'published')
            ->where(function($query) use ($user) {
                // If post has no roles and no users, it's public
                $query->whereDoesntHave('roles')
                      ->whereDoesntHave('users')
                      // Or user is directly targeted
                      ->orWhereHas('users', function($q) use ($user) {
                          $q->where('users.id', $user->id);
                      })
                      // Or user's role is targeted
                      ->orWhereHas('roles', function($q) use ($user) {
                          $q->whereIn('roles.id', $user->roles->pluck('id'));
                      });
            })
            ->orderBy('is_pinned', 'desc')
            ->latest()
            ->take(5)
            ->get();

        return [
            'posts' => $posts
        ];
    }
    
    public function markAsRead($postId)
    {
        $post = CmsPost::find($postId);
        if ($post) {
            $user = Auth::user();
            // Attach read status if not already read
            if (!$post->readers()->where('users.id', $user->id)->exists()) {
                $post->readers()->attach($user->id, ['read_at' => now()]);
            }
            
            $this->dispatch('open-announcement-modal', id: $postId);
        }
    }
};
?>
<flux:card class="col-span-1 md:col-span-2 lg:col-span-3 mb-6 !p-0 overflow-hidden shadow-sm border-zinc-200/60 dark:border-zinc-700">
    <div class="px-5 py-4 border-b border-zinc-200/60 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex justify-between items-center">
        <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
            <flux:icon.megaphone variant="solid" class="w-5 h-5" />
            <flux:heading class="font-semibold text-lg text-zinc-800 dark:text-zinc-100">Papan Pengumuman</flux:heading>
        </div>
        <flux:button href="{{ route('cms.posts.index') }}" variant="subtle" size="sm" icon-trailing="arrow-right">Lihat Semua</flux:button>
    </div>
    
    <div class="divide-y divide-zinc-200/60 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
        @forelse($posts as $post)
            @php
                $isUnread = !$post->readers->contains(auth()->id());
            @endphp
            <div wire:click="markAsRead({{ $post->id }})" class="p-4 flex gap-4 items-start hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors relative group">
                @if($post->cover_image)
                    <img src="{{ Storage::url($post->cover_image) }}" class="w-20 h-20 rounded-lg object-cover flex-shrink-0 border border-zinc-200 dark:border-zinc-700 group-hover:shadow-md transition-shadow" alt="Cover" />
                @else
                    <div class="w-20 h-20 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 text-indigo-300 dark:text-indigo-700 border border-indigo-100 dark:border-indigo-800 flex items-center justify-center flex-shrink-0">
                        <flux:icon.newspaper class="w-8 h-8" />
                    </div>
                @endif
                
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="text-base font-medium text-zinc-900 dark:text-zinc-100 truncate {{ $isUnread ? 'font-bold' : '' }}">
                            {{ $post->title }}
                        </h3>
                        @if($isUnread)
                            <flux:badge color="red" size="sm" class="animate-pulse">NEW</flux:badge>
                        @endif
                        @if($post->is_pinned)
                            <flux:badge color="amber" size="sm" icon="star" />
                        @endif
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 line-clamp-2 mb-2">
                        {!! strip_tags($post->content) !!}
                    </p>
                    <div class="flex items-center gap-3 text-xs text-zinc-400">
                        <div class="flex items-center gap-1">
                            <flux:avatar :src="$post->author?->avatarUrl()" :name="$post->author?->name" size="xs" />
                            <span>{{ $post->author?->name ?? 'Sistem' }}</span>
                        </div>
                        <span>&bull;</span>
                        <span>{{ $post->created_at->diffForHumans() }}</span>
                        @if($post->category)
                            <span>&bull;</span>
                            <span class="text-indigo-500 font-medium">{{ $post->category->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                <flux:icon.inbox class="w-8 h-8 mx-auto mb-2 opacity-50" />
                <p>Belum ada pengumuman terbaru.</p>
            </div>
        @endforelse
    </div>
</flux:card>
