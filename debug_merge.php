<?php

echo "Checking file sizes...\n";

$initFile = __DIR__.'/output/init.mp4';
$segment1 = __DIR__.'/output/segment_1.m4s';
$segment2 = __DIR__.'/output/segment_2.m4s';

echo "init.mp4 size: " . filesize($initFile) . " bytes\n";
echo "segment_1.m4s size: " . filesize($segment1) . " bytes\n";
echo "segment_2.m4s size: " . filesize($segment2) . " bytes\n";

// Load init.mp4 and check avc1 box
$initData = file_get_contents($initFile);
$initBytes = unpack('C*', $initData);
$initArray = array_values($initBytes);

echo "\ninit.mp4 avc1 box check:\n";
for ($i = 0; $i < count($initArray) - 4; $i++) {
    if ($initArray[$i] == 0x61 && $initArray[$i+1] == 0x76 && $initArray[$i+2] == 0x63 && $initArray[$i+3] == 0x31) {
        echo "Found avc1 at offset $i\n";
        $width = ($initArray[$i + 28] << 8) | $initArray[$i + 29];
        $height = ($initArray[$i + 30] << 8) | $initArray[$i + 31];
        echo "Width: $width, Height: $height\n";
        break;
    }
}

// Load segment_1 and check if it starts with moof
$seg1Data = file_get_contents($segment1);
$seg1Bytes = unpack('C*', $seg1Data);
$seg1Array = array_values($seg1Bytes);

echo "\nsegment_1.m4s first 20 bytes:\n";
for ($i = 0; $i < min(20, count($seg1Array)); $i++) {
    echo dechex($seg1Array[$i]) . " ";
}
echo "\n";

// Check the first box type
$type = chr($seg1Array[4]) . chr($seg1Array[5]) . chr($seg1Array[6]) . chr($seg1Array[7]);
echo "First box type: $type\n";

// Now simulate the merge
echo "\nSimulating array_merge...\n";
$allData = array_merge($initArray, $seg1Array);

// Check avc1 box in merged data
echo "\nMerged data avc1 box check:\n";
for ($i = 0; $i < count($allData) - 4; $i++) {
    if ($allData[$i] == 0x61 && $allData[$i+1] == 0x76 && $allData[$i+2] == 0x63 && $allData[$i+3] == 0x31) {
        echo "Found avc1 at offset $i\n";
        $width = ($allData[$i + 28] << 8) | $allData[$i + 29];
        $height = ($allData[$i + 30] << 8) | $allData[$i + 31];
        echo "Width: $width, Height: $height\n";
        
        // Show hex around width/height
        echo "Bytes around width/height (offset " . ($i + 28) . "): ";
        for ($j = $i + 24; $j < $i + 36; $j++) {
            echo dechex($allData[$j]) . " ";
        }
        echo "\n";
        break;
    }
}
?>