<?php
$content = file_get_contents('resources/views/livewire/global/item-gallery-modal.blade.php');

// 1. Add expandedItemIds and toggleVariants
$classUpdates = <<<PHP
    public \$lastContext = null;

    public \$expandedItemIds = [];

    public function toggleVariants(\$itemId)
    {
        if (in_array(\$itemId, \$this->expandedItemIds)) {
            \$this->expandedItemIds = array_diff(\$this->expandedItemIds, [\$itemId]);
        } else {
            \$this->expandedItemIds[] = \$itemId;
        }
    }
PHP;
$content = str_replace('    public $lastContext = null;', $classUpdates, $content);

// 2. Remove Alpine data for the modal
$xDataOld = <<<JS
                x-data="{ 
                    activeVariants: {},
                    openVariantModal(item, hasVariants, variantsList) {
                        this.currentItem = item;
                        if (hasVariants) {
                            this.variantsList = variantsList;
                            \$flux.modal('select-variant-modal').show();
                        } else {
                            this.selectStandard();
                        }
                    },
                    currentItem: null,
                    variantsList: [],
                    formatRupiah(number) {
                        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(number);
                    },
                    selectStandard() {
                        playSelectSound();
                        \$dispatch('item-selected', { item: this.currentItem });
                        this.activeVariants[this.currentItem.item_id] = null;
                        \$flux.modal('select-variant-modal').close();
                    },
                    selectVariant(v) {
                        playSelectSound();
                        \$dispatch('item-selected', { item: {
                            item_id: this.currentItem.item_id,
                            name: this.currentItem.name,
                            code: this.currentItem.code,
                            unit_price: v.unit_price || this.currentItem.unit_price,
                            image: v.image_raw || this.currentItem.image,
                            unit: this.currentItem.unit,
                            has_history: true,
                            custom_attributes: v.custom_attributes || [],
                            custom_attachments: v.custom_attachments || [],
                            note: v.note ? v.note + '<br><strong>Salinan Pesanan: ' + v.customer + '</strong>' : '<strong>Salinan Pesanan: ' + v.customer + '</strong>'
                        } });
                        this.activeVariants[this.currentItem.item_id] = v;
                        \$flux.modal('select-variant-modal').close();
                    }
                }"
JS;

$xDataNew = <<<JS
                x-data="{ 
                    activeVariants: {},
                    formatRupiah(number) {
                        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(number);
                    }
                }"
JS;
$content = str_replace($xDataOld, $xDataNew, $content);

file_put_contents('resources/views/livewire/global/item-gallery-modal.blade.php', $content);
echo "Replaced class properties and x-data.\n";
