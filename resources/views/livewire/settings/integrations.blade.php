<?php

use function Livewire\Volt\{state, mount};
use App\Models\Setting;
use App\Events\SettingUpdated;
use Illuminate\Support\Facades\Cache;

state([
    'clarityId' => '',
    'gudangHandlesShipping' => false,
]);

mount(function () {
    $this->clarityId = Setting::where('key', 'clarity_id')->value('value') ?? '';
    $this->gudangHandlesShipping = Setting::where('key', 'gudang_handles_shipping')->value('value') == '1';
});

$save = function () {
    Setting::updateOrCreate(
        ['key' => 'clarity_id'],
        ['value' => $this->clarityId]
    );
    
    $shippingValue = $this->gudangHandlesShipping ? '1' : '0';
    Setting::updateOrCreate(
        ['key' => 'gudang_handles_shipping'],
        ['value' => $shippingValue]
    );

    // Cache agar tidak query DB terus (1 jam)
    Cache::put('setting_gudang_handles_shipping', $shippingValue, now()->addHour());
    Cache::forget('setting_clarity_id');

    // Broadcast ke semua client yang sedang membuka halaman Kanban
    SettingUpdated::safeDispatch('gudang_handles_shipping', $shippingValue);

    \Flux::toast('Pengaturan berhasil disimpan!');
};

?>

<x-pages::settings.layout :heading="__('Pengaturan & Integrasi')" :subheading="__('Kelola integrasi pihak ketiga dan pengaturan operasional.')">
    
    <form wire:submit="save" class="space-y-8">
        
        <!-- Bagian Integrasi -->
        <div class="space-y-4">
            <flux:heading size="lg">Microsoft Clarity</flux:heading>
            <flux:subheading>
                Masukkan Project ID (Tracking ID) dari Microsoft Clarity untuk merekam sesi dan interaksi pengguna di aplikasi.
            </flux:subheading>

            <flux:input 
                wire:model="clarityId" 
                label="Clarity Tracking ID" 
                placeholder="Contoh: x4qbnvo0dw" 
                description="Biarkan kosong untuk menonaktifkan pelacakan Microsoft Clarity."
            />
        </div>

        <flux:separator />

        <!-- Bagian Operasional -->
        <div class="space-y-4">
            <flux:heading size="lg">Operasional Gudang</flux:heading>
            <flux:subheading>
                Atur kewenangan dan alur kerja untuk tim gudang dan pemenuhan pesanan.
            </flux:subheading>

            <flux:switch wire:model="gudangHandlesShipping" label="Gudang Menangani Ekspedisi" description="Jika diaktifkan, tim Gudang bisa menginput resi dan menyerahkan pesanan ke kurir. Jika dinonaktifkan, urusan ekspedisi akan ditangani sepenuhnya oleh tim Sales/Admin." />
        </div>

        <div class="flex items-center gap-4 pt-4">
            <flux:button icon="check" variant="primary" type="submit"> Simpan Peng </flux:button>
            <flux:text class="text-sm">Perubahan akan langsung diterapkan ke seluruh aplikasi.</flux:text>
        </div>
    </form>

</x-pages::settings.layout>
