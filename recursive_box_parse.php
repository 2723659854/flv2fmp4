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

// Recursive box parser
function parseBoxes($data, $offset = 0, $indent = 0) {
    while ($offset < count($data) - 8) {
        $size = ($data[$offset] << 24) | ($data[$offset+1] << 16) | ($data[$offset+2] << 8) | $data[$offset+3];
        $type = chr($data[$offset+4]) . chr($data[$offset+5]) . chr($data[$offset+6]) . chr($data[$offset+7]);
        
        $padding = str_repeat('  ', $indent);
        echo $padding . "Offset: $offset, Size: $size, Type: '$type'\n";
        
        if ($type == 'avc1') {
            // Width and height are at offset + 28
            $width = ($data[$offset + 28] << 8) | ($data[$offset + 29]);
            $height = ($data[$offset + 30] << 8) | ($data[$offset + 31]);
            echo $padding . "  -> Width: $width, Height: $height\n";
            
            // Show bytes around width/height
            echo $padding . "  Bytes at offset " . ($offset + 28) . ": ";
            for ($i = $offset + 24; $i < min($offset + 36, $offset + $size); $i++) {
                printf("%02X ", $data[$i]);
            }
            echo "\n";
        }
        
        // Recursively parse known container boxes
        if (in_array($type, ['moov', 'trak', 'mdia', 'minf', 'stbl', 'stsd'])) {
            parseBoxes($data, $offset + 8, $indent + 1);
        }
        
        $offset += $size;
    }
}

echo "\nRecursive box structure:\n";
parseBoxes($initSegment);
?>