<?php

$outputFile = __DIR__.'/output/output.mp4';

if (!file_exists($outputFile)) {
    echo "output.mp4 not found!\n";
    exit;
}

$data = file_get_contents($outputFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "output.mp4 size: " . count($array) . " bytes\n";

// Find all boxes
echo "\nBox structure:\n";
$offset = 0;
while ($offset < count($array) - 8) {
    $size = ($array[$offset] << 24) | ($array[$offset+1] << 16) | ($array[$offset+2] << 8) | $array[$offset+3];
    $type = chr($array[$offset+4]) . chr($array[$offset+5]) . chr($array[$offset+6]) . chr($array[$offset+7]);
    
    echo "Offset: $offset (0x" . dechex($offset) . "), Size: $size, Type: $type\n";
    
    if ($type == 'avc1') {
        // Check width/height in avc1 box
        $width = ($array[$offset + 28] << 8) | $array[$offset + 29];
        $height = ($array[$offset + 30] << 8) | $array[$offset + 31];
        echo "  -> Width: $width, Height: $height\n";
    }
    
    $offset += $size;
}

// Check if there's mdat content
echo "\nChecking mdat content...\n";
$mdatFound = false;
$offset = 0;
while ($offset < count($array) - 8) {
    $size = ($array[$offset] << 24) | ($array[$offset+1] << 16) | ($array[$offset+2] << 8) | $array[$offset+3];
    $type = chr($array[$offset+4]) . chr($array[$offset+5]) . chr($array[$offset+6]) . chr($array[$offset+7]);
    
    if ($type == 'mdat') {
        $mdatFound = true;
        $mdatSize = $size - 8; // Subtract header
        echo "Found mdat box with $mdatSize bytes of media data\n";
        
        // Check if it contains H.264 NAL units (starts with 00 00 00 01)
        $mdatOffset = $offset + 8;
        for ($i = $mdatOffset; $i < $mdatOffset + min(100, $mdatSize); $i++) {
            if ($array[$i] == 0 && $array[$i+1] == 0 && $array[$i+2] == 0 && $array[$i+3] == 1) {
                echo "Found H.264 NAL unit start code at offset $i\n";
                $nalType = $array[$i+4] & 0x1F;
                echo "NAL Type: $nalType (" . getNalTypeName($nalType) . ")\n";
                break;
            }
        }
    }
    
    $offset += $size;
}

if (!$mdatFound) {
    echo "No mdat box found!\n";
}

function getNalTypeName($type) {
    switch ($type) {
        case 1: return 'Coded slice of a non-IDR picture';
        case 5: return 'Coded slice of an IDR picture';
        case 6: return 'Supplemental enhancement information (SEI)';
        case 7: return 'Sequence parameter set (SPS)';
        case 8: return 'Picture parameter set (PPS)';
        default: return 'Unknown';
    }
}
?>