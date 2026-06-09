<?php
use function Livewire\Volt\layout;

layout('layouts::app', ['title' => 'Master Data Inventory']);
?>

<div class="p-0">

    <div class="mx-auto">
        <livewire:item-input.item-list />
        <livewire:item-input.item-form />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <livewire:item-input.unit-form />
        <livewire:item-input.type-form />
        <livewire:item-input.category-form />
        <livewire:item-input.sub-category-form />
        
    </div>
</div>