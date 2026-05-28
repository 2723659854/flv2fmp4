<?php

require_once 'php/mp4/MP4Remux.php';

// Create a test video meta
$videoMeta = [
    'type' => 'video',
    'codecWidth' => 720,
    'codecHeight' => 742,
    'avcc' => [0x01, 0x67, 0x4D, 0x40, 0x1F, 0xFF, 0xE1, 0x00, 0x1E, 0x67, 0x64, 0x00, 0x1F, 0xAC, 0xD9, 0x40, 0xB4, 0x17, 0xFE, 0x6C, 0x05, 0xA8, 0x08, 0x08, 0x0A, 0x00, 0x00, 0x03, 0x00, 0x02, 0x00, 0x00, 0x03, 0x00, 0x78, 0x1E, 0x30, 0x63, 0x2C, 0x01, 0x00, 0x06, 0x68, 0xEB, 0xE3, 0xCB, 0x22, 0xC0, 0xFD, 0xF8, 0xF8],
    'timescale' => 1000,
    'duration' => 0,
    'id' => 1
];

$metas = [$videoMeta];

echo "Generating init segment...\n";
$initSegment = MP4::generateInitSegment($metas);

echo "\nFull init segment hex dump (first 200 bytes):\n";
for ($i = 0; $i < min(200, count($initSegment)); $i++) {
    printf("%02X ", $initSegment[$i]);
    if (($i + 1) % 16 == 0) {
        echo "\n";
    }
}

// Parse boxes properly
echo "\n\nParsing box structure:\n";
$offset = 0;
while ($offset < count($initSegment) - 8) {
    $size = ($initSegment[$offset] << 24) | ($initSegment[$offset+1] << 16) | ($initSegment[$offset+2] << 8) | $initSegment[$offset+3];
    $type = chr($initSegment[$offset+4]) . chr($initSegment[$offset+5]) . chr($initSegment[$offset+6]) . chr($initSegment[$offset+7]);
    
    echo "Offset: $offset, Size: $size, Type: '$type'\n";
    
    if ($type == 'avc1') {
        // Width and height are at offset + 28
        $width = ($initSegment[$offset + 28] << 8) | $initSegment[$offset + 29];
        $height = ($initSegment[$offset + 30] << 8) | $initSegment[$offset + 31];
        echo "  -> Width: $width, Height: $height\n";
    }
    
    $offset += $size;
}
?>