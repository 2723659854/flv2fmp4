<?php
// 验证生成的MP4文件中的mdat数据
$mp4Path = __DIR__ . '/output/output.mp4';
$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "MP4 file size: " . count($mp4Array) . " bytes\n\n";

// 查找mdat box
$offset = 0;
while ($offset < count($mp4Array) - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset+1] << 16) | 
            ($mp4Array[$offset+2] << 8) | $mp4Array[$offset+3];
    $type = chr($mp4Array[$offset+4]) . chr($mp4Array[$offset+5]) . 
            chr($mp4Array[$offset+6]) . chr($mp4Array[$offset+7]);
    
    echo "Found box: $type at offset $offset, size: $size\n";
    
    if ($type == 'mdat') {
        $mdatOffset = $offset + 8;
        $mdatSize = $size - 8;
        
        echo "\nmdat content (first 50 bytes):\n";
        for ($i = 0; $i < min(50, $mdatSize); $i++) {
            printf("%02X ", $mp4Array[$mdatOffset + $i]);
            if (($i + 1) % 16 == 0) echo "\n";
        }
        echo "\n";
        
        // 检查第一个样本的长度前缀
        $firstSampleSize = ($mp4Array[$mdatOffset] << 24) | ($mp4Array[$mdatOffset+1] << 16) | 
                          ($mp4Array[$mdatOffset+2] << 8) | $mp4Array[$mdatOffset+3];
        echo "\nFirst sample size: $firstSampleSize bytes\n";
        
        // 检查第一个NALU的类型
        $firstNaluType = $mp4Array[$mdatOffset + 4] & 0x1F;
        $naluTypeName = [
            1 => 'Coded slice (non-IDR)',
            5 => 'Coded slice (IDR)',
            6 => 'SEI',
            7 => 'SPS',
            8 => 'PPS'
        ][$firstNaluType] ?? 'Unknown (' . $firstNaluType . ')';
        echo "First NALU type: $naluTypeName (" . dechex($firstNaluType) . ")\n";
        
        break;
    }
    
    $offset += $size;
}
?>