<?php

$initFile = __DIR__.'/output/init.mp4';
$data = file_get_contents($initFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "Init file size: " . count($array) . " bytes\n";

// Find avc1 box
for ($i = 0; $i < count($array) - 4; $i++) {
    if ($array[$i] == 0x61 && $array[$i+1] == 0x76 && $array[$i+2] == 0x63 && $array[$i+3] == 0x31) {
        $size = ($array[$i-4] << 24) | ($array[$i-3] << 16) | ($array[$i-2] << 8) | $array[$i-1];
        echo "Found avc1 at offset $i, size: $size\n";

        // Width is at offset + 24 (after reserved fields)
        $width = ($array[$i + 24] << 8) | $array[$i + 25];
        $height = ($array[$i + 26] << 8) | $array[$i + 27];
        echo "Width: $width, Height: $height\n";
        echo "avc1 content around width/height: " . implode(', ', array_slice($array, $i + 20, 20)) . "\n";
        break;
    }
}
?>