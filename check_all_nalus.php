<?php
// 检查视频样本中的所有NALU
$filePath = __DIR__ . '/test.flv';
$fileData = file_get_contents($filePath);
$bytes = unpack('C*', $fileData);
$arrayBuffer = array_values($bytes);

echo "FLV file size: " . count($arrayBuffer) . " bytes\n\n";

// FLV 头部
$headerSize = ($arrayBuffer[5] << 24) | ($arrayBuffer[6] << 16) | ($arrayBuffer[7] << 8) | $arrayBuffer[8];
echo "Header size: $headerSize\n";

$offset = $headerSize + 4; // 跳过头部和 previousTagSize0

// 找到第一个视频标签
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
                    echo "Found video tag at offset $offset\n";
                    echo "  Data size: $dataSize\n";
                    echo "  Timestamp: $timestamp\n";
                    echo "  Frame Type: " . ($frameType == 1 ? "Keyframe" : "Inter frame") . "\n";
                    
                    // 解析所有NALU
                    $naluOffset = $videoDataOffset + 5;
                    $naluLengthSize = 4;
                    $totalSize = $dataSize - 5; // 减去AVC packet type和composition time
                    $currentOffset = 0;
                    $naluCount = 0;
                    
                    echo "\n  NALUs in this tag:\n";
                    while ($currentOffset < $totalSize) {
                        if ($currentOffset + $naluLengthSize > $totalSize) {
                            break;
                        }
                        
                        $naluSize = ($arrayBuffer[$naluOffset + $currentOffset] << 24) | 
                                    ($arrayBuffer[$naluOffset + $currentOffset + 1] << 16) | 
                                    ($arrayBuffer[$naluOffset + $currentOffset + 2] << 8) | 
                                    $arrayBuffer[$naluOffset + $currentOffset + 3];
                        
                        if ($naluSize == 0) {
                            $currentOffset += $naluLengthSize;
                            continue;
                        }
                        
                        $naluType = $arrayBuffer[$naluOffset + $currentOffset + $naluLengthSize] & 0x1F;
                        $naluTypeName = [
                            1 => 'Coded slice (non-IDR)',
                            5 => 'Coded slice (IDR)',
                            6 => 'SEI',
                            7 => 'SPS',
                            8 => 'PPS'
                        ][$naluType] ?? 'Unknown (' . $naluType . ')';
                        
                        echo "    NALU $naluCount: Type=$naluTypeName (" . dechex($naluType) . "), Size=$naluSize\n";
                        
                        // 显示前10字节
                        echo "    First 10 bytes: ";
                        for ($i = 0; $i < min(10, $naluSize); $i++) {
                            printf("%02X ", $arrayBuffer[$naluOffset + $currentOffset + $naluLengthSize + $i]);
                        }
                        echo "\n";
                        
                        $currentOffset += $naluLengthSize + $naluSize;
                        $naluCount++;
                    }
                    
                    echo "\n  Total NALUs: $naluCount\n";
                    echo "  Bytes processed: $currentOffset\n";
                    break;
                }
            }
        }
    }
    
    // 跳到下一个标签
    $dataSize = ($arrayBuffer[$offset+1] << 16) | ($arrayBuffer[$offset+2] << 8) | $arrayBuffer[$offset+3];
    $offset += 11 + $dataSize + 4; // tag header + body + previousTagSize
}
?>