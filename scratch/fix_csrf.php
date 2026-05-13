<?php
$files = glob(__DIR__ . "/../admin/*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    $modified = false;
    
    if (preg_match('/if\s*\(\$_SERVER\[\'REQUEST_METHOD\'\]\s*===\s*\'POST\'[^\)]*\)\s*\{/', $content)) {
        if (strpos($content, 'csrf_verify();') === false) {
            $content = preg_replace('/(if\s*\(\$_SERVER\[\'REQUEST_METHOD\'\]\s*===\s*\'POST\'[^\)]*\)\s*\{)/', "$1\n    csrf_verify();", $content);
            $modified = true;
        }
    }

    if (preg_match('/<form[^>]*method=["\']POST["\'][^>]*>/i', $content)) {
        if (strpos($content, 'csrf_field()') === false) {
            $content = preg_replace('/(<form[^>]*method=["\']POST["\'][^>]*>)/i', "$1\n    <?= csrf_field() ?>", $content);
            $modified = true;
        }
    }

    if ($modified) {
        file_put_contents($file, $content);
        echo "Modified " . basename($file) . "\n";
    }
}
