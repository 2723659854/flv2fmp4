<?php
// 检查trak内部结构
$mp4Path = __DIR__ . '/output/output.mp4';
if (!file_exists($mp4Path)) {
    echo "File not found: $mp4Path\n";
    exit;
}

$mp4Data = file_get_contents($mp4Path);
$mp4Bytes = unpack('C*', $mp4Data);
$mp4Array = array_values($mp4Bytes);

echo "=== Checking trak box (offset 140, size 482) ===\n";
$trakOffset = 140 + 8; // skip box header

$offset = $trakOffset;
while ($offset < $trakOffset + 482 - 8) {
    $size = ($mp4Array[$offset] << 24) | ($mp4Array[$offset+1] << 16) | 
            ($mp4Array[$offset+2] << 8) | $mp4Array[$offset+3];
    $type = chr($mp4Array[$offset+4]) . chr($mp4Array[$offset+5]) . 
            chr($mp4Array[$offset+6]) . chr($mp4Array[$offset+7]);
    
    echo "  Sub-box: $type, Offset: $offset, Size: $size\n";
    
    if ($type == 'mdia') {
        echo "    === Checking mdia ===\n";
        $mdiaOffset = $offset + 8;
        $mdiaEnd = $offset + $size;
        $mOffset = $mdiaOffset;
        while ($mOffset < $mdiaEnd - 8) {
            $mSize = ($mp4Array[$mOffset] << 24) | ($mp4Array[$mOffset+1] << 16) | 
                     ($mp4Array[$mOffset+2] << 8) | $mp4Array[$mOffset+3];
            $mType = chr($mp4Array[$mOffset+4]) . chr($mp4Array[$mOffset+5]) . 
                     chr($mp4Array[$mOffset+6]) . chr($mp4Array[$mOffset+7]);
            
            echo "      Sub-box: $mType, Offset: $mOffset, Size: $mSize\n";
            
            if ($mType == 'minf') {
                echo "        === Checking minf ===\n";
                $minfOffset = $mOffset + 8;
                $minfEnd = $mOffset + $mSize;
                $miOffset = $minfOffset;
                while ($miOffset < $minfEnd - 8) {
                    $miSize = ($mp4Array[$miOffset] << 24) | ($mp4Array[$miOffset+1] << 16) | 
                              ($mp4Array[$miOffset+2] << 8) | $mp4Array[$miOffset+3];
                    $miType = chr($mp4Array[$miOffset+4]) . chr($mp4Array[$miOffset+5]) . 
                              chr($mp4Array[$miOffset+6]) . chr($mp4Array[$miOffset+7]);
                    
                    echo "        Sub-box: $miType, Offset: $miOffset, Size: $miSize\n";
                    
                    if ($miType == 'stbl') {
                        echo "          === Checking stbl ===\n";
                        $stblOffset = $miOffset + 8;
                        $stblEnd = $miOffset + $miSize;
                        $sOffset = $stblOffset;
                        while ($sOffset < $stblEnd - 8) {
                            $sSize = ($mp4Array[$sOffset] << 24) | ($mp4Array[$sOffset+1] << 16) | 
                                     ($mp4Array[$sOffset+2] << 8) | $mp4Array[$sOffset+3];
                            $sType = chr($mp4Array[$sOffset+4]) . chr($mp4Array[$sOffset+5]) . 
                                     chr($mp4Array[$sOffset+6]) . chr($mp4Array[$sOffset+7]);
                            
                            echo "            Sub-box: $sType, Offset: $sOffset, Size: $sSize\n";
                            
                            $sOffset += $sSize;
                        }
                    }
                    
                    $miOffset += $miSize;
                }
            }
            
            $mOffset += $mSize;
        }
    }
    
    $offset += $size;
}
?>