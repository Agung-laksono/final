<?php

function replaceInDir($dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            
            // Replace Modules\Workspace\app with Modules\Workspace
            $newContent = str_replace('Modules\Workspace\app', 'Modules\Workspace', $content);
            
            if ($newContent !== $content) {
                file_put_contents($file->getPathname(), $newContent);
                echo "Fixed " . $file->getPathname() . "\n";
            }
        }
    }
}

replaceInDir(__DIR__ . '/Modules/Workspace');
replaceInDir(__DIR__ . '/resources/views');
replaceInDir(__DIR__ . '/routes');

echo "Namespace fixed!\n";
