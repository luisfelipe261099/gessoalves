<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$dir = __DIR__ . '/uploads/portfolio/';
$images = [];

if (is_dir($dir)) {
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $files = @scandir($dir);
    if ($files) {
        sort($files);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext, true)) continue;
            $filepath = $dir . $file;
            // Validate it's actually an image
            $info = @getimagesize($filepath);
            if (!$info) continue;
            $name    = pathinfo($file, PATHINFO_FILENAME);
            $label   = ucwords(preg_replace('/[\-_]+/', ' ', $name));
            $images[] = [
                'src'   => 'uploads/portfolio/' . rawurlencode($file),
                'alt'   => $label,
                'label' => $label,
            ];
        }
    }
}

echo json_encode(['images' => $images], JSON_UNESCAPED_UNICODE);
