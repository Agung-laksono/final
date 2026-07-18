@props([
    'title' => 'EDITOR CATATAN',
    'subtitle' => 'RICH TEXT MODE',
    'wireModel' => 'tempNoteContent',
    'onSave' => 'saveEditor()',
    'onCancel' => 'showEditor = false',
    'showVariable' => 'showEditor',
])

{{-- Panel Editor Rich Text --}}
<div x-show="{{ $showVariable }}"
     x-transition.opacity.duration.200ms
     :class="{{ $showVariable }} ? 'pointer-events-auto' : 'pointer-events-none'"
     style="display: none;"
     class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
     @keydown.escape.window="{{ $onCancel }}">

    {{-- Backdrop --}}
    <div x-show="{{ $showVariable }}"
         x-transition.opacity.duration.300ms
         class="absolute inset-0 bg-zinc-900/50 dark:bg-zinc-900/80 backdrop-blur-sm"
         @click="{{ $onCancel }}"></div>

    {{-- Modal --}}
    <div x-show="{{ $showVariable }}"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-[800px] h-[90vh] overflow-hidden flex flex-col">

        {{-- Header --}}
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 shrink-0">
            <div>
                <h3 class="font-semibold text-lg text-zinc-800 dark:text-zinc-100 uppercase tracking-widest">{{ $title }}</h3>
                <p class="text-[10px] text-zinc-400 tracking-wider uppercase mt-0.5">{{ $subtitle }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="{{ $onCancel }}"
                        class="text-zinc-400 hover:text-rose-500 transition-colors p-2 rounded-full hover:bg-rose-50 dark:hover:bg-rose-900/20">
                    <flux:icon.x-mark class="w-5 h-5" />
                </button>
            </div>
        </div>

        {{-- Rich Editor Body --}}
        <div class="flex-1 relative p-2 sm:p-4 w-full max-w-full flex flex-col" wire:ignore>
            <div class="flex-1 w-full max-w-full border border-zinc-200 dark:border-zinc-700 rounded-lg bg-white dark:bg-zinc-900 shadow-sm flex flex-col">
                <x-rich-editor wire:model="{{ $wireModel }}" height="100%" />
            </div>
        </div>

        {{-- Footer --}}
        <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex justify-end gap-2 shrink-0">
            <flux:button variant="ghost" @click="{{ $onCancel }}"> Batal </flux:button>
            <flux:button icon="check" variant="primary" @click="{{ $onSave }}"> Simpan Catatan </flux:button>
        </div>
    </div>
</div>
