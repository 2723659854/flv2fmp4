<?php
// 调试 FLV 解析过程
require_once 'php/flv/FlvParse.php';

$flvPath = __DIR__ . '/test.flv';
$flvData = file_get_contents($flvPath);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

echo "FLV file size: " . count($flvArray) . " bytes\n";

$parser = new FlvParse();

// 设置回调来追踪解析过程
$tagCount = 0;
$parser->setOnTag(function($tag) use (&$tagCount) {
    $tagCount++;
    $tagTypeName = ['8' => 'Audio', '9' => 'Video', '18' => 'Script'][($tag->tagType)] ?? 'Unknown';
    $dataSize = ($tag->dataSize[0] << 16) | ($tag->dataSize[1] << 8) | $tag->dataSize[2];
    $timestamp = ($tag->Timestamp[0] << 24) | ($tag->Timestamp[1] << 16) | ($tag->Timestamp[2] << 8) | $tag->Timestamp[3];
    
    echo "Tag #$tagCount: Type=$tagTypeName ({$tag->tagType}), Size=$dataSize, Timestamp=$timestamp\n";
    
    // 显示前几个视频标签的内容
    if ($tag->tagType == 9 && $tagCount <= 5) {
        if (count($tag->body) > 0) {
            $frameType = ($tag->body[0] >> 4) & 0x0F;
            $codecId = $tag->body[0] & 0x0F;
            echo "  Frame Type: " . ($frameType == 1 ? "Keyframe" : "Inter frame") . "\n";
            echo "  Codec ID: " . ($codecId == 7 ? "AVC" : "Unknown (" . $codecId . ")") . "\n";
            
            if ($codecId == 7 && count($tag->body) > 1) {
                $avcPacketType = $tag->body[1];
                echo "  AVC Packet Type: " . ($avcPacketType == 0 ? "Sequence header" : ($avcPacketType == 1 ? "NALU" : "End of sequence")) . "\n";
            }
        }
    }
});

$offset = $parser->setFlv($flvArray);
echo "\nParsing completed at offset: $offset\n";
echo "Total tags found: $tagCount\n";
echo "Has Audio: " . ($parser->_hasAudio ? "Yes" : "No") . "\n";
echo "Has Video: " . ($parser->_hasVideo ? "Yes" : "No") . "\n";
?>