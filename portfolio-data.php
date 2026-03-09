<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120'); // cache 2 min no browser

$dir          = __DIR__ . '/uploads/portfolio/';
$titles_file  = $dir . 'titles.json';
$allowed_ext  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$images       = [];

// Load titles map
$titles = [];
if (is_file($titles_file)) {
    $data = json_decode(file_get_contents($titles_file), true);
    if (is_array($data)) $titles = $data;
}

if (is_dir($dir)) {
    $files = @scandir($dir);
    if ($files) {
        sort($files);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext, true)) continue;
            $filepath = $dir . $file;
            if (!@getimagesize($filepath)) continue;
            // Use saved title or fallback to cleaned filename
            if (!empty($titles[$file])) {
                $label = $titles[$file];
            } else {
                $name  = pathinfo($file, PATHINFO_FILENAME);
                $label = ucwords(preg_replace('/[p_\d]+[\-_]+|[\-_]+/', ' ', $name));
                $label = trim($label);
            }
            $images[] = [
                'src'   => 'uploads/portfolio/' . rawurlencode($file),
                'alt'   => $label,
                'label' => $label,
            ];
        }
    }
}

echo json_encode(['images' => $images], JSON_UNESCAPED_UNICODE);
