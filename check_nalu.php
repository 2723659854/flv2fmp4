<?php
// 检查 mdat 中的视频数据
$filePath = __DIR__ . '/output/output.mp4';

if (!file_exists($filePath)) {
    die("File not found: $filePath\n");
}

$fileData = file_get_contents($filePath);
$bytes = unpack('C*', $fileData);
$data = array_values($bytes);

echo "File size: " . count($data) . " bytes\n";

// 找到 mdat box
$offset = 0;
$mdatOffset = -1;
$mdatSize = 0;

while ($offset < count($data)) {
    if ($offset + 7 >= count($data)) break;
    
    $size = ($data[$offset] << 24) | ($data[$offset+1] << 16) | ($data[$offset+2] << 8) | $data[$offset+3];
    $type = chr($data[$offset+4]) . chr($data[$offset+5]) . chr($data[$offset+6]) . chr($data[$offset+7]);
    
    if ($type == 'mdat') {
        $mdatOffset = $offset;
        $mdatSize = $size;
        break;
    }
    
    $offset += $size;
}

if ($mdatOffset == -1) {
    die("mdat box not found\n");
}

echo "mdat found at offset $mdatOffset, size $mdatSize\n";

// 检查 mdat 中的数据（跳过前8字节的 box 头部）
$mdatDataOffset = $mdatOffset + 8;
$mdatEndOffset = $mdatOffset + $mdatSize;

// 查找 NALU 起始码 0x00 0x00 0x00 0x01
$naluCount = 0;
for ($i = $mdatDataOffset; $i < $mdatEndOffset - 3; $i++) {
    if ($data[$i] == 0x00 && $data[$i+1] == 0x00 && $data[$i+2] == 0x00 && $data[$i+3] == 0x01) {
        $naluCount++;
        $naluType = $data[$i+4] & 0x1F;
        $typeName = '';
        switch ($naluType) {
            case 1: $typeName = 'Coded slice (non-IDR)'; break;
            case 5: $typeName = 'Coded slice (IDR)'; break;
            case 6: $typeName = 'SEI'; break;
            case 7: $typeName = 'SPS'; break;
            case 8: $typeName = 'PPS'; break;
            default: $typeName = "Unknown ($naluType)";
        }
        echo sprintf("NALU found at offset %d, type: %s (0x%02X)\n", $i, $typeName, $naluType);
        
        if ($naluCount <= 5) {
            // 显示前20字节
            echo "  First 20 bytes: ";
            for ($j = 0; $j < min(20, $mdatEndOffset - $i); $j++) {
                printf("%02X ", $data[$i + $j]);
            }
            echo "\n";
        }
    }
}

echo "\nTotal NALUs found: $naluCount\n";

// 检查前几个字节
echo "\nFirst 32 bytes of mdat content:\n";
for ($i = 0; $i < min(32, $mdatSize - 8); $i++) {
    printf("%02X ", $data[$mdatDataOffset + $i]);
}
echo "\n";
?>