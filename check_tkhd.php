<?php

$outputFile = __DIR__.'/output/output.mp4';

if (!file_exists($outputFile)) {
    echo "output.mp4 not found!\n";
    exit;
}

$data = file_get_contents($outputFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "Searching for tkhd boxes...\n";

$offset = 0;
while ($offset < count($array) - 8) {
    $size = ($array[$offset] << 24) | ($array[$offset+1] << 16) | ($array[$offset+2] << 8) | $array[$offset+3];
    $type = chr($array[$offset+4]) . chr($array[$offset+5]) . chr($array[$offset+6]) . chr($array[$offset+7]);
    
    if ($type == 'tkhd') {
        echo "\nFound tkhd box at offset $offset\n";
        
        // tkhd version is at offset + 8
        $version = $array[$offset + 8];
        echo "Version: $version\n";
        
        // Width and height are at offset + 8 + 8 + 32 = 48 (for version 0)
        // or offset + 8 + 12 + 32 = 52 (for version 1)
        $widthOffset = $offset + 8 + ($version == 0 ? 8 : 12) + 32;
        $heightOffset = $widthOffset + 4;
        
        if ($widthOffset + 7 < count($array)) {
            $width = ($array[$widthOffset] << 24) | ($array[$widthOffset+1] << 16) | ($array[$widthOffset+2] << 8) | $array[$widthOffset+3];
            $height = ($array[$heightOffset] << 24) | ($array[$heightOffset+1] << 16) | ($array[$heightOffset+2] << 8) | $array[$heightOffset+3];
            
            // In tkhd, width and height are fixed-point 16.16 format
            $widthFloat = $width / 65536.0;
            $heightFloat = $height / 65536.0;
            
            echo "Width (fixed-point): $width -> $widthFloat\n";
            echo "Height (fixed-point): $height -> $heightFloat\n";
        }
    }
    
    $offset += $size;
}

// Check for sdtp boxes
echo "\n\nSearching for sdtp boxes (to check for duplicates)...\n";
$sdtpCount = 0;
$offset = 0;
while ($offset < count($array) - 8) {
    $size = ($array[$offset] << 24) | ($array[$offset+1] << 16) | ($array[$offset+2] << 8) | $array[$offset+3];
    $type = chr($array[$offset+4]) . chr($array[$offset+5]) . chr($array[$offset+6]) . chr($array[$offset+7]);
    
    if ($type == 'sdtp') {
        $sdtpCount++;
        echo "Found sdtp box #$sdtpCount at offset $offset\n";
    }
    
    $offset += $size;
}

echo "\nTotal sdtp boxes found: $sdtpCount\n";
?>