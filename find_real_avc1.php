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

// Parse boxes correctly and find all 'avc1' occurrences
echo "\nFinding all 'avc1' occurrences:\n";
$avc1Count = 0;
for ($i = 0; $i < count($initSegment) - 4; $i++) {
    if ($initSegment[$i] == 0x61 && $initSegment[$i+1] == 0x76 && $initSegment[$i+2] == 0x63 && $initSegment[$i+3] == 0x31) {
        $avc1Count++;
        
        // Check if this is a real box (has valid size before it)
        if ($i >= 4) {
            $size = ($initSegment[$i-4] << 24) | ($initSegment[$i-3] << 16) | ($initSegment[$i-2] << 8) | $initSegment[$i-1];
            
            // A real avc1 box should have a reasonable size (> 8)
            if ($size > 8) {
                echo "AVC1 #$avc1Count at offset $i (REAL BOX)\n";
                echo "  Size: $size bytes\n";
                
                // Check width/height at offset + 28
                if ($i + 31 < count($initSegment)) {
                    $width = ($initSegment[$i + 28] << 8) | $initSegment[$i + 29];
                    $height = ($initSegment[$i + 30] << 8) | $initSegment[$i + 31];
                    echo "  Width: $width, Height: $height\n";
                }
            } else {
                echo "AVC1 #$avc1Count at offset $i (likely just a string)\n";
            }
        } else {
            echo "AVC1 #$avc1Count at offset $i (position too early)\n";
        }
    }
}

echo "\nTotal 'avc1' occurrences: $avc1Count\n";
?>