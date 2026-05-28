<?php

ini_set('memory_limit', '512M');

$initFile = __DIR__.'/output/init.mp4';
$data = file_get_contents($initFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "Init file size: " . count($array) . " bytes\n";
echo "First 50 bytes: " . implode(', ', array_slice($array, 0, 50)) . "\n";

// Find avcC box
for ($i = 0; $i < count($array) - 4; $i++) {
    if ($array[$i] == 0x61 && $array[$i+1] == 0x76 && $array[$i+2] == 0x63 && $array[$i+3] == 0x43) {
        $size = ($array[$i-4] << 24) | ($array[$i-3] << 16) | ($array[$i-2] << 8) | $array[$i-1];
        echo "Found avcC at offset $i, size: $size\n";
        echo "avcC content: " . implode(', ', array_slice($array, $i, min($size, 50))) . "\n";
        break;
    }
}

// Find moov box
for ($i = 0; $i < count($array) - 4; $i++) {
    if ($array[$i] == 0x6D && $array[$i+1] == 0x6F && $array[$i+2] == 0x6F && $array[$i+3] == 0x76) {
        $size = ($array[$i-4] << 24) | ($array[$i-3] << 16) | ($array[$i-2] << 8) | $array[$i-1];
        echo "Found moov at offset $i, size: $size\n";
        break;
    }
}
?>