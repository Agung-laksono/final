<?php
use function Livewire\Volt\{state, layout, title};

layout('layouts.app');
title('Master Vendor');
?>

<div>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">Master Data Vendor</flux:heading>
            
            <livewire:vendor.vendor-form />
        </div>

        <livewire:vendor.vendor-list />
    </div>
</div>
