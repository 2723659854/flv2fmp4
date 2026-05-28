<?php

require_once 'php/mp4/MP4Remux.php';

// Create a test video meta like the one in the real conversion
$videoMeta = [
    'type' => 'video',
    'codecWidth' => 720,
    'codecHeight' => 742,
    'avcc' => [0x01, 0x67, 0x4D, 0x40, 0x1F, 0xFF, 0xE1, 0x00, 0x1E, 0x67, 0x64, 0x00, 0x1F, 0xAC, 0xD9, 0x40, 0xB4, 0x17, 0xFE, 0x6C, 0x05, 0xA8, 0x08, 0x08, 0x0A, 0x00, 0x00, 0x03, 0x00, 0x02, 0x00, 0x00, 0x03, 0x00, 0x78, 0x1E, 0x30, 0x63, 0x2C, 0x01, 0x00, 0x06, 0x68, 0xEB, 0xE3, 0xCB, 0x22, 0xC0, 0xFD, 0xF8, 0xF8],
    'timescale' => 1000,
    'duration' => 0,
    'id' => 1
];

$audioMeta = [
    'type' => 'audio',
    'sampleRate' => 44100,
    'channels' => 2,
    'timescale' => 44100,
    'duration' => 0,
    'id' => 2
];

$metas = [$videoMeta, $audioMeta];

echo "Generating init segment...\n";
$initSegment = MP4::generateInitSegment($metas);

echo "Init segment size: " . count($initSegment) . " bytes\n";

// Find avc1 box in the generated data
echo "\nSearching for avc1 box in generated data...\n";
for ($i = 0; $i < count($initSegment) - 4; $i++) {
    if ($initSegment[$i] == 0x61 && $initSegment[$i+1] == 0x76 && $initSegment[$i+2] == 0x63 && $initSegment[$i+3] == 0x31) {
        echo "Found avc1 at offset $i\n";
        $width = ($initSegment[$i + 28] << 8) | $initSegment[$i + 29];
        $height = ($initSegment[$i + 30] << 8) | $initSegment[$i + 31];
        echo "Width: $width, Height: $height\n";
        
        // Show hex around width/height
        echo "Bytes around width/height: ";
        for ($j = $i + 24; $j < $i + 36; $j++) {
            echo dechex($initSegment[$j]) . " ";
        }
        echo "\n";
        break;
    }
}

// Save to a test file
file_put_contents(__DIR__.'/output/test_init.mp4', pack('C*', ...$initSegment));
echo "\nSaved to test_init.mp4\n";
?>