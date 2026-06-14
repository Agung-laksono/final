<?php
use function Livewire\Volt\{state, layout, title};

layout('layouts.app');
title('Master Vendor');
?>

<div>
    <livewire:vendor.vendor-list />
</div>
