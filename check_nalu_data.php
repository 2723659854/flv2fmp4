<?php
// 检查mdat中的NALU数据
$mp4Path = __DIR__ . '/output/output.mp4';
if (!file_exists($mp4Path)) {
    echo "File not found: $mp4Path\n";
    exit;
}

$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "MP4 file size: " . count($mp4Array) . " bytes\n\n";

// 查找mdat
$offset = 0;
$mdatOffset = -1;
$mdatSize = 0;

while ($offset < count($mp4Array) - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset+1] << 16) | 
            ($mp4Array[$offset+2] << 8) | $mp4Array[$offset+3];
    $type = chr($mp4Array[$offset+4]) . chr($mp4Array[$offset+5]) . 
            chr($mp4Array[$offset+6]) . chr($mp4Array[$offset+7]);
    
    if ($type == 'mdat') {
        $mdatOffset = $offset + 8;
        $mdatSize = $size - 8;
        break;
    }
    
    $offset += $size;
}

if ($mdatOffset == -1) {
    echo "mdat not found!\n";
    exit;
}

echo "mdat found at offset $mdatOffset, size $mdatSize\n";

// 解析NALU数据
$currentOffset = 0;
$naluCount = 0;

while ($currentOffset < $mdatSize - 4) {
    // 读取4字节长度前缀
    $naluSize = ($mp4Array[$mdatOffset + $currentOffset] << 24) | 
                ($mp4Array[$mdatOffset + $currentOffset + 1] << 16) | 
                ($mp4Array[$mdatOffset + $currentOffset + 2] << 8) | 
                $mp4Array[$mdatOffset + $currentOffset + 3];
    
    if ($naluSize == 0) {
        $currentOffset += 4;
        continue;
    }
    
    if ($currentOffset + 4 + $naluSize > $mdatSize) {
        break;
    }
    
    $naluType = $mp4Array[$mdatOffset + $currentOffset + 4] & 0x1F;
    $naluTypeName = [
        1 => 'Coded slice (non-IDR)',
        5 => 'Coded slice (IDR)',
        6 => 'SEI',
        7 => 'SPS',
        8 => 'PPS'
    ][$naluType] ?? 'Unknown (' . $naluType . ')';
    
    echo "\nNALU $naluCount:\n";
    echo "  Type: $naluTypeName (" . dechex($naluType) . ")\n";
    echo "  Size: $naluSize bytes\n";
    echo "  Offset: " . ($mdatOffset + $currentOffset) . "\n";
    
    // 显示前16字节
    echo "  First 16 bytes: ";
    for ($i = 0; $i < min(16, $naluSize); $i++) {
        printf("%02X ", $mp4Array[$mdatOffset + $currentOffset + 4 + $i]);
    }
    echo "\n";
    
    // 如果是SPS，检查sps_id
    if ($naluType == 7) {
        // SPS数据格式：nal_unit_type(1) + sps_data
        // sps_id在sps_data的指数哥伦布编码中
        $spsDataOffset = $mdatOffset + $currentOffset + 5; // 跳过NALU头
        $spsId = parseExpGolomb($mp4Array, $spsDataOffset);
        echo "  SPS ID: $spsId\n";
    }
    
    // 如果是PPS，检查pps_id
    if ($naluType == 8) {
        // PPS数据格式：nal_unit_type(1) + pps_data
        // pps_id在pps_data的指数哥伦布编码中
        $ppsDataOffset = $mdatOffset + $currentOffset + 5; // 跳过NALU头
        $ppsId = parseExpGolomb($mp4Array, $ppsDataOffset);
        echo "  PPS ID: $ppsId\n";
    }
    
    $currentOffset += 4 + $naluSize;
    $naluCount++;
    
    if ($naluCount >= 10) {
        echo "\n... (showing first 10 NALUs)\n";
        break;
    }
}

echo "\nTotal NALUs found: $naluCount\n";

// 简单的指数哥伦布解码
function parseExpGolomb($data, $offset) {
    $leadingZeroBits = 0;
    while ($offset < count($data) && $data[$offset] == 0x00) {
        $leadingZeroBits++;
        $offset++;
    }
    
    if ($offset >= count($data)) {
        return -1;
    }
    
    // 读取接下来的(leadingZeroBits + 1)位
    $value = 1;
    for ($i = 0; $i <= $leadingZeroBits; $i++) {
        $value = ($value << 1) | (($data[$offset] >> (7 - ($i % 8))) & 0x01);
        if ($i % 8 == 7) {
            $offset++;
        }
    }
    
    return $value - 1;
}
?>