<?php
// 详细检查 FLV 文件结构
$filePath = __DIR__ . '/test.flv';
$fileData = file_get_contents($filePath);
$bytes = unpack('C*', $fileData);
$arrayBuffer = array_values($bytes);

echo "FLV file size: " . count($arrayBuffer) . " bytes\n\n";

// 检查 FLV 头部
echo "=== FLV Header ===\n";
echo "Signature: " . chr($arrayBuffer[0]) . chr($arrayBuffer[1]) . chr($arrayBuffer[2]) . "\n";
echo "Version: " . $arrayBuffer[3] . "\n";
echo "Flags: 0x" . dechex($arrayBuffer[4]) . "\n";
$headerSize = ($arrayBuffer[5] << 24) | ($arrayBuffer[6] << 16) | ($arrayBuffer[7] << 8) | $arrayBuffer[8];
echo "Header size: $headerSize bytes\n";

echo "\n=== First 100 bytes (with offset) ===\n";
for ($i = 0; $i < min(100, count($arrayBuffer)); $i++) {
    if ($i % 16 == 0) {
        echo "\nOffset " . str_pad($i, 4, '0', STR_PAD_LEFT) . ": ";
    }
    printf("%02X ", $arrayBuffer[$i]);
}

echo "\n\n=== Looking for tags starting from offset $headerSize ===\n";
$offset = $headerSize;

while ($offset < count($arrayBuffer) - 11) {
    $tagType = $arrayBuffer[$offset];
    
    // 检查是否是有效的标签类型
    if ($tagType == 8 || $tagType == 9 || $tagType == 18) {
        $dataSize = ($arrayBuffer[$offset+1] << 16) | ($arrayBuffer[$offset+2] << 8) | $arrayBuffer[$offset+3];
        $timestamp = ($arrayBuffer[$offset+4] << 16) | ($arrayBuffer[$offset+5] << 8) | $arrayBuffer[$offset+6];
        $timestampExtended = $arrayBuffer[$offset+7];
        $streamID = ($arrayBuffer[$offset+8] << 16) | ($arrayBuffer[$offset+9] << 8) | $arrayBuffer[$offset+10];
        
        $tagTypeName = ['8' => 'Audio', '9' => 'Video', '18' => 'Script'][($tagType)];
        echo "Found $tagTypeName tag at offset $offset\n";
        echo "  Data size: $dataSize\n";
        echo "  Timestamp: $timestamp (ext: $timestampExtended)\n";
        echo "  Stream ID: $streamID\n";
        
        // 检查标签内容
        if ($tagType == 9 && count($arrayBuffer) > $offset + 11) {
            $videoDataOffset = $offset + 11;
            $frameType = ($arrayBuffer[$videoDataOffset] >> 4) & 0x0F;
            $codecId = $arrayBuffer[$videoDataOffset] & 0x0F;
            echo "  Frame Type: " . ($frameType == 1 ? "Keyframe" : "Inter frame") . "\n";
            echo "  Codec ID: " . ($codecId == 7 ? "AVC" : "Unknown (" . $codecId . ")") . "\n";
        }
        
        break;
    }
    
    $offset++;
    if ($offset - $headerSize > 100) {
        echo "No tag found in first 100 bytes after header\n";
        break;
    }
}
?>