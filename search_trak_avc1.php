<?php

$outputFile = __DIR__.'/output/output.mp4';

if (!file_exists($outputFile)) {
    echo "output.mp4 not found!\n";
    exit;
}

$data = file_get_contents($outputFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "Searching for 'trak' and 'avc1' patterns...\n";

// Search for trak pattern
for ($i = 0; $i < count($array) - 4; $i++) {
    if ($array[$i] == 0x74 && $array[$i+1] == 0x72 && $array[$i+2] == 0x61 && $array[$i+3] == 0x6B) {
        echo "\nFound 'trak' at offset $i\n";
        
        // Check the size before trak (4 bytes before)
        $size = ($array[$i-4] << 24) | ($array[$i-3] << 16) | ($array[$i-2] << 8) | $array[$i-1];
        echo "trak box size: $size\n";
        
        // Look for avc1 inside this trak
        $endOffset = $i + $size;
        for ($j = $i; $j < min($endOffset, $i + 500) && $j < count($array) - 4; $j++) {
            if ($array[$j] == 0x61 && $array[$j+1] == 0x76 && $array[$j+2] == 0x63 && $array[$j+3] == 0x31) {
                echo "  Found 'avc1' inside trak at offset $j\n";
                
                // Check width/height at offset + 28
                if ($j + 31 < count($array)) {
                    $width = ($array[$j + 28] << 8) | $array[$j + 29];
                    $height = ($array[$j + 30] << 8) | $array[$j + 31];
                    echo "  Width: $width, Height: $height\n";
                }
                break;
            }
        }
    }
}

// Also check for avcC box content
echo "\n\nChecking avcC box content...\n";
for ($i = 0; $i < count($array) - 4; $i++) {
    if ($array[$i] == 0x61 && $array[$i+1] == 0x76 && $array[$i+2] == 0x63 && $array[$i+3] == 0x43) {
        echo "Found 'avcC' at offset $i\n";
        
        // avcC starts with version (1 byte), profile (1 byte), profile_compatibility (1 byte), level (1 byte)
        if ($i + 4 < count($array)) {
            $version = $array[$i + 8];
            $profile = $array[$i + 9];
            $level = $array[$i + 11];
            
            echo "  Version: $version\n";
            echo "  Profile: 0x" . dechex($profile) . "\n";
            echo "  Level: 0x" . dechex($level) . "\n";
        }
    }
}
?>