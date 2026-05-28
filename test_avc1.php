<?php

// Test avc1 function directly
require_once 'php/mp4/MP4Remux.php';

// Create a test video meta
$testMeta = [
    'type' => 'video',
    'id' => 1,
    'timescale' => 1000,
    'duration' => 0,
    'codecWidth' => 720,
    'codecHeight' => 742,
    'presentWidth' => 720,
    'presentHeight' => 742,
    'avcc' => [0x01, 0x64, 0x00, 0x1F]
];

echo "Testing avc1 function with codecWidth=720, codecHeight=742\n";

$avc1Box = MP4::avc1($testMeta);

echo "avc1 box size: " . count($avc1Box) . " bytes\n";

// Check width/height in the avc1 box
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

$width = ($avc1Box[28] << 8) | $avc1Box[29];
$height = ($avc1Box[30] << 8) | $avc1Box[31];

echo "Width in avc1 box: $width\n";
echo "Height in avc1 box: $height\n";

// Print raw bytes around width/height
echo "Raw bytes at offset 28: ";
for ($i = 28; $i < 36; $i++) {
    echo dechex($avc1Box[$i]) . " ";
}
echo "\n";
?>