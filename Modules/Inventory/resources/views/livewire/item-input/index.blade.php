<?php
use function Livewire\Volt\layout;

layout('layouts::app', ['title' => 'Master Data Inventory']);
?>

<div class="p-0">

    <div class="mx-auto">
        <livewire:item-input.item-list />
        <livewire:item-input.item-form />
    </div>


    <!-- Print Label Modal -->
    <livewire:print-label-modal />
</div>