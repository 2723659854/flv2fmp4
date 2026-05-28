<?php

require_once 'php/mp4/MP4Remux.php';

// Check if types are initialized correctly
echo "Checking self::\$types['avc1']:\n";
echo "Value: " . print_r(MP4::$types['avc1'], true) . "\n";

// Check if it's the correct value
$expected = [ord('a'), ord('v'), ord('c'), ord('1')];
echo "Expected: " . print_r($expected, true) . "\n";

// Test box function
echo "\nTesting box function with avc1 type:\n";
$testData = [0x00, 0x01, 0x02, 0x03];
$box = MP4::box(MP4::$types['avc1'], $testData);

echo "Box size: " . count($box) . "\n";
echo "Box content (first 12 bytes): ";
for ($i = 0; $i < min(12, count($box)); $i++) {
    echo dechex($box[$i]) . " ";
}
echo "\n";

// Check if the type is correct
echo "Type at offset 4: " . chr($box[4]) . chr($box[5]) . chr($box[6]) . chr($box[7]) . "\n";
?>