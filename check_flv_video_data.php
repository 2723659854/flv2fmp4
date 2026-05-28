<?php
// 直接检查 FLV 文件中的视频数据
$filePath = __DIR__ . '/test.flv';
$fileData = file_get_contents($filePath);
$bytes = unpack('C*', $fileData);
$arrayBuffer = array_values($bytes);

echo "FLV file size: " . count($arrayBuffer) . " bytes\n\n";

// FLV 头部
$headerSize = ($arrayBuffer[5] << 24) | ($arrayBuffer[6] << 16) | ($arrayBuffer[7] << 8) | $arrayBuffer[8];
echo "Header size: $headerSize\n";

$offset = $headerSize + 4; // 跳过头部和 previousTagSize0

$videoTagCount = 0;
$firstVideoData = null;

while ($offset < count($arrayBuffer) - 11) {
    $tagType = $arrayBuffer[$offset];
    
    if ($tagType == 9) { // Video tag
        $dataSize = ($arrayBuffer[$offset+1] << 16) | ($arrayBuffer[$offset+2] << 8) | $arrayBuffer[$offset+3];
        $timestamp = ($arrayBuffer[$offset+4] << 16) | ($arrayBuffer[$offset+5] << 8) | $arrayBuffer[$offset+6];
        
        $videoDataOffset = $offset + 11;
        if ($videoDataOffset + 1 < count($arrayBuffer)) {
            $frameType = ($arrayBuffer[$videoDataOffset] >> 4) & 0x0F;
            $codecId = $arrayBuffer[$videoDataOffset] & 0x0F;
            
            if ($codecId == 7) { // AVC
                $avcPacketType = $arrayBuffer[$videoDataOffset + 1];
                
                if ($avcPacketType == 1) { // NALU data
                    $videoTagCount++;
                    
                    // 获取 NALU 数据
                    $naluOffset = $videoDataOffset + 5;
                    $naluSize = ($arrayBuffer[$naluOffset] << 24) | ($arrayBuffer[$naluOffset+1] << 16) | 
                                ($arrayBuffer[$naluOffset+2] << 8) | $arrayBuffer[$naluOffset+3];
                    
                    if ($videoTagCount == 1) {
                        echo "First video NALU tag at offset $offset\n";
                        echo "  Data size: $dataSize\n";
                        echo "  Timestamp: $timestamp\n";
                        echo "  Frame Type: " . ($frameType == 1 ? "Keyframe" : "Inter frame") . "\n";
                        echo "  NALU Size: $naluSize\n";
                        
                        // 显示 NALU 数据的前 20 字节
                        echo "  NALU data (first 20 bytes): ";
                        for ($i = 0; $i < min(20, $naluSize); $i++) {
                            printf("%02X ", $arrayBuffer[$naluOffset + 4 + $i]);
                        }
                        echo "\n";
                        
                        $firstVideoData = array_slice($arrayBuffer, $naluOffset + 4, $naluSize);
                    }
                }
            }
        }
    }
    
    // 跳到下一个标签
    $dataSize = ($arrayBuffer[$offset+1] << 16) | ($arrayBuffer[$offset+2] << 8) | $arrayBuffer[$offset+3];
    $offset += 11 + $dataSize + 4; // tag header + body + previousTagSize
    
    if ($videoTagCount >= 5) break;
}

echo "\nTotal video tags found: $videoTagCount\n";

// 检查第一个 NALU 的类型
if ($firstVideoData) {
    $naluType = $firstVideoData[0] & 0x1F;
    $naluTypeName = [
        1 => 'Coded slice (non-IDR)',
        5 => 'Coded slice (IDR)',
        7 => 'SPS',
        8 => 'PPS'
    ][$naluType] ?? 'Unknown (' . $naluType . ')';
    echo "\nFirst NALU Type: $naluTypeName (" . dechex($naluType) . ")\n";
}
?>