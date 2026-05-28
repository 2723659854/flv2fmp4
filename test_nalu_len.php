<?php

$testData = [0x00, 0x00, 0x02, 0xA1, 0x06, 0x05, 0xFF, 0xFF];

function _readUint32BE($buffer, $offset) {
    return ($buffer[$offset] << 24) | 
           ($buffer[$offset + 1] << 16) | 
           ($buffer[$offset + 2] << 8) | 
           $buffer[$offset + 3];
}

$naluLen = _readUint32BE($testData, 0);
echo "NALU length: $naluLen\n";
echo "Expected: 673\n";
echo "0x" . sprintf('%08X', $naluLen) . "\n";

// Check if it matches the FLV data
$flvData = [0x23, 0x01, 0x00, 0x00, 0x67, 0x00, 0x00, 0x02, 0xA1, 0x06, 0x05, 0xFF, 0xFF];
$naluLen2 = _readUint32BE($flvData, 4);
echo "\nFLV NALU length: $naluLen2\n";
echo "Expected: 673\n";
echo "0x" . sprintf('%08X', $naluLen2) . "\n";
?>