<?php

use function Livewire\Volt\{state, mount};
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

state([
    'clarityId' => '',
]);

mount(function () {
    $this->clarityId = Setting::where('key', 'clarity_id')->value('value') ?? '';
});

$save = function () {
    Setting::updateOrCreate(
        ['key' => 'clarity_id'],
        ['value' => $this->clarityId]
    );

    // Clear the cache if we were using it for settings
    Cache::forget('setting_clarity_id');

    \Flux::toast('Pengaturan Integrasi berhasil disimpan!');
};

?>

<x-pages::settings.layout :heading="__('Integrations')" :subheading="__('Kelola integrasi dengan layanan pihak ketiga seperti Analytics dan alat pantau.')">
    
    <form wire:submit="save" class="space-y-6">
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

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">Simpan Pengaturan</flux:button>
            <flux:text class="text-sm">Perubahan akan langsung diterapkan ke seluruh aplikasi.</flux:text>
        </div>
    </form>

</x-pages::settings.layout>
