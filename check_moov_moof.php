<?php
// 手动检查MP4文件中的关键box
$mp4Path = __DIR__ . '/output/output.mp4';
if (!file_exists($mp4Path)) {
    echo "File not found: $mp4Path\n";
    exit;
}

$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "MP4 file size: " . count($mp4Array) . " bytes\n\n";

// 检查moov内部
echo "=== Checking moov box (offset 24, size 670) ===\n";
$moovOffset = 24 + 8; // skip box header

$offset = $moovOffset;
while ($offset < $moovOffset + 670 - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset+1] << 16) | 
            ($mp4Array[$offset+2] << 8) | $mp4Array[$offset+3];
    $type = chr($mp4Array[$offset+4]) . chr($mp4Array[$offset+5]) . 
            chr($mp4Array[$offset+6]) . chr($mp4Array[$offset+7]);
    
    echo "  Sub-box: $type, Offset: $offset, Size: $size\n";
    
    if ($type == 'avcC') {
        $avcCOffset = $offset + 8;
        $version = $mp4Array[$avcCOffset];
        $avcProfile = $mp4Array[$avcCOffset + 1];
        $naluLengthSize = ($mp4Array[$avcCOffset + 4] & 0x03) + 1;
        echo "    AVC Version: $version, Profile: " . dechex($avcProfile) . ", NALU length: $naluLengthSize\n";
    }
    
    $offset += $size;
}

// 检查moof内部
echo "\n=== Checking moof box (offset 694, size 16233) ===\n";
$moofOffset = 694 + 8; // skip box header

$offset = $moofOffset;
while ($offset < $moofOffset + 16233 - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset+1] << 16) | 
            ($mp4Array[$offset+2] << 8) | $mp4Array[$offset+3];
    $type = chr($mp4Array[$offset+4]) . chr($mp4Array[$offset+5]) . 
            chr($mp4Array[$offset+6]) . chr($mp4Array[$offset+7]);
    
    echo "  Sub-box: $type, Offset: $offset, Size: $size\n";
    
    if ($type == 'tfhd') {
        $tfhdOffset = $offset + 8;
        $version = $mp4Array[$tfhdOffset];
        $flags = ($mp4Array[$tfhdOffset + 1] << 16) | ($mp4Array[$tfhdOffset + 2] << 8) | $mp4Array[$tfhdOffset + 3];
        $trackId = ($mp4Array[$tfhdOffset + 4] << 24) | ($mp4Array[$tfhdOffset + 5] << 16) | 
                   ($mp4Array[$tfhdOffset + 6] << 8) | $mp4Array[$tfhdOffset + 7];
        echo "    Version: $version, Flags: " . dechex($flags) . ", Track ID: $trackId\n";
    }
    
    $offset += $size;
}
?>