@props([
    'name' => null,
    'desktopWidth' => 'md:w-[680px]', // Default lebar di desktop
    'mobileWidth' => 'w-[92vw]',      // Default lebar di mobile (memberi padding)
    'spacing' => 'space-y-3',         // Jarak antar elemen default
])

{{-- 
    Komponen Wrapper untuk flux:modal
    Tujuan: Menyeragamkan tampilan modal (seperti margin di mobile) 
    sekaligus tetap fleksibel jika ingin di-override parameter lebarnya per-halaman.
--}}
@php
    $modalClasses = $mobileWidth . ' ' . $desktopWidth . ' ' . $spacing;
@endphp
<flux:modal 
    :name="$name" 
    {{ $attributes->merge(['class' => $modalClasses]) }}
>
    {{ $slot }}
</flux:modal>
