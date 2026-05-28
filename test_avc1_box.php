<?php

require_once 'php/mp4/MP4Remux.php';

// Create a test video meta
$videoMeta = [
    'codecWidth' => 720,
    'codecHeight' => 742,
    'avcc' => [0x01, 0x67, 0x4D, 0x40, 0x1F] // Dummy avcc data
];

echo "Testing avc1 box creation:\n";
$avc1Box = MP4::avc1($videoMeta);

echo "\nCreated avc1 box (first 50 bytes):\n";
$hex = '';
$ascii = '';
for ($i = 0; $i < min(50, count($avc1Box)); $i++) {
    $hex .= sprintf('%02X ', $avc1Box[$i]);
    $ascii .= ($avc1Box[$i] >= 32 && $avc1Box[$i] <= 126) ? chr($avc1Box[$i]) : '.';
    
    if ((($i + 1) % 16) == 0) {
        echo $hex . '| ' . $ascii . "\n";
        $hex = '';
        $ascii = '';
    }
}
if ($hex) {
    echo $hex . str_repeat('   ', 16 - (strlen($hex) / 3)) . '| ' . $ascii . "\n";
}

// Check the type at offset 4
$type = chr($avc1Box[4]) . chr($avc1Box[5]) . chr($avc1Box[6]) . chr($avc1Box[7]);
echo "\nType at offset 4: '$type' (expected 'avc1')\n";

// Check width/height at offset 28
$width = ($avc1Box[28] << 8) | $avc1Box[29];
$height = ($avc1Box[30] << 8) | $avc1Box[31];
echo "Width at offset 28: $width (expected 720)\n";
echo "Height at offset 30: $height (expected 742)\n";
?>