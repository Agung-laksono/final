<?php
$allSubtasks = [
    133 => ['id' => 133, 'parent_id' => 134],
    134 => ['id' => 134, 'parent_id' => 133],
];
$itemId = 133;
$newParentId = 134;

$curr = $newParentId;
$visited = [];
$i = 0;
while ($curr) {
    echo "Curr: $curr\n";
    if ((string)$curr === (string)$itemId || isset($visited[$curr])) {
        echo "Reverting! Cycle detected.\n";
        break;
    }
    $visited[$curr] = true;
    $curr = $allSubtasks[$curr]['parent_id'] ?? null;
    
    $i++;
    if ($i > 10) {
        echo "INFINITE LOOP DETECTED!\n";
        break;
    }
}
echo "Done.\n";
