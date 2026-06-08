<?php
use function Livewire\Volt\layout;

layout('layouts::app', ['title' => 'Pengelolaan Gudang']);
?>

<div>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <livewire:warehouse.warehouse-list />
    </div>
</div>
