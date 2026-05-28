<?php
// 检查MP4文件中的avcC box
$mp4Path = __DIR__ . '/output/output.mp4';
$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "MP4 file size: " . count($mp4Array) . " bytes\n\n";

// 查找avcC box
$offset = 0;
while ($offset < count($mp4Array) - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset+1] << 16) | 
            ($mp4Array[$offset+2] << 8) | $mp4Array[$offset+3];
    $type = chr($mp4Array[$offset+4]) . chr($mp4Array[$offset+5]) . 
            chr($mp4Array[$offset+6]) . chr($mp4Array[$offset+7]);
    
    if ($type == 'avcC') {
        $avcCOffset = $offset + 8;
        $avcCSize = $size - 8;
        
        echo "Found avcC box at offset $offset, size: $size\n";
        echo "\navcC content (first 50 bytes):\n";
        for ($i = 0; $i < min(50, $avcCSize); $i++) {
            printf("%02X ", $mp4Array[$avcCOffset + $i]);
            if (($i + 1) % 16 == 0) echo "\n";
        }
        echo "\n";
        
        // 解析avcC结构
        $version = $mp4Array[$avcCOffset];
        $avcProfile = $mp4Array[$avcCOffset + 1];
        $profileCompatibility = $mp4Array[$avcCOffset + 2];
        $avcLevel = $mp4Array[$avcCOffset + 3];
        $naluLengthSize = ($mp4Array[$avcCOffset + 4] & 0x03) + 1;
        $spsCount = $mp4Array[$avcCOffset + 5] & 0x1F;
        
        echo "\navcC structure:\n";
        echo "  Version: $version\n";
        echo "  AVC Profile: " . dechex($avcProfile) . "\n";
        echo "  Profile Compatibility: " . dechex($profileCompatibility) . "\n";
        echo "  AVC Level: " . dechex($avcLevel) . "\n";
        echo "  NALU Length Size: $naluLengthSize\n";
        echo "  SPS Count: $spsCount\n";
        
        // 解析SPS
        $spsOffset = $avcCOffset + 6;
        for ($i = 0; $i < $spsCount; $i++) {
            $spsSize = ($mp4Array[$spsOffset] << 8) | $mp4Array[$spsOffset + 1];
            $spsOffset += 2;
            
            echo "\n  SPS $i: Size=$spsSize\n";
            echo "  SPS data (first 20 bytes): ";
            for ($j = 0; $j < min(20, $spsSize); $j++) {
                printf("%02X ", $mp4Array[$spsOffset + $j]);
            }
            echo "\n";
            
            // 检查SPS的nal_unit_type
            $naluType = $mp4Array[$spsOffset] & 0x1F;
            echo "  NALU type: " . ($naluType == 7 ? "SPS (7)" : "Unknown (" . $naluType . ")") . "\n";
            
            $spsOffset += $spsSize;
        }
        
        // 解析PPS
        $ppsCount = $mp4Array[$spsOffset];
        echo "\n  PPS Count: $ppsCount\n";
        $spsOffset++;
        
        for ($i = 0; $i < $ppsCount; $i++) {
            $ppsSize = ($mp4Array[$spsOffset] << 8) | $mp4Array[$spsOffset + 1];
            $spsOffset += 2;
            
            echo "\n  PPS $i: Size=$ppsSize\n";
            echo "  PPS data (first 20 bytes): ";
            for ($j = 0; $j < min(20, $ppsSize); $j++) {
                printf("%02X ", $mp4Array[$spsOffset + $j]);
            }
            echo "\n";
            
            // 检查PPS的nal_unit_type
            $naluType = $mp4Array[$spsOffset] & 0x1F;
            echo "  NALU type: " . ($naluType == 8 ? "PPS (8)" : "Unknown (" . $naluType . ")") . "\n";
            
            $spsOffset += $ppsSize;
        }
        
        break;
    }
    
    $offset += $size;
}
?>