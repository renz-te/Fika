<?php
$files = glob(__DIR__ . '/*.php');
foreach ($files as $f) {
    $content = file_get_contents($f);
    $newContent = str_replace('hourly_rate', 'hourly_rate', $content);
    if ($content !== $newContent) {
        file_put_contents($f, $newContent);
        echo "Replaced in: " . basename($f) . "\n";
    }
}
echo "Done.\n";
