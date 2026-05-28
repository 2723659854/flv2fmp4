<?php
// 检查stsd内部结构
$mp4Path = __DIR__ . '/output/output.mp4';
if (!file_exists($mp4Path)) {
    echo "File not found: $mp4Path\n";
    exit;
}

$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "=== Checking stsd box (offset 397, size 157) ===\n";
$stsdOffset = 397 + 8; // skip box header

// stsd header
$version = $mp4Array[$stsdOffset];
$flags = ($mp4Array[$stsdOffset + 1] << 16) | ($mp4Array[$stsdOffset + 2] << 8) | $mp4Array[$stsdOffset + 3];
$entryCount = ($mp4Array[$stsdOffset + 4] << 24) | ($mp4Array[$stsdOffset + 5] << 16) | 
              ($mp4Array[$stsdOffset + 6] << 8) | $mp4Array[$stsdOffset + 7];

echo "  Version: $version, Flags: " . dechex($flags) . ", Entry count: $entryCount\n";

// avc1 sample entry
$avc1Offset = $stsdOffset + 8;
$avc1Size = ($mp4Array[$avc1Offset] << 24) | ($mp4Array[$avc1Offset + 1] << 16) | 
            ($mp4Array[$avc1Offset + 2] << 8) | $mp4Array[$avc1Offset + 3];
$avc1Type = chr($mp4Array[$avc1Offset + 4]) . chr($mp4Array[$avc1Offset + 5]) . 
            chr($mp4Array[$avc1Offset + 6]) . chr($mp4Array[$avc1Offset + 7]);

echo "  Sample entry: $avc1Type, Size: $avc1Size\n";

// VisualSampleEntry fields
$avc1DataOffset = $avc1Offset + 8;

// Skip reserved fields (16 bytes)
$avc1DataOffset += 16;

// Data reference index (2 bytes)
$dataRefIdx = ($mp4Array[$avc1DataOffset] << 8) | $mp4Array[$avc1DataOffset + 1];
echo "  Data reference index: $dataRefIdx\n";

// Pre-defined (2 bytes) - should be 0
$preDefined = ($mp4Array[$avc1DataOffset + 2] << 8) | $mp4Array[$avc1DataOffset + 3];
echo "  Pre-defined: " . dechex($preDefined) . "\n";

// Reserved (2 bytes) - should be 0
$reserved = ($mp4Array[$avc1DataOffset + 4] << 8) | $mp4Array[$avc1DataOffset + 5];
echo "  Reserved: " . dechex($reserved) . "\n";

// Width (2 bytes)
$width = ($mp4Array[$avc1DataOffset + 6] << 8) | $mp4Array[$avc1DataOffset + 7];
echo "  Width: $width\n";

// Height (2 bytes)
$height = ($mp4Array[$avc1DataOffset + 8] << 8) | $mp4Array[$avc1DataOffset + 9];
echo "  Height: $height\n";

// Check for avcC inside avc1
echo "\n  Looking for avcC inside avc1...\n";
$offset = $avc1Offset + 8; // start of avc1 content
while ($offset < $avc1Offset + $avc1Size - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset + 1] << 16) | 
            ($mp4Array[$offset + 2] << 8) | $mp4Array[$offset + 3];
    $type = chr($mp4Array[$offset + 4]) . chr($mp4Array[$offset + 5]) . 
            chr($mp4Array[$offset + 6]) . chr($mp4Array[$offset + 7]);
    
    echo "    Sub-box: $type, Offset: $offset, Size: $size\n";
    
    if ($type == 'avcC') {
        $avcCOffset = $offset + 8;
        $version = $mp4Array[$avcCOffset];
        $avcProfile = $mp4Array[$avcCOffset + 1];
        $naluLengthSize = ($mp4Array[$avcCOffset + 4] & 0x03) + 1;
        echo "      AVC Version: $version, Profile: " . dechex($avcProfile) . ", NALU length: $naluLengthSize\n";
        
        // Show first 20 bytes of avcC
        echo "      First 20 bytes: ";
        for ($i = 0; $i < min(20, $size - 8); $i++) {
            printf("%02X ", $mp4Array[$avcCOffset + $i]);
        }
        echo "\n";
    }
    
    $offset += $size;
}
?>