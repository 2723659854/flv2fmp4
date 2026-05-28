<?php
// 简单检查MP4文件结构
$mp4Path = __DIR__ . '/output/output.mp4';
if (!file_exists($mp4Path)) {
    echo "File not found: $mp4Path\n";
    exit;
}

$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "MP4 file size: " . count($mp4Array) . " bytes\n\n";

// 查找所有box
$offset = 0;
while ($offset < count($mp4Array) - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset+1] << 16) | 
            ($mp4Array[$offset+2] << 8) | $mp4Array[$offset+3];
    $type = chr($mp4Array[$offset+4]) . chr($mp4Array[$offset+5]) . 
            chr($mp4Array[$offset+6]) . chr($mp4Array[$offset+7]);
    
    echo "Box: $type, Offset: $offset, Size: $size\n";
    
    if ($type == 'avcC') {
        echo "  Found avcC box!\n";
        $avcCOffset = $offset + 8;
        $version = $mp4Array[$avcCOffset];
        $avcProfile = $mp4Array[$avcCOffset + 1];
        $naluLengthSize = ($mp4Array[$avcCOffset + 4] & 0x03) + 1;
        echo "    Version: $version, Profile: " . dechex($avcProfile) . ", NALU length size: $naluLengthSize\n";
    }
    
    if ($type == 'tfhd') {
        echo "  Found tfhd box!\n";
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