<?php
$file = 'c:\Users\Admin\.gemini\antigravity\scratch\final\resources\views\components\load-more.blade.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);
$stack = [];
foreach($lines as $i => $line) {
    if (preg_match_all('/@(if|foreach|forelse|can|php)\b/', $line, $matches)) {
        foreach($matches[1] as $m) {
            $stack[] = [$m, $i + 1, $line];
        }
    }
    if (preg_match_all('/@(endif|endforeach|endforelse|endcan|endphp)\b/', $line, $matches)) {
        foreach($matches[1] as $m) {
            $expected = str_replace('end', '', $m);
            $last = end($stack);
            if ($last && $last[0] === $expected) {
                array_pop($stack);
            } else {
                echo "Mismatch at line " . ($i + 1) . ": found @" . $m . " but expected @end" . ($last ? $last[0] : 'none') . "\n";
                if ($last && $last[0] === 'if' && $m === 'endif') array_pop($stack);
            }
        }
    }
}
echo "Unclosed: \n";
print_r($stack);
