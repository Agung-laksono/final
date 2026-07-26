<?php
$files = [
    "Modules/CMS/resources/views/livewire/post-index.blade.php",
    "Modules/Inventory/resources/views/livewire/item-history-movement/movement-list.blade.php",
    "Modules/Inventory/resources/views/livewire/item-input/item-form.blade.php",
    "Modules/Inventory/resources/views/livewire/item-input/item-label-list.blade.php",
    "Modules/Inventory/resources/views/livewire/item-input/item-list.blade.php",
    "Modules/Inventory/resources/views/livewire/item-opname/history-modal.blade.php",
    "Modules/Inventory/resources/views/livewire/item-transfer/transfer-list.blade.php",
    "Modules/Production/resources/views/livewire/recipe/index.blade.php",
    "Modules/Production/resources/views/livewire/work-order/kanban.blade.php",
    "Modules/Purchase/resources/views/livewire/order/kanban.blade.php",
    "Modules/Purchase/resources/views/livewire/queue/kanban.blade.php",
    "Modules/Purchase/resources/views/livewire/returns/index.blade.php",
    "Modules/Purchase/resources/views/livewire/vendor/vendor-detail-modal.blade.php",
    "Modules/Sales/resources/views/livewire/quotation/index.blade.php",
    "Modules/Sales/resources/views/livewire/returns/index.blade.php",
    "Modules/Sales/resources/views/livewire/sales-order/index.blade.php",
    "resources/views/livewire/settings/roles.blade.php",
    "resources/views/livewire/settings/users.blade.php"
];

foreach ($files as $f) {
    $path = "C:/Users/HP/final/" . $f;
    if (file_exists($path)) {
        $c = file_get_contents($path);
        if (strpos($c, "class=\"table-mobile-cards\"") === false) {
            $c = str_replace("<flux:table>", "<x-table.wrapper>\n    <flux:table class=\"table-mobile-cards\">", $c);
            $c = str_replace("</flux:table>", "</flux:table>\n</x-table.wrapper>", $c);
            file_put_contents($path, $c);
            echo "Updated $f\n";
        }
    }
}

