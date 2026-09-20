@foreach($subtasks as $subtask)
    <div wire:key="subtask-{{ $subtask['id'] }}" 
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
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between group/text mt-0.5">
                    <span class="text-[15px] break-words {{ $subtask['is_completed'] ? 'text-zinc-400 dark:text-zinc-500' : 'font-medium text-zinc-700 dark:text-zinc-200' }} transition-colors duration-300 relative leading-tight">
                        <span class="relative">
                            {{ $subtask['title'] }}
                            <span class="absolute left-0 top-1/2 -translate-y-1/2 h-[1.5px] bg-zinc-400 dark:bg-zinc-500 transition-all duration-300 ease-out {{ $subtask['is_completed'] ? 'w-full opacity-100' : 'w-0 opacity-0' }}"></span>
                        </span>
                    </span>
                    @if($isOwner ?? false)
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2">
                        <flux:button variant="ghost" size="xs" wire:click="deleteSubtask({{ $subtask['id'] }})" class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 h-6 px-2 rounded-lg transition-colors">Delete</flux:button>
                    </div>
                    @endif
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
        @if((isset($subtask['children']) && count($subtask['children']) > 0) || $level < 3)
            <div class="relative mt-1">
                {{-- Continuous Vertical Guide Line for this level --}}
                <div class="absolute left-[9px] top-[-8px] bottom-[16px] w-[2px] bg-zinc-200 dark:bg-zinc-700/80 z-0"></div>
                
                <div class="pl-8 space-y-0.5">
                    @if(isset($subtask['children']) && count($subtask['children']) > 0)
                        @include('livewire.workspace.partials.subtask-item', ['subtasks' => $subtask['children'], 'level' => $level + 1, 'isOwner' => $isOwner ?? false])
                    @endif
                    
                    @if($level < 3)
                        <div class="relative py-1" x-data="{ isAdding: false, title: '' }">
                            {{-- Curved Connector for Add button --}}
                            <div class="absolute left-[-23px] top-[-8px] w-[23px] h-[24px] border-l-[2px] border-b-[2px] border-zinc-200 dark:border-zinc-700/80 rounded-bl-[12px] z-0 pointer-events-none"></div>
                            
                            <div x-show="!isAdding" class="opacity-0 group-hover:opacity-100 transition-opacity relative z-10">
                                <button type="button" @click="isAdding = true; $nextTick(() => $refs.newSubtaskInput.focus())" class="text-xs font-semibold text-zinc-400 hover:text-indigo-500 dark:text-zinc-500 dark:hover:text-indigo-400 flex items-center gap-1.5 py-1 px-1.5 rounded-lg hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-colors border border-transparent hover:border-indigo-100 dark:hover:border-indigo-500/20">
                                    <flux:icon.plus class="w-3.5 h-3.5" /> Add sub-task
                                </button>
                            </div>
                            <div x-show="isAdding" class="flex gap-2 items-center relative z-10" x-transition>
                                <input x-ref="newSubtaskInput" x-model="title" placeholder="Sub-task title..." class="w-full max-w-xs h-8 text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 outline-none focus:ring-2 focus:ring-indigo-500/20 dark:text-white transition-all shadow-sm" @keydown.enter="$wire.addSubtask({{ $subtask['id'] }}, title).then(() => { isAdding = false; title = ''; })" />
                                <flux:button size="sm" class="!h-8 !px-3 shadow-sm transition-transform active:scale-95" variant="primary" @click="$wire.addSubtask({{ $subtask['id'] }}, title).then(() => { isAdding = false; title = ''; })" x-bind:disabled="!title">Add</flux:button>
                                <flux:button size="sm" class="!h-8 !px-3" variant="ghost" @click="isAdding = false">Cancel</flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endforeach
