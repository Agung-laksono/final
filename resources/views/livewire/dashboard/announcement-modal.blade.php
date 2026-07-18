<?php

use Livewire\Volt\Component;
use Modules\CMS\Models\CmsPost;
use Livewire\Attributes\On;

new class extends Component {
    public ?CmsPost $post = null;
    public bool $show = false;

    #[On('open-announcement-modal')]
    public function loadPost($id)
    {
        $this->post = CmsPost::with(['category', 'author'])->find($id);
        if ($this->post) {
            $this->show = true;
        }
    }
};
?>
<div>
    <flux:modal wire:model="show" class="md:w-[800px] space-y-6" scroll="body">
        @if($post)
            <div>
                @if($post->cover_image)
                    <img src="{{ Storage::url($post->cover_image) }}" class="w-full h-64 object-cover rounded-xl mb-6 shadow-sm border border-zinc-200 dark:border-zinc-700" alt="Cover" />
                @endif
                
                <div class="flex items-center gap-3 mb-4">
                    <flux:badge color="{{ $post->category?->type == 'documentation' ? 'blue' : ($post->category?->type == 'announcement' ? 'zinc' : 'green') }}">
                        {{ $post->category?->name ?? 'Pengumuman' }}
                    </flux:badge>
                    <span class="text-sm text-zinc-500">{{ $post->created_at->format('d M Y - H:i') }}</span>
                    <span class="text-sm text-zinc-500 flex items-center gap-1">
                        <flux:icon.user class="w-4 h-4" /> {{ $post->author?->name ?? 'Sistem' }}
                    </span>
                </div>
                
                <flux:heading size="xl" level="1" class="mb-6 !text-3xl font-bold">{{ $post->title }}</flux:heading>
                
                <!-- Konten Artikel dengan styling khusus (prose) -->
                <div class="prose dark:prose-invert max-w-none prose-img:rounded-xl prose-a:text-indigo-600 dark:prose-a:text-indigo-400">
                    {!! $post->content !!}
                </div>
                
            </div>
            
            <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700 mt-8">
                <flux:button wire:click="$set('show', false)" variant="primary">Tutup</flux:button>
            </div>
        @endif
    </flux:modal>
</div>
