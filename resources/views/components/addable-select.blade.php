@props([
    'label'        => '',
    'options'      => [],
    'optionsProp'  => '',       // Nama properti Livewire (contoh: "units", "types")
    'optionValue'  => 'id',
    'optionLabel'  => 'name',
    'placeholder'  => '-- Pilih --',
    'addNewText'   => 'Tambah Baru',
    'addNewEvent'  => null,
    'required'     => false,
])

@php
    $wireModel = $attributes->wire('model')->value();
    
    // Normalisasi awal untuk render pertama (SSR)
    $initialOptions = collect($options)->map(function ($opt) use ($optionValue, $optionLabel) {
        return [
            'value' => is_object($opt) ? $opt->{$optionValue} : $opt[$optionValue],
            'label' => is_object($opt) ? $opt->{$optionLabel} : $opt[$optionLabel],
        ];
    })->values()->toArray();
@endphp

<div
    {{ $attributes->whereDoesntStartWith('wire:model') }}
    x-data="{
        open: false,
        search: '',
        selected: @entangle($wireModel).live,
        addNewEvent: @js($addNewEvent),
        options: {{ Js::from($initialOptions) }},
        
        updateOptions(newRawOptions) {
            let items = Array.isArray(newRawOptions) ? newRawOptions : Object.values(newRawOptions);
            this.options = items.map(o => {
                if (typeof o === 'object' && o !== null) {
                    return { value: o['{{ $optionValue }}'], label: o['{{ $optionLabel }}'] };
                }
                return { value: o, label: o };
            });
        },

        get filtered() {
            if (!this.search) return this.options;
            const q = this.search.toLowerCase();
            return this.options.filter(o => o.label && String(o.label).toLowerCase().includes(q));
        },

        get selectedLabel() {
            const found = this.options.find(o => String(o.value) === String(this.selected));
            return found ? found.label : null;
        },

        selectOption(val) {
            this.selected = val;
            this.open = false;
            this.search = '';
        },

        openAddNew() {
            this.open = false;
            this.search = '';
            if (this.addNewEvent) {
                window.dispatchEvent(new CustomEvent(this.addNewEvent));
            }
        },

        focusedIndex: -1,

        navigateDown() {
            const max = this.filtered.length;
            this.focusedIndex = Math.min(this.focusedIndex + 1, max);
        },

        navigateUp() {
            this.focusedIndex = Math.max(this.focusedIndex - 1, 0);
        },

        confirmSelection() {
            if (this.focusedIndex >= 0 && this.focusedIndex < this.filtered.length) {
                this.selectOption(this.filtered[this.focusedIndex].value);
            } else if (this.focusedIndex === this.filtered.length) {
                this.openAddNew();
            }
        }
    }"
    x-on:keydown.escape="open = false; search = ''"
    x-on:keydown.arrow-down.prevent="navigateDown()"
    x-on:keydown.arrow-up.prevent="navigateUp()"
    x-on:keydown.enter.prevent="open ? confirmSelection() : (open = true)"
    x-on:click.outside="open = false; search = ''"
    x-on:unit-updated.window="if ('{{ $optionsProp }}' === 'units' && $event.detail.options) updateOptions($event.detail.options)"
    x-on:type-updated.window="if ('{{ $optionsProp }}' === 'types' && $event.detail.options) updateOptions($event.detail.options)"
    x-on:category-updated.window="if ('{{ $optionsProp }}' === 'categories' && $event.detail.options) updateOptions($event.detail.options)"
    x-on:subcategory-updated.window="if ('{{ $optionsProp }}' === 'subcategories' && $event.detail.options) updateOptions($event.detail.options)"
    class="relative"
>
    {{-- Label --}}
    @if($label)
        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
            {{ $label }}
            @if($required)
                <span class="text-red-500 ml-0.5">*</span>
            @endif
        </label>
    @endif

    {{-- Trigger Button --}}
    <button
        type="button"
        x-on:click="open = !open; if(open) { $nextTick(() => $refs.search.focus()); focusedIndex = -1; }"
        class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border transition-all duration-200
               bg-white dark:bg-zinc-900
               border-zinc-200 dark:border-zinc-700
               text-zinc-900 dark:text-zinc-100
               hover:border-zinc-300 dark:hover:border-zinc-600
               focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 dark:focus:border-blue-400"
        :class="open ? 'ring-2 ring-blue-500/30 border-blue-500 dark:border-blue-400' : ''"
    >
        <span x-text="selectedLabel || '{{ $placeholder }}'"
              :class="selectedLabel ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-500'">
        </span>
        <svg x-bind:class="open ? 'rotate-180' : ''"
             class="size-4 text-zinc-400 transition-transform duration-200 shrink-0"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
        x-cloak
        class="absolute z-50 mt-1.5 w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xl shadow-zinc-200/50 dark:shadow-zinc-900/80 overflow-hidden"
    >
        {{-- Search Input --}}
        <div class="p-2 border-b border-zinc-100 dark:border-zinc-800">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-zinc-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                </svg>
                <input
                    x-ref="search"
                    x-model="search"
                    type="text"
                    placeholder="Cari..."
                    class="w-full pl-8 pr-3 py-1.5 text-sm rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-all"
                />
            </div>
        </div>

        {{-- Options List --}}
        <ul class="max-h-52 overflow-y-auto py-1.5 px-1.5 space-y-0.5">
            <li>
                <button type="button" x-on:click="selectOption('')"
                    class="w-full text-left px-3 py-2 text-sm rounded-lg text-zinc-400 dark:text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                    {{ $placeholder }}
                </button>
            </li>

            <template x-for="(opt, index) in filtered" :key="opt.value">
                <li>
                    <button
                        type="button"
                        x-on:click="selectOption(opt.value)"
                        :class="{
                            'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium': String(selected) === String(opt.value),
                            'bg-zinc-100 dark:bg-zinc-700': focusedIndex === index,
                            'text-zinc-700 dark:text-zinc-300': String(selected) !== String(opt.value)
                        }"
                        class="w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors flex items-center justify-between gap-2"
                    >
                        <span x-text="opt.label"></span>
                        <svg x-show="String(selected) === String(opt.value)"
                             class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </li>
            </template>

            <li x-show="filtered.length === 0" class="px-3 py-4 text-center text-sm text-zinc-400 dark:text-zinc-500">
                Tidak ada hasil untuk "<span x-text="search" class="font-medium"></span>"
            </li>
        </ul>

        {{-- Add New Button --}}
        @if($addNewText)
        <div class="p-1.5 border-t border-zinc-100 dark:border-zinc-800">
            <button
                type="button"
                x-on:click="openAddNew()"
                :class="focusedIndex === filtered.length ? 'bg-blue-50 dark:bg-blue-900/30' : ''"
                class="w-full flex items-center gap-2 px-3 py-2 text-sm font-semibold text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all"
            >
                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                </svg>
                {{ $addNewText }}
            </button>
        </div>
        @endif
    </div>

    {{-- Hidden native input untuk validasi browser --}}
    <input type="hidden" name="{{ $wireModel }}" x-bind:value="selected" @if($required) required @endif />

    {{-- Error Message --}}
    @error($wireModel)
        <p class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
            <svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>
