<?php

$initFile = __DIR__.'/output/init.mp4';
$data = file_get_contents($initFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "Init file size: " . count($array) . " bytes\n";

// Search for all boxes
echo "\nBox structure:\n";
$offset = 0;
while ($offset < count($array) - 8) {
    $size = ($array[$offset] << 24) | ($array[$offset+1] << 16) | ($array[$offset+2] << 8) | $array[$offset+3];
    $type = chr($array[$offset+4]) . chr($array[$offset+5]) . chr($array[$offset+6]) . chr($array[$offset+7]);
    
    echo "  Offset: $offset, Size: $size, Type: $type\n";
    
    if ($type == 'avc1') {
        echo "    Found avc1 box!\n";
        // Check width/height at offset + 28
        if ($offset + 31 < count($array)) {
            $width = ($array[$offset + 28] << 8) | $array[$offset + 29];
            $height = ($array[$offset + 30] << 8) | $array[$offset + 31];
            echo "    Width: $width, Height: $height\n";
            echo "    Raw bytes: ";
            for ($i = $offset + 28; $i < min($offset + 36, count($array)); $i++) {
                echo dechex($array[$i]) . " ";
            }
            echo "\n";
        }
    }
    
    $offset += $size;
}

// Also check if avc1 data is present but with wrong offset
echo "\nSearching for avc1 content pattern (0x67 0x31 0x31 0x31 which is 'g111'):\n";
for ($i = 0; $i < count($array) - 4; $i++) {
    if ($array[$i] == 0x67 && $array[$i+1] == 0x31 && $array[$i+2] == 0x31 && $array[$i+3] == 0x31) {
        echo "Found 'g111' pattern at offset $i\n";
        // This should be in avc1 box, check nearby width/height
        // width/height should be 8 bytes before 'g111'
        if ($i - 8 >= 0) {
            $width = ($array[$i - 8] << 8) | $array[$i - 7];
            $height = ($array[$i - 6] << 8) | $array[$i - 5];
            echo "Width at offset " . ($i-8) . ": $width\n";
            echo "Height at offset " . ($i-6) . ": $height\n";
        }
    }
}
?>