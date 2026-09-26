@foreach($subtasks as $subtask)
    <div wire:key="subtask-{{ $subtask['id'] }}" 
         id="subtask-{{ $subtask['id'] }}"
         data-subtask-id="{{ $subtask['id'] }}"
         x-show="!(localHideCompleted && {{ $subtask['is_completed'] ? 'true' : 'false' }})"
         class="group relative py-1.5 transition-all duration-300">
        
        {{-- Curved Connector for this child --}}
        @if($level > 1)
            <div class="absolute left-[-23px] top-[-8px] w-[23px] h-[26px] border-l-[2px] border-b-[2px] border-zinc-200 dark:border-zinc-700/80 rounded-bl-[12px] z-0 pointer-events-none"></div>
        @endif
        
        <div class="flex items-start gap-3 relative z-10">
            {{-- Checkbox --}}
            <div class="pt-0.5 relative flex items-center justify-center shrink-0 z-20">
                <label class="relative flex items-center justify-center cursor-pointer group/cb w-5 h-5">
                    <input
                        type="checkbox"
                        wire:click="toggleSubtask({{ $subtask['id'] }})"
                        @if($subtask['is_completed']) checked @endif
                        class="peer sr-only"
                    />
                    {{-- Checkbox Background & Border --}}
                    <div class="absolute inset-0 rounded-[6px] border-2 border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 peer-checked:bg-emerald-500 peer-checked:border-emerald-500 transition-all duration-300 ease-out shadow-sm group-hover/cb:border-emerald-400 group-hover/cb:shadow-emerald-500/20"></div>
                    
                    {{-- Checkmark Icon --}}
                    <svg class="relative z-10 w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 scale-50 peer-checked:scale-100 transition-all duration-300 ease-out" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </label>
            </div>
            
            {{-- Text and Actions --}}
            <div class="flex-1 min-w-0" x-data="{
                editing: false,
                editTitle: {{ json_encode($subtask['title']) }},
                originalTitle: {{ json_encode($subtask['title']) }},
                saveRename() {
                    if (this.editTitle.trim() !== '' && this.editTitle !== this.originalTitle) {
                        $wire.renameSubtask({{ $subtask['id'] }}, this.editTitle);
                        this.originalTitle = this.editTitle;
                    }
                    this.editing = false;
                }
            }">
                <div class="flex items-center justify-between group/text mt-0.5">
                    
                    {{-- Display Mode --}}
                    <span x-show="!editing" class="subtask-drag-handle cursor-grab active:cursor-grabbing text-[15px] break-words {{ $subtask['is_completed'] ? 'text-zinc-400 dark:text-zinc-500' : 'font-medium text-zinc-700 dark:text-zinc-200' }} transition-colors duration-300 relative leading-tight">
                        <span class="relative select-none flex items-center group/title">
                            <span>{{ $subtask['title'] }}</span>
                            @if($isOwner ?? false)
                            <button @click="editing = true; $nextTick(() => $refs.editInput.focus())" class="ml-1.5 opacity-0 group-hover/title:opacity-100 transition-opacity text-zinc-400 hover:text-indigo-500 focus:outline-none">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </button>
                            @endif
                            <span class="absolute left-0 top-1/2 -translate-y-1/2 h-[1.5px] bg-zinc-400 dark:bg-zinc-500 transition-all duration-300 ease-out {{ $subtask['is_completed'] ? 'w-full opacity-100' : 'w-0 opacity-0' }}"></span>
                        </span>
                    </span>

                    {{-- Edit Mode --}}
                    <div x-show="editing" style="display: none;" class="flex-1 mr-2">
                        <input x-ref="editInput" type="text" x-model="editTitle" @keydown.enter="saveRename()" @keydown.escape="editing = false; editTitle = originalTitle" @blur="saveRename()" class="w-full text-[14px] bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-0.5 px-2 outline-none dark:text-white">
                    </div>
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2 flex items-center">
                        <flux:button variant="ghost" size="xs" wire:click="setReference('checklist', 'subtask-{{ $subtask['id'] }}', '{{ addslashes($subtask['title']) }}')" class="text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 h-6 px-2 rounded-lg transition-colors mr-1">Quote</flux:button>
                        @if($isOwner ?? false)
                            <flux:button variant="ghost" size="xs" wire:click="duplicateSubtask({{ $subtask['id'] }})" class="text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 h-6 px-2 rounded-lg transition-colors">Copy</flux:button>
                            <flux:button variant="ghost" size="xs" wire:click="deleteSubtask({{ $subtask['id'] }})" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 h-6 px-2 rounded-lg transition-colors">Delete</flux:button>
                        @endif
                    </div>
                </div>
                
                @if($subtask['requires_input'])
                    <div class="mt-2 mb-1" x-data="{ inputValue: '{{ $subtask['input_value'] ?? '' }}' }">
                        <div class="flex items-center gap-2 max-w-sm bg-white dark:bg-zinc-900/50 p-1 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:border-indigo-300 transition-all duration-300">
                            <input x-model="inputValue" placeholder="Enter value..." class="w-full bg-transparent text-sm border-none focus:ring-0 px-2 outline-none dark:text-white" />
                            <flux:button size="sm" wire:click="saveSubtaskValue({{ $subtask['id'] }}, inputValue)" class="!rounded-lg whitespace-nowrap h-7 shadow-sm transition-transform active:scale-95">Save</flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        
        {{-- Nested Children Container (Outside of flex-1 to guarantee alignment) --}}
        @if((isset($subtask['children']) && count($subtask['children']) > 0) || $level < 5)
            <div class="relative mt-1">
                {{-- Continuous Vertical Guide Line for this level --}}
                <div class="absolute left-[9px] top-[-8px] bottom-[28px] w-[2px] bg-zinc-200 dark:bg-zinc-700/80 z-0"></div>
                
                <div class="pl-8 space-y-0.5"
                     data-parent-id="{{ $subtask['id'] }}"
                     x-data="{
                        init() {
                            if (window.Sortable && !this.$el._sortable_initialized) {
                                this.$el._sortable_initialized = true;
                                window.Sortable.create(this.$el, {
                                    group: 'subtasks',
                                    animation: 250,
                                    fallbackOnBody: true,
                                    swapThreshold: 0.65,
                                    handle: '.subtask-drag-handle',
                                    ghostClass: 'subtask-ghost',
                                    dragClass: 'subtask-drag',
                                    chosenClass: 'subtask-chosen',
                                    onEnd: (e) => {
                                        const itemId = e.item.getAttribute('data-subtask-id');
                                        const newParentId = e.to.getAttribute('data-parent-id');
                                        const orderedIds = Array.from(e.to.children)
                                            .filter(c => c.hasAttribute('data-subtask-id'))
                                            .map(c => c.getAttribute('data-subtask-id'));
                                        // Deteksi apakah onEnd dipicu berkali-kali secara bersamaan (Request Flood)
                                        const now = Date.now();
                                        if (window._lastSubtaskDropTime && (now - window._lastSubtaskDropTime < 100)) {
                                            alert('⚠ ERROR TERDETEKSI: SortableJS memicu onEnd berkali-kali dalam waktu bersamaan! Ini yang membuat server macet.');
                                            return; // Hentikan agar tidak membombardir server
                                        }
                                        window._lastSubtaskDropTime = now;

                                        // Revert DOM block removed for smooth UI
                                        
                                        window.dispatchEvent(new CustomEvent('debug-log', {
                                            detail: {msg: 'Child Drop: Item ' + itemId + ' to Parent ' + (newParentId || 'null') + ' | New Order: [' + orderedIds.join(', ') + ']'}
                                        }));

                                        if (itemId) {
                                            $wire.reorderSubtasks(itemId, newParentId || null, orderedIds);
                                        }
                                    }
                                });
                            }
                        }
                     }"
                >
                    @if(isset($subtask['children']) && count($subtask['children']) > 0)
                        @include('livewire.workspace.partials.subtask-item', ['subtasks' => $subtask['children'], 'level' => $level + 1, 'isOwner' => $isOwner ?? false])
                    @endif
                    
                    @if($level < 5)
                        <div class="relative py-1" x-data="{ isAdding: false, title: '', isSaving: false }">
                            {{-- Curved Connector for Add button --}}
                            <div class="absolute left-[-23px] top-[-8px] w-[23px] h-[24px] border-l-[2px] border-b-[2px] border-zinc-200 dark:border-zinc-700/80 rounded-bl-[12px] z-0 pointer-events-none"></div>
                            
                            <div x-show="!isAdding" class="opacity-0 group-hover:opacity-100 transition-opacity relative z-10">
                                <button type="button" @click="isAdding = true; $nextTick(() => $refs.newSubtaskInput.focus())" class="text-xs font-semibold text-zinc-400 hover:text-indigo-500 dark:text-zinc-500 dark:hover:text-indigo-400 flex items-center gap-1.5 py-1 px-1.5 rounded-lg hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-colors border border-transparent hover:border-indigo-100 dark:hover:border-indigo-500/20">
                                    <flux:icon.plus class="w-3.5 h-3.5" /> Add sub-task
                                </button>
                            </div>
                            <div x-show="isAdding" class="flex gap-2 items-center relative z-10" x-transition>
                                <input x-ref="newSubtaskInput" x-model="title" x-bind:disabled="isSaving" placeholder="Sub-task title..." class="w-full max-w-xs h-8 text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 outline-none focus:ring-2 focus:ring-indigo-500/20 dark:text-white transition-all shadow-sm disabled:opacity-50" @keydown.enter="if(!title || isSaving) return; isSaving = true; $wire.addSubtask({{ $subtask['id'] }}, title).then(() => { isAdding = false; title = ''; isSaving = false; })" />
                                <flux:button size="sm" class="!h-8 !px-3 shadow-sm transition-transform active:scale-95" variant="primary" @click="isSaving = true; $wire.addSubtask({{ $subtask['id'] }}, title).then(() => { isAdding = false; title = ''; isSaving = false; })" x-bind:disabled="!title || isSaving">
                                    <span x-text="isSaving ? 'Saving...' : 'Add'"></span>
                                </flux:button>
                                <flux:button size="sm" class="!h-8 !px-3" variant="ghost" @click="isAdding = false" x-bind:disabled="isSaving">Cancel</flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endforeach
