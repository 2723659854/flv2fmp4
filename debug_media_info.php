<?php
require 'php/flv/FlvParse.php';
require 'php/flv/FlvTag.php';
require 'php/flv/TagDemux.php';

$flvData = file_get_contents('test.flv');
echo "FLV文件大小: " . strlen($flvData) . " bytes\n";

$arrayBuffer = [];
for ($i = 0; $i < strlen($flvData); $i++) {
    $arrayBuffer[] = ord($flvData[$i]);
}

$tagDemux = new TagDemux();

$tagDemux->setOnTrackMetadata(function($type, $meta) {
    echo "\n=== 视频元数据 ===\n";
    echo "类型: $type\n";
    echo "codecWidth: " . ($meta['codecWidth'] ?? '未设置') . "\n";
    echo "codecHeight: " . ($meta['codecHeight'] ?? '未设置') . "\n";
    echo "presentWidth: " . ($meta['presentWidth'] ?? '未设置') . "\n";
    echo "presentHeight: " . ($meta['presentHeight'] ?? '未设置') . "\n";
    echo "codec: " . ($meta['codec'] ?? '未设置') . "\n";
    echo "profile: " . ($meta['profile'] ?? '未设置') . "\n";
    echo "level: " . ($meta['level'] ?? '未设置') . "\n";
});

$tagDemux->setOnDataAvailable(function($audioTrack, $videoTrack) {
    echo "\n=== 数据回调 ===\n";
    echo "音频样本数: " . count($audioTrack['samples']) . "\n";
    echo "视频样本数: " . count($videoTrack['samples']) . "\n";
});

$tagDemux->moofTag($arrayBuffer, 0, count($arrayBuffer));

echo "\n=== 媒体信息 ===\n";
$mediaInfo = $tagDemux->getMediaInfo();
echo "宽度: " . ($mediaInfo['width'] ?? 0) . "\n";
echo "高度: " . ($mediaInfo['height'] ?? 0) . "\n";
echo "帧率: " . ($mediaInfo['fps'] ?? 0) . "\n";
?>