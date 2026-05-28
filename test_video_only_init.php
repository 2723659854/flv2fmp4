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

$metas = [$videoMeta];

echo "Generating init segment with only video...\n";
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
            printf("%02X ", $initSegment[$j]);
        }
        echo "\n";
        break;
    }
}

// Save to a test file
file_put_contents(__DIR__.'/output/test_init.mp4', pack('C*', ...$initSegment));
echo "\nSaved to test_init.mp4\n";

// Now read it back and check
echo "\nReading back test_init.mp4...\n";
$readBack = file_get_contents(__DIR__.'/output/test_init.mp4');
$readBytes = unpack('C*', $readBack);
$readArray = array_values($readBytes);

echo "Read back size: " . count($readArray) . " bytes\n";

// Find avc1 box in read back data
echo "\nSearching for avc1 box in read back data...\n";
for ($i = 0; $i < count($readArray) - 4; $i++) {
    if ($readArray[$i] == 0x61 && $readArray[$i+1] == 0x76 && $readArray[$i+2] == 0x63 && $readArray[$i+3] == 0x31) {
        echo "Found avc1 at offset $i\n";
        $width = ($readArray[$i + 28] << 8) | $readArray[$i + 29];
        $height = ($readArray[$i + 30] << 8) | $readArray[$i + 31];
        echo "Width: $width, Height: $height\n";
        break;
    }
}
?>