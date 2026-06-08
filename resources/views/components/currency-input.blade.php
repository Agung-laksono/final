@php
    // Menarik nama variabel dari wire:model (contoh: "purchase_price")
    $wireModel = $attributes->wire('model')->value();
    
    // Jika user tidak memberikan atribut name, gunakan wire:model sebagai fallback untuk keperluan error reporting
    $inputName = $attributes->get('name', $wireModel);
@endphp

<div x-data="{ 
    val: @entangle($wireModel).live,
    format(v) { 
        if (!v && v !== 0) return ''; 
        return v.toString().replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); 
    } 
}">
    <flux:input 
        name="{{ $inputName }}"
        x-bind:value="format(val)" 
        x-on:input="val = $event.target.value.replace(/\D/g, '')"
        {{ $attributes->whereDoesntStartWith('wire:model')->except('name') }}
    >
        <x-slot name="icon">
            <span class="text-zinc-500 font-medium pl-2">Rp</span>
        </x-slot>
    </flux:input>
</div>
