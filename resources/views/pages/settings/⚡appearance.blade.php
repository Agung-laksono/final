<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Cookie;

new #[Title('Appearance settings')] class extends Component {
    public string $layoutMode = 'floating';

    public function mount()
    {
        $this->layoutMode = request()->cookie('layout_mode', 'floating');
    }

    public function updatedLayoutMode($value)
    {
        // Save to cookie for 1 year
        Cookie::queue('layout_mode', $value, 60 * 24 * 365);
        
        // Reload the page to apply the layout change correctly
        return redirect()->route('appearance.edit');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" label="Tema Aplikasi">
            <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
        </flux:radio.group>

        <flux:separator class="my-6" />

        <flux:radio.group wire:model.live="layoutMode" variant="segmented" label="Layout Navigasi">
            <flux:radio value="sidebar" icon="bars-4">Sidebar Klasik</flux:radio>
            <flux:radio value="floating" icon="squares-2x2">Multi-Level Speed Dial</flux:radio>
        </flux:radio.group>
        
        <div class="mt-2 text-sm text-zinc-500">
            Pilihan layout navigasi ini disimpan di *browser* Anda (Cookie).
        </div>

    </x-pages::settings.layout>
</section>
