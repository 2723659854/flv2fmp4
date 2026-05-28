<?php

$initFile = __DIR__.'/output/init.mp4';
$data = file_get_contents($initFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

// Find 'avc1' pattern
for ($i = 0; $i < count($array) - 4; $i++) {
    if ($array[$i] == 0x61 && $array[$i+1] == 0x76 && $array[$i+2] == 0x63 && $array[$i+3] == 0x31) {
        echo "Found avc1 at offset $i\n";
        echo "avc1 box content (first 60 bytes):\n";
        
        $hex = '';
        $ascii = '';
        for ($j = $i; $j < min($i + 60, count($array)); $j++) {
            $hex .= sprintf('%02X ', $array[$j]);
            $ascii .= ($array[$j] >= 32 && $array[$j] <= 126) ? chr($array[$j]) : '.';
            
            if ((($j - $i + 1) % 16) == 0) {
                echo $hex . '| ' . $ascii . "\n";
                $hex = '';
                $ascii = '';
            }
        }
        if ($hex) {
            echo $hex . str_repeat('   ', 16 - (strlen($hex) / 3)) . '| ' . $ascii . "\n";
        }
        
        // Width/height should be at offset + 28
        $width = ($array[$i + 28] << 8) | $array[$i + 29];
        $height = ($array[$i + 30] << 8) | $array[$i + 31];
        echo "\nWidth at offset " . ($i + 28) . ": $width (0x" . dechex($width) . ")\n";
        echo "Height at offset " . ($i + 30) . ": $height (0x" . dechex($height) . ")\n";
        break;
    }
}
?>