<?php
// 检查 FLV 文件结构
$filePath = __DIR__ . '/test.flv';

if (!file_exists($filePath)) {
    die("File not found: $filePath\n");
}

$fileData = file_get_contents($filePath);
$bytes = unpack('C*', $fileData);
$arrayBuffer = array_values($bytes);

echo "FLV file size: " . count($arrayBuffer) . " bytes\n";

// 显示文件的前 100 字节
echo "\nFirst 100 bytes of FLV file:\n";
for ($i = 0; $i < min(100, count($arrayBuffer)); $i++) {
    printf("%02X ", $arrayBuffer[$i]);
    if (($i + 1) % 16 == 0) echo "\n";
}
echo "\n";

// 显示 ASCII 表示
echo "\nASCII representation:\n";
for ($i = 0; $i < min(100, count($arrayBuffer)); $i++) {
    $c = $arrayBuffer[$i];
    echo ($c >= 32 && $c <= 126) ? chr($c) : '.';
    if (($i + 1) % 16 == 0) echo "\n";
}
echo "\n";

// 尝试查找视频标签
echo "\nSearching for video tags...\n";
$offset = 0;
while ($offset < count($arrayBuffer) - 100) {
    // 检查是否是标签开始（tag type 应该是 8, 9, 或 18）
    $tagType = $arrayBuffer[$offset];
    if ($tagType == 9) { // Video tag
        $dataSize = ($arrayBuffer[$offset+1] << 16) | ($arrayBuffer[$offset+2] << 8) | $arrayBuffer[$offset+3];
        echo "Found video tag at offset $offset, data size: $dataSize\n";
        break;
    }
    $offset++;
}
?>