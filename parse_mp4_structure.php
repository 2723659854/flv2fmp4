<?php
// 递归解析MP4文件的嵌套box结构
function parseBoxes($data, $offset, $depth = 0) {
    $indent = str_repeat('  ', $depth);
    
    while ($offset < count($data) - 8) {
        $size = ($data[$offset] << 24) | ($data[$offset+1] << 16) | 
                ($data[$offset+2] << 8) | $data[$offset+3];
        
        if ($size == 0) {
            // 特殊情况：box扩展到文件末尾
            $size = count($data) - $offset;
        }
        
        $type = chr($data[$offset+4]) . chr($data[$offset+5]) . 
                chr($data[$offset+6]) . chr($data[$offset+7]);
        
        echo $indent . "Box: $type, Offset: $offset, Size: $size\n";
        
        // 特殊处理某些box
        if ($type == 'avcC') {
            $avcCOffset = $offset + 8;
            $version = $data[$avcCOffset];
            $avcProfile = $data[$avcCOffset + 1];
            $naluLengthSize = ($data[$avcCOffset + 4] & 0x03) + 1;
            echo $indent . "  AVC Version: $version, Profile: " . dechex($avcProfile) . ", NALU length: $naluLengthSize\n";
        }
        
        if ($type == 'tfhd') {
            $tfhdOffset = $offset + 8;
            $version = $data[$tfhdOffset];
            $flags = ($data[$tfhdOffset + 1] << 16) | ($data[$tfhdOffset + 2] << 8) | $data[$tfhdOffset + 3];
            $trackId = ($data[$tfhdOffset + 4] << 24) | ($data[$tfhdOffset + 5] << 16) | 
                       ($data[$tfhdOffset + 6] << 8) | $data[$tfhdOffset + 7];
            echo $indent . "  Version: $version, Flags: " . dechex($flags) . ", Track ID: $trackId\n";
        }
        
        if ($type == 'traf') {
            echo $indent . "  Contains multiple boxes:\n";
            parseBoxes($data, $offset + 8, $depth + 2);
        }
        
        $offset += $size;
    }
}

$mp4Path = __DIR__ . '/output/output.mp4';
if (!file_exists($mp4Path)) {
    echo "File not found: $mp4Path\n";
    exit;
}

$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "MP4 file size: " . count($mp4Array) . " bytes\n\n";
parseBoxes($mp4Array, 0);
?>