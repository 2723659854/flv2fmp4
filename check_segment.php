<?php

$segmentFile = __DIR__.'/output/segment_1.m4s';
$data = file_get_contents($segmentFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "Segment 1 file size: " . count($array) . " bytes\n";
echo "First 50 bytes: " . implode(', ', array_slice($array, 0, 50)) . "\n";

// Find mdat box
for ($i = 0; $i < count($array) - 4; $i++) {
    if ($array[$i] == 0x6D && $array[$i+1] == 0x64 && $array[$i+2] == 0x61 && $array[$i+3] == 0x74) {
        $size = ($array[$i-4] << 24) | ($array[$i-3] << 16) | ($array[$i-2] << 8) | $array[$i-1];
        echo "Found mdat at offset $i, size: $size\n";
        echo "mdat data starts at offset " . ($i + 8) . "\n";
        echo "First 50 bytes of mdat data: " . implode(', ', array_slice($array, $i + 8, 50)) . "\n";
        break;
    }
}
?>