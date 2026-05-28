<?php

$initFile = __DIR__.'/output/init.mp4';
$data = file_get_contents($initFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "Init file size: " . count($array) . " bytes\n";

// Search for avc1 box
for ($i = 0; $i < count($array) - 8; $i++) {
    // Check for 'avc1' pattern
    if ($array[$i+4] == 0x61 && $array[$i+5] == 0x76 && $array[$i+6] == 0x63 && $array[$i+7] == 0x31) {
        // Found avc1
        $size = ($array[$i] << 24) | ($array[$i+1] << 16) | ($array[$i+2] << 8) | $array[$i+3];
        echo "Found avc1 at offset $i, size: $size bytes\n";

        // avc1 structure:
        // bytes 0-3: size
        // bytes 4-7: type ('avc1')
        // bytes 8-11: reserved
        // bytes 12-15: data_reference_index
        // bytes 16-19: pre_defined
        // bytes 20-23: pre_defined
        // bytes 24-27: pre_defined
        // bytes 28-29: width (2 bytes)
        // bytes 30-31: height (2 bytes)
        
        $width = ($array[$i + 28] << 8) | $array[$i + 29];
        $height = ($array[$i + 30] << 8) | $array[$i + 31];
        echo "Width: $width, Height: $height\n";
        
        // Print raw bytes around width/height
        echo "Raw bytes at offset " . ($i + 28) . ": ";
        for ($j = $i + 28; $j < min($i + 36, count($array)); $j++) {
            echo dechex($array[$j]) . " ";
        }
        echo "\n";
        
        break;
    }
}

// Also check mvhd for timescale
echo "\nChecking mvhd box:\n";
for ($i = 0; $i < count($array) - 8; $i++) {
    if ($array[$i+4] == 0x6D && $array[$i+5] == 0x76 && $array[$i+6] == 0x68 && $array[$i+7] == 0x64) {
        $size = ($array[$i] << 24) | ($array[$i+1] << 16) | ($array[$i+2] << 8) | $array[$i+3];
        echo "Found mvhd at offset $i, size: $size\n";
        
        // timescale is at offset + 20 (for version 0)
        $timescale = ($array[$i + 20] << 24) | ($array[$i + 21] << 16) | ($array[$i + 22] << 8) | $array[$i + 23];
        echo "Timescale: $timescale\n";
        break;
    }
}
?>