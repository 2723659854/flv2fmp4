<?php
// 分析生成的 MP4 文件结构
$filePath = __DIR__ . '/output/output.mp4';

if (!file_exists($filePath)) {
    die("File not found: $filePath\n");
}

$fileData = file_get_contents($filePath);
$bytes = unpack('C*', $fileData);
$data = array_values($bytes);

echo "File size: " . count($data) . " bytes\n";
echo "\n=== MP4 Box Structure ===\n";

$offset = 0;
while ($offset < count($data)) {
    // 读取 box 大小 (4 bytes)
    if ($offset + 3 >= count($data)) break;
    $size = ($data[$offset] << 24) | ($data[$offset+1] << 16) | ($data[$offset+2] << 8) | $data[$offset+3];
    
    // 读取 box 类型 (4 bytes)
    if ($offset + 7 >= count($data)) break;
    $type = chr($data[$offset+4]) . chr($data[$offset+5]) . chr($data[$offset+6]) . chr($data[$offset+7]);
    
    echo sprintf("Offset: %6d, Size: %6d, Type: %s\n", $offset, $size, $type);
    
    // 如果是 moof，检查内部的 traf
    if ($type == 'moof') {
        $innerOffset = $offset + 8;
        while ($innerOffset < $offset + $size) {
            if ($innerOffset + 7 >= count($data)) break;
            $innerSize = ($data[$innerOffset] << 24) | ($data[$innerOffset+1] << 16) | ($data[$innerOffset+2] << 8) | $data[$innerOffset+3];
            $innerType = chr($data[$innerOffset+4]) . chr($data[$innerOffset+5]) . chr($data[$innerOffset+6]) . chr($data[$innerOffset+7]);
            echo sprintf("         -> Offset: %6d, Size: %6d, Type: %s\n", $innerOffset, $innerSize, $innerType);
            
            // 如果是 traf，检查内部结构
            if ($innerType == 'traf') {
                $trafOffset = $innerOffset + 8;
                while ($trafOffset < $innerOffset + $innerSize) {
                    if ($trafOffset + 7 >= count($data)) break;
                    $trafInnerSize = ($data[$trafOffset] << 24) | ($data[$trafOffset+1] << 16) | ($data[$trafOffset+2] << 8) | $data[$trafOffset+3];
                    $trafInnerType = chr($data[$trafOffset+4]) . chr($data[$trafOffset+5]) . chr($data[$trafOffset+6]) . chr($data[$trafOffset+7]);
                    echo sprintf("              -> Offset: %6d, Size: %6d, Type: %s\n", $trafOffset, $trafInnerSize, $trafInnerType);
                    
                    // 如果是 tfhd，读取 track ID
                    if ($trafInnerType == 'tfhd') {
                        // tfhd 结构: 4 bytes size + 4 bytes type + 1 byte version + 3 bytes flags + 4 bytes trackId
                        // 所以 trackId 在 trafOffset + 12 的位置
                        if ($trafOffset + 15 < count($data)) {
                            $trackId = ($data[$trafOffset+12] << 24) | ($data[$trafOffset+13] << 16) | ($data[$trafOffset+14] << 8) | $data[$trafOffset+15];
                            echo sprintf("                 Track ID: %d\n", $trackId);
                        }
                    }
                    
                    $trafOffset += $trafInnerSize;
                }
            }
            
            $innerOffset += $innerSize;
        }
    }
    
    $offset += $size;
}
?>