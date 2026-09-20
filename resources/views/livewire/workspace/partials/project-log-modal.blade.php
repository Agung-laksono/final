<flux:modal name="project-log" class="w-full max-w-2xl p-0 overflow-hidden">
    @if($selectedProject)
    <div class="flex flex-col h-[80vh] md:h-[600px] bg-white dark:bg-zinc-900 relative">
        
        {{-- Header --}}
        <div class="shrink-0 p-6 border-b border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md sticky top-0 z-20 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-lg">
                    <flux:icon.clock class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">Activity Log</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">History of changes for this task</p>
                </div>
            </div>
            
            <flux:button variant="ghost" size="sm" icon="x-mark" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300" x-on:click="$flux.modal('project-log').close()" />
        </div>

        {{-- Log Timeline --}}
        <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">
            @forelse(collect($selectedProject['activities'] ?? [])->sortByDesc('created_at')->values() as $activity)
                @php
                    $desc = strtolower($activity['description'] ?? $activity['action'] ?? '');
                    $icon = 'pencil';
                    $color = 'bg-blue-500 text-white';
                    
                    if (str_contains($desc, 'membuat') || str_contains($desc, 'create') || str_contains($desc, 'tambah')) {
                        $icon = 'plus';
                        $color = 'bg-emerald-500 text-white';
                    } elseif (str_contains($desc, 'hapus') || str_contains($desc, 'delete') || str_contains($desc, 'remove')) {
                        $icon = 'trash';
                        $color = 'bg-red-500 text-white';
                    } elseif (str_contains($desc, 'pindah') || str_contains($desc, 'move') || str_contains($desc, 'status')) {
                        $icon = 'arrows-right-left';
                        $color = 'bg-amber-500 text-white';
                    } elseif (str_contains($desc, 'lampiran') || str_contains($desc, 'attach') || str_contains($desc, 'file') || str_contains($desc, 'mengunggah')) {
                        $icon = 'paper-clip';
                        $color = 'bg-indigo-500 text-white';
                    } elseif (str_contains($desc, 'komentar') || str_contains($desc, 'comment')) {
                        $icon = 'chat-bubble-left';
                        $color = 'bg-cyan-500 text-white';
                    } elseif (str_contains($desc, 'arsip') || str_contains($desc, 'archive')) {
                        $icon = 'archive-box';
                        $color = 'bg-zinc-500 text-white';
                    }
                @endphp
                <div class="flex gap-4 relative group">
                    {{-- Vertical Line for timeline --}}
                    @if(!$loop->last)
                        <div class="absolute top-[44px] left-[19px] bottom-[-24px] w-px bg-zinc-200 dark:bg-zinc-800"></div>
                    @endif
                    
                    {{-- Avatar with Action Badge --}}
                    <div class="shrink-0 relative z-10 self-start mt-1">
                        @if(isset($activity['user']['avatar']))
                            <img src="{{ Storage::url($activity['user']['avatar']) }}" alt="{{ $activity['user']['name'] ?? 'System' }}" class="w-10 h-10 rounded-full border-2 border-white dark:border-zinc-900 shadow-sm object-cover" />
                        @else
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-800 dark:to-zinc-700 flex items-center justify-center border-2 border-white dark:border-zinc-900 shadow-sm font-bold text-sm text-zinc-600 dark:text-zinc-300">
                                {{ substr($activity['user']['name'] ?? 'S', 0, 1) }}
                            </div>
                        @endif
                        
                        {{-- Activity Type Badge --}}
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full {{ $color }} border border-white dark:border-zinc-900 flex items-center justify-center shadow-sm">
                            @if($icon == 'plus') <flux:icon.plus class="w-2.5 h-2.5" />
                            @elseif($icon == 'trash') <flux:icon.trash class="w-2.5 h-2.5" />
                            @elseif($icon == 'arrows-right-left') <flux:icon.arrows-right-left class="w-2.5 h-2.5" />
                            @elseif($icon == 'paper-clip') <flux:icon.paper-clip class="w-2.5 h-2.5" />
                            @elseif($icon == 'chat-bubble-left') <flux:icon.chat-bubble-left class="w-2.5 h-2.5" />
                            @elseif($icon == 'archive-box') <flux:icon.archive-box class="w-2.5 h-2.5" />
                            @else <flux:icon.pencil class="w-2.5 h-2.5" /> @endif
                        </div>
                    </div>
                    
                    {{-- Content Card --}}
                    <div class="flex-1 pb-4">
                        <div class="bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-2xl border border-zinc-100 dark:border-zinc-700/50 hover:border-zinc-200 dark:hover:border-zinc-600 transition-colors shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 mb-1.5">
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $activity['user']['name'] ?? 'System' }}
                                </span>
                                <span class="text-[11px] font-medium text-zinc-500 bg-white dark:bg-zinc-800 px-2.5 py-1 rounded-full border border-zinc-200 dark:border-zinc-700 inline-flex self-start sm:self-auto shadow-sm">
                                    {{ \Carbon\Carbon::parse($activity['created_at'])->diffForHumans() }}
                                </span>
                            </div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $activity['description'] ?? $activity['action'] ?? 'Performed an action' }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-12">
                    <div class="w-16 h-16 mx-auto bg-zinc-100 dark:bg-zinc-800 rounded-full flex items-center justify-center mb-4">
                        <flux:icon.clock class="w-8 h-8 text-zinc-400" />
                    </div>
                    <h3 class="text-zinc-900 dark:text-zinc-100 font-medium mb-1">No Activity Yet</h3>
                    <p class="text-zinc-500 text-sm">There are no recorded actions for this task.</p>
                </div>
            @endforelse
        </div>
        
    </div>
    @endif
</flux:modal>
