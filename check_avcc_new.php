<?php
// 检查新生成的MP4文件中的avcC box
$mp4Path = __DIR__ . '/output/output.mp4';
if (!file_exists($mp4Path)) {
    echo "File not found: $mp4Path\n";
    exit;
}

$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "MP4 file size: " . count($mp4Array) . " bytes\n\n";

// 查找stsd中的avcC
$offset = 0;
while ($offset < count($mp4Array) - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset+1] << 16) | 
            ($mp4Array[$offset+2] << 8) | $mp4Array[$offset+3];
    $type = chr($mp4Array[$offset+4]) . chr($mp4Array[$offset+5]) . 
            chr($mp4Array[$offset+6]) . chr($mp4Array[$offset+7]);
    
    if ($type == 'stsd') {
        echo "Found stsd box at offset $offset, size: $size\n";
        
        $stsdOffset = $offset + 8;
        $version = $mp4Array[$stsdOffset];
        $flags = ($mp4Array[$stsdOffset + 1] << 16) | ($mp4Array[$stsdOffset + 2] << 8) | $mp4Array[$stsdOffset + 3];
        $entryCount = ($mp4Array[$stsdOffset + 4] << 24) | ($mp4Array[$stsdOffset + 5] << 16) | 
                      ($mp4Array[$stsdOffset + 6] << 8) | $mp4Array[$stsdOffset + 7];
        
        echo "  Version: $version, Flags: " . dechex($flags) . ", Entry count: $entryCount\n";
        
        // avc1 sample entry
        $avc1Offset = $stsdOffset + 8;
        $avc1Size = ($mp4Array[$avc1Offset] << 24) | ($mp4Array[$avc1Offset + 1] << 16) | 
                    ($mp4Array[$avc1Offset + 2] << 8) | $mp4Array[$avc1Offset + 3];
        $avc1Type = chr($mp4Array[$avc1Offset + 4]) . chr($mp4Array[$avc1Offset + 5]) . 
                    chr($mp4Array[$avc1Offset + 6]) . chr($mp4Array[$avc1Offset + 7]);
        
        echo "  Sample entry: $avc1Type, Size: $avc1Size\n";
        
        // 检查avc1内部的avcC
        $innerOffset = $avc1Offset + 8; // skip avc1 box header
        while ($innerOffset < $avc1Offset + $avc1Size - 8) {
            $innerSize = ($mp4Array[$innerOffset] << 24) | ($mp4Array[$innerOffset + 1] << 16) | 
                         ($mp4Array[$innerOffset + 2] << 8) | $mp4Array[$innerOffset + 3];
            $innerType = chr($mp4Array[$innerOffset + 4]) . chr($mp4Array[$innerOffset + 5]) . 
                         chr($mp4Array[$innerOffset + 6]) . chr($mp4Array[$innerOffset + 7]);
            
            echo "    Inner box: $innerType, Offset: $innerOffset, Size: $innerSize\n";
            
            if ($innerType == 'avcC') {
                $avcCOffset = $innerOffset + 8;
                $version = $mp4Array[$avcCOffset];
                $avcProfile = $mp4Array[$avcCOffset + 1];
                $profileCompatibility = $mp4Array[$avcCOffset + 2];
                $avcLevel = $mp4Array[$avcCOffset + 3];
                $naluLengthSize = ($mp4Array[$avcCOffset + 4] & 0x03) + 1;
                $spsCount = $mp4Array[$avcCOffset + 5] & 0x1F;
                
                echo "      AVC Version: $version\n";
                echo "      AVC Profile: " . dechex($avcProfile) . "\n";
                echo "      Profile Compatibility: " . dechex($profileCompatibility) . "\n";
                echo "      AVC Level: " . dechex($avcLevel) . "\n";
                echo "      NALU Length Size: $naluLengthSize\n";
                echo "      SPS Count: $spsCount\n";
                
                // 解析SPS
                $spsOffset = $avcCOffset + 6;
                for ($i = 0; $i < $spsCount; $i++) {
                    $spsSize = ($mp4Array[$spsOffset] << 8) | $mp4Array[$spsOffset + 1];
                    $spsOffset += 2;
                    
                    echo "\n      SPS $i: Size=$spsSize\n";
                    echo "      SPS data (first 20 bytes): ";
                    for ($j = 0; $j < min(20, $spsSize); $j++) {
                        printf("%02X ", $mp4Array[$spsOffset + $j]);
                    }
                    echo "\n";
                    
                    $naluType = $mp4Array[$spsOffset] & 0x1F;
                    echo "      NALU type: " . ($naluType == 7 ? "SPS (7)" : "Unknown (" . $naluType . ")") . "\n";
                    
                    $spsOffset += $spsSize;
                }
                
                // 解析PPS
                $ppsCount = $mp4Array[$spsOffset];
                echo "\n      PPS Count: $ppsCount\n";
                $spsOffset++;
                
                for ($i = 0; $i < $ppsCount; $i++) {
                    $ppsSize = ($mp4Array[$spsOffset] << 8) | $mp4Array[$spsOffset + 1];
                    $spsOffset += 2;
                    
                    echo "\n      PPS $i: Size=$ppsSize\n";
                    echo "      PPS data (first 20 bytes): ";
                    for ($j = 0; $j < min(20, $ppsSize); $j++) {
                        printf("%02X ", $mp4Array[$spsOffset + $j]);
                    }
                    echo "\n";
                    
                    $naluType = $mp4Array[$spsOffset] & 0x1F;
                    echo "      NALU type: " . ($naluType == 8 ? "PPS (8)" : "Unknown (" . $naluType . ")") . "\n";
                    
                    $spsOffset += $ppsSize;
                }
            }
            
            $innerOffset += $innerSize;
        }
        
        break;
    }
    
    $offset += $size;
}
?>