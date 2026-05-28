<?php
require_once 'php/mp4/MP4Remux.php';

// Create a minimal test
echo "=== Testing raw box creation ===\n";

// Create stsd box manually
$stsdType = [0x73, 0x74, 0x73, 0x64]; // 'stsd'
$stsdPrefix = [0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x01]; // version + flags + entry_count

// Create avc1 box data
$avc1Data = [
    0x00, 0x00, 0x00, 0x00,  // reserved
    0x00, 0x00, 0x00, 0x01,  // data_reference_index
    0x00, 0x00, 0x00, 0x00,  // pre_defined
    0x00, 0x00, 0x00, 0x00,  // pre_defined
    0x00, 0x00, 0x00, 0x00,  // pre_defined
    0x02, 0xD0,              // width (720)
    0x02, 0xE6,              // height (742)
    0x00, 0x48, 0x00, 0x00,  // horizresolution
    0x00, 0x48, 0x00, 0x00,  // vertresolution
    0x00, 0x00, 0x00, 0x00,  // reserved
    0x00, 0x01,              // frame_count
    0x04,                    // compressorname length
    0x67, 0x31, 0x31, 0x31,  // compressorname
    0x00, 0x00, 0x00, 0x00,  // reserved
    0x00, 0x00, 0x00, 0x00,  // reserved
    0x00, 0x00, 0x00, 0x00,  // reserved
    0x00, 0x00, 0x00, 0x00,  // reserved
    0x00, 0x00, 0x00, 0x00,  // reserved
    0x00, 0x00, 0x00,        // depth
    0x00, 0x18,              // pre_defined
    0xFF, 0xFF               // pre_defined
];

$avccData = [0x01, 0x64, 0x00, 0x1F];

// Create avc1 box
$avc1Type = [0x61, 0x76, 0x63, 0x31]; // 'avc1'
$avc1Size = 8 + count($avc1Data) + 8 + count($avccData);

echo "avc1 data size: " . count($avc1Data) . "\n";
echo "avcc data size: " . count($avccData) . "\n";
echo "avc1 box size: $avc1Size\n";

// Manual construction
$avc1Box = array_fill(0, $avc1Size, 0);
$avc1Box[0] = ($avc1Size >> 24) & 0xFF;
$avc1Box[1] = ($avc1Size >> 16) & 0xFF;
$avc1Box[2] = ($avc1Size >> 8) & 0xFF;
$avc1Box[3] = $avc1Size & 0xFF;
for ($i = 0; $i < 4; $i++) {
    $avc1Box[4 + $i] = $avc1Type[$i];
}
$offset = 8;
foreach ($avc1Data as $byte) {
    $avc1Box[$offset++] = $byte;
}

// Add avcc sub-box
$avccSize = 8 + count($avccData);
$avc1Box[$offset++] = ($avccSize >> 24) & 0xFF;
$avc1Box[$offset++] = ($avccSize >> 16) & 0xFF;
$avc1Box[$offset++] = ($avccSize >> 8) & 0xFF;
$avc1Box[$offset++] = $avccSize & 0xFF;
$avc1Box[$offset++] = 0x61; // 'a'
$avc1Box[$offset++] = 0x76; // 'v'
$avc1Box[$offset++] = 0x63; // 'c'
$avc1Box[$offset++] = 0x43; // 'C'
foreach ($avccData as $byte) {
    $avc1Box[$offset++] = $byte;
}

// Check avc1 box
echo "\nManual avc1 box:\n";
echo "Width at offset 28-29: " . (($avc1Box[28] << 8) | $avc1Box[29]) . "\n";
echo "Height at offset 30-31: " . (($avc1Box[30] << 8) | $avc1Box[31]) . "\n";

// Now create stsd box manually
$stsdSize = 8 + count($stsdPrefix) + count($avc1Box);
$stsdBox = array_fill(0, $stsdSize, 0);
$stsdBox[0] = ($stsdSize >> 24) & 0xFF;
$stsdBox[1] = ($stsdSize >> 16) & 0xFF;
$stsdBox[2] = ($stsdSize >> 8) & 0xFF;
$stsdBox[3] = $stsdSize & 0xFF;
for ($i = 0; $i < 4; $i++) {
    $stsdBox[4 + $i] = $stsdType[$i];
}
$offset = 8;
foreach ($stsdPrefix as $byte) {
    $stsdBox[$offset++] = $byte;
}
foreach ($avc1Box as $byte) {
    $stsdBox[$offset++] = $byte;
}

// Find avc1 in stsd
echo "\nManual stsd box:\n";
for ($i = 0; $i < count($stsdBox) - 4; $i++) {
    if ($stsdBox[$i] == 0x61 && $stsdBox[$i+1] == 0x76 && $stsdBox[$i+2] == 0x63 && $stsdBox[$i+3] == 0x31) {
        echo "Found avc1 at offset $i\n";
        $width = ($stsdBox[$i+28] << 8) | $stsdBox[$i+29];
        $height = ($stsdBox[$i+30] << 8) | $stsdBox[$i+31];
        echo "Width: $width, Height: $height\n";
        break;
    }
}

// Compare with MP4::box function
echo "\n=== Testing MP4::box function ===\n";
$avc1Box2 = MP4::box($avc1Type, $avc1Data, MP4::box([0x61, 0x76, 0x63, 0x43], $avccData));
echo "MP4::box avc1 size: " . count($avc1Box2) . "\n";
echo "Width at offset 28-29: " . (($avc1Box2[28] << 8) | $avc1Box2[29]) . "\n";

$stsdBox2 = MP4::box($stsdType, $stsdPrefix, $avc1Box2);
echo "\nMP4::box stsd size: " . count($stsdBox2) . "\n";
for ($i = 0; $i < count($stsdBox2) - 4; $i++) {
    if ($stsdBox2[$i] == 0x61 && $stsdBox2[$i+1] == 0x76 && $stsdBox2[$i+2] == 0x63 && $stsdBox2[$i+3] == 0x31) {
        echo "Found avc1 at offset $i\n";
        $width = ($stsdBox2[$i+28] << 8) | $stsdBox2[$i+29];
        $height = ($stsdBox2[$i+30] << 8) | $stsdBox2[$i+31];
        echo "Width: $width, Height: $height\n";
        break;
    }
}
?>