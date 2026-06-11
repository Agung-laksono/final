@props([
    'align' => 'right',
    'appearance' => 'default',
])

@php
    $inputClass = "w-full py-1.5 pl-8 pr-3 text-[13px] font-bold text-[#1a2b4c] dark:text-zinc-100 ";
    
    if ($align === 'center') $inputClass .= 'text-center ';
    elseif ($align === 'left') $inputClass .= 'text-left ';
    else $inputClass .= 'text-right ';

    if ($appearance === 'transparent') {
        $inputClass .= 'bg-transparent border-none focus:ring-0 shadow-none ';
    } else {
        $inputClass .= 'bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 focus:ring-1 focus:ring-cyan-500 rounded-lg shadow-sm ';
    }
@endphp

<div x-data="{
        raw: null,
        formatted: '',
        init() {
            this.$watch('raw', value => {
                if (document.activeElement !== this.$refs.input) {
                    this.formatted = this.format(value);
                }
            });
            setTimeout(() => {
                this.formatted = this.format(this.raw);
            }, 0);
        },
        format(value) {
            if (value === null || value === '' || value === undefined) return '';
            let val = value.toString().replace(/\D/g, '');
            if (val === '') return '';
            return new Intl.NumberFormat('id-ID').format(val);
        },
        onInput(event) {
            let cursorPosition = event.target.selectionStart;
            let originalLength = event.target.value.length;

            let val = event.target.value.replace(/\D/g, '');
            
            this.raw = val === '' ? null : parseInt(val, 10);
            this.formatted = this.format(val);
            event.target.value = this.formatted;
            
            let newLength = this.formatted.length;
            let newCursorPosition = cursorPosition + (newLength - originalLength);
            event.target.setSelectionRange(newCursorPosition, newCursorPosition);
        }
    }"
    x-modelable="raw"
    {{ $attributes->whereStartsWith('x-model') }}
    {{ $attributes->whereStartsWith('wire:model') }}
    class="relative flex items-center {{ $attributes->get('class') }}"
>
    <span class="absolute left-3 text-xs font-semibold text-zinc-400 z-10 pointer-events-none">Rp</span>
    
    <input 
        x-ref="input"
        type="text" 
        :value="formatted"
        @input="onInput($event)"
        {{ $attributes->except(['class', 'x-model', 'wire:model', 'align', 'appearance']) }}
        class="{{ $inputClass }}" 
    />
</div>
