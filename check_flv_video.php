<?php
// 检查 FLV 文件中的视频数据
$filePath = __DIR__ . '/test.flv';

if (!file_exists($filePath)) {
    die("File not found: $filePath\n");
}

$fileData = file_get_contents($filePath);
$bytes = unpack('C*', $fileData);
$arrayBuffer = array_values($bytes);

echo "FLV file size: " . count($arrayBuffer) . " bytes\n";

// 检查 FLV 头部
if (count($arrayBuffer) > 13) {
    $signature = chr($arrayBuffer[0]) . chr($arrayBuffer[1]) . chr($arrayBuffer[2]);
    $version = $arrayBuffer[3];
    $flags = $arrayBuffer[4];
    $headerSize = ($arrayBuffer[5] << 24) | ($arrayBuffer[6] << 16) | ($arrayBuffer[7] << 8) | $arrayBuffer[8];
    
    echo "\nFLV Header:\n";
    echo "  Signature: $signature\n";
    echo "  Version: $version\n";
    echo "  Flags: 0x" . dechex($flags) . "\n";
    echo "  Header Size: $headerSize\n";
    
    $hasAudio = ($flags & 0x04) != 0;
    $hasVideo = ($flags & 0x01) != 0;
    echo "  Has Audio: " . ($hasAudio ? "Yes" : "No") . "\n";
    echo "  Has Video: " . ($hasVideo ? "Yes" : "No") . "\n";
}

// 查找第一个视频标签
$offset = 13; // 跳过 FLV 头部
$videoTagFound = false;

while ($offset < count($arrayBuffer) - 10) {
    // 跳过 previousTagSize (4 bytes)
    $offset += 4;
    
    if ($offset + 10 >= count($arrayBuffer)) break;
    
    $tagType = $arrayBuffer[$offset];
    $dataSize = ($arrayBuffer[$offset+1] << 16) | ($arrayBuffer[$offset+2] << 8) | $arrayBuffer[$offset+3];
    $timestamp = ($arrayBuffer[$offset+4] << 16) | ($arrayBuffer[$offset+5] << 8) | $arrayBuffer[$offset+6];
    $timestampExtended = $arrayBuffer[$offset+7];
    
    if ($tagType == 9) { // Video tag
        echo "\nFound video tag at offset $offset\n";
        echo "  Tag Type: Video (0x" . dechex($tagType) . ")\n";
        echo "  Data Size: $dataSize\n";
        echo "  Timestamp: $timestamp\n";
        
        // 检查视频数据
        $videoDataOffset = $offset + 11;
        if ($videoDataOffset < count($arrayBuffer)) {
            $frameType = ($arrayBuffer[$videoDataOffset] >> 4) & 0x0F;
            $codecId = $arrayBuffer[$videoDataOffset] & 0x0F;
            
            echo "  Frame Type: " . ($frameType == 1 ? "Keyframe" : "Inter frame") . "\n";
            echo "  Codec ID: " . ($codecId == 7 ? "AVC" : "Unknown (" . $codecId . ")") . "\n";
            
            if ($codecId == 7) {
                $avcPacketType = $arrayBuffer[$videoDataOffset + 1];
                echo "  AVC Packet Type: " . ($avcPacketType == 0 ? "Sequence header" : ($avcPacketType == 1 ? "NALU" : "End of sequence")) . "\n";
                
                if ($avcPacketType == 1) {
                    // 这是 NALU 数据
                    $compositionTime = ($arrayBuffer[$videoDataOffset+2] << 16) | ($arrayBuffer[$videoDataOffset+3] << 8) | $arrayBuffer[$videoDataOffset+4];
                    echo "  Composition Time: $compositionTime\n";
                    
                    // 显示 NALU 数据的前 20 字节
                    $naluOffset = $videoDataOffset + 5;
                    echo "  NALU data (first 20 bytes): ";
                    for ($i = 0; $i < min(20, $dataSize - 5); $i++) {
                        printf("%02X ", $arrayBuffer[$naluOffset + $i]);
                    }
                    echo "\n";
                }
            }
        }
        
        $videoTagFound = true;
        break;
    }
    
    $offset += 11 + $dataSize;
}

if (!$videoTagFound) {
    echo "\nNo video tag found\n";
}
?>