<?php
$dirs = ['app', 'config', 'database', 'resources', 'routes', 'tests'];

foreach ($dirs as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php'])) {
            $content = file_get_contents($file->getPathname());
            
            $newContent = str_replace(
                ["'keuangan'", '"keuangan"', 'role:kasir,keuangan', 'role:keuangan'],
                ["'admin'", '"admin"', 'role:kasir,admin', 'role:admin'],
                $content
            );
            
            if ($content !== $newContent) {
                file_put_contents($file->getPathname(), $newContent);
                echo "Updated: " . $file->getPathname() . "\n";
            }
        }
    }
}
echo "Done.\n";
