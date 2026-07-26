<?php
$content = file_get_contents('resources/views/livewire/global/item-gallery-modal.blade.php');

// We need to remove from `<flux:modal name="select-variant-modal"` up to `</flux:modal>` just before `@script`.
$startPos = strpos($content, '<flux:modal name="select-variant-modal"');
if ($startPos !== false) {
    $scriptPos = strpos($content, '@script', $startPos);
    if ($scriptPos !== false) {
        // Find the `</flux:modal>` before `@script`
        $endPos = strrpos(substr($content, 0, $scriptPos), '</flux:modal>');
        if ($endPos !== false) {
            $endPos += strlen('</flux:modal>');
            $modalContent = substr($content, $startPos, $endPos - $startPos);
            $content = str_replace($modalContent, '', $content);
            file_put_contents('resources/views/livewire/global/item-gallery-modal.blade.php', $content);
            echo "Removed select-variant-modal.\n";
        } else {
            echo "End pos not found.\n";
        }
    } else {
        echo "@script not found.\n";
    }
} else {
    echo "Modal not found.\n";
}
