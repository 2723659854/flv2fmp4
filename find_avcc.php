<?php
// 简单检查avcC是否存在
$mp4Path = __DIR__ . '/output/output.mp4';
if (!file_exists($mp4Path)) {
    echo "File not found: $mp4Path\n";
    exit;
}

$mp4Data = file_get_contents($mp4Path);

// 搜索avcC字符串
$pos = strpos($mp4Data, 'avcC');
if ($pos !== false) {
    echo "Found avcC at offset: $pos\n";
    
    // 读取avcC之前的4字节作为size
    $sizeData = substr($mp4Data, $pos - 4, 4);
    $size = unpack('N', $sizeData)[1];
    echo "avcC box size: $size bytes\n";
    
    // 读取avcC内容的前30字节
    $avcCContent = substr($mp4Data, $pos + 4, min(30, $size - 4));
    echo "avcC content (first 30 bytes):\n";
    $bytes = unpack('C*', $avcCContent);
    foreach ($bytes as $byte) {
        printf("%02X ", $byte);
    }
    echo "\n";
    
    // 解析基本信息
    $bytes = array_values($bytes);
    $version = $bytes[0];
    $avcProfile = $bytes[1];
    $naluLengthSize = ($bytes[4] & 0x03) + 1;
    
    echo "\nAVC Version: $version\n";
    echo "AVC Profile: " . dechex($avcProfile) . "\n";
    echo "NALU Length Size: $naluLengthSize\n";
} else {
    echo "avcC box not found!\n";
}
?>