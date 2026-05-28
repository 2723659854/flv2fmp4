<?php
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
    'avcc' => [0x01, 0x64, 0x00, 0x1F, 0xFF, 0xE1, 0x00, 0x1C, 0x67, 0x64, 0x00, 0x1F, 0xAC, 0xD9, 0x40, 0x50, 0x05, 0xBB, 0x01, 0x10, 0x00, 0x00, 0x03, 0x00, 0x01, 0x00, 0x00, 0x03, 0x03, 0xC8, 0xF1, 0x83, 0x18]
];

echo "=== Testing avc1 box generation ===\n";
echo "Input: width=$testMeta[codecWidth], height=$testMeta[codecHeight]\n";

$avc1Box = MP4::avc1($testMeta);

echo "\navc1 box size: " . count($avc1Box) . " bytes\n";

// Check structure
echo "\navc1 box structure:\n";
echo "Bytes 0-3 (size): " . (($avc1Box[0] << 24) | ($avc1Box[1] << 16) | ($avc1Box[2] << 8) | $avc1Box[3]) . "\n";
echo "Bytes 4-7 (type): " . chr($avc1Box[4]) . chr($avc1Box[5]) . chr($avc1Box[6]) . chr($avc1Box[7]) . "\n";
echo "Bytes 28-29 (width): " . (($avc1Box[28] << 8) | $avc1Box[29]) . " (0x" . dechex($avc1Box[28]) . dechex($avc1Box[29]) . ")\n";
echo "Bytes 30-31 (height): " . (($avc1Box[30] << 8) | $avc1Box[31]) . " (0x" . dechex($avc1Box[30]) . dechex($avc1Box[31]) . ")\n";

echo "\n=== Testing stsd box generation ===\n";

$stsdBox = MP4::stsd($testMeta);

echo "\nstd box size: " . count($stsdBox) . " bytes\n";

// Find avc1 in stsd
for ($i = 0; $i < count($stsdBox) - 4; $i++) {
    if ($stsdBox[$i] == 0x61 && $stsdBox[$i+1] == 0x76 && $stsdBox[$i+2] == 0x63 && $stsdBox[$i+3] == 0x31) {
        echo "\nFound avc1 at offset $i in stsd\n";
        $avc1InStsd = array_slice($stsdBox, $i);
        echo "Width: " . (($avc1InStsd[28] << 8) | $avc1InStsd[29]) . "\n";
        echo "Height: " . (($avc1InStsd[30] << 8) | $avc1InStsd[31]) . "\n";
        
        // Print raw bytes around width/height
        echo "Raw bytes at avc1 offset 28-35: ";
        for ($j = 28; $j < 36; $j++) {
            printf("%02X ", $avc1InStsd[$j]);
        }
        echo "\n";
        break;
    }
}
?>