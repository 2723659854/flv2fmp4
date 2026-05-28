<?php
// 调试完整的数据收集流程
require_once 'php/flv/FlvParse.php';
require_once 'php/flv/TagDemux.php';

$flvPath = __DIR__ . '/test.flv';
$flvData = file_get_contents($flvPath);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

echo "FLV file size: " . count($flvArray) . " bytes\n\n";

$parser = new FlvParse();
$demuxer = new TagDemux();

// 设置回调来追踪解析过程
$videoSamples = [];
$audioSamples = [];

$demuxer->setOnTrackMetadata(function($type, $meta) {
    echo "=== Track Metadata ($type) ===\n";
    echo "Codec: {$meta['codec']}\n";
});

$demuxer->setOnDataAvailable(function($audioTrack, $videoTrack) use (&$videoSamples, &$audioSamples) {
    echo "\n=== onDataAvailable Called ===\n";
    echo "Audio samples: " . count($audioTrack['samples']) . "\n";
    echo "Video samples: " . count($videoTrack['samples']) . "\n";
    
    // 追加到全局数组
    $videoSamples = array_merge($videoSamples, $videoTrack['samples']);
    $audioSamples = array_merge($audioSamples, $audioTrack['samples']);
    
    // 检查第一个视频样本
    if (count($videoTrack['samples']) > 0) {
        $firstVideo = $videoTrack['samples'][0];
        echo "\nFirst video sample in this batch:\n";
        echo "  DTS: {$firstVideo['dts']}\n";
        echo "  PTS: {$firstVideo['pts']}\n";
        echo "  Is Keyframe: " . ($firstVideo['isKeyframe'] ? "Yes" : "No") . "\n";
        echo "  Units count: " . count($firstVideo['units']) . "\n";
        
        foreach ($firstVideo['units'] as $idx => $unit) {
            $naluType = $unit['type'] & 0x1F;
            $naluTypeName = [
                1 => 'Coded slice (non-IDR)',
                5 => 'Coded slice (IDR)',
                7 => 'SPS',
                8 => 'PPS'
            ][$naluType] ?? 'Unknown (' . $naluType . ')';
            echo "    Unit $idx: Type=$naluTypeName (" . dechex($unit['type']) . "), Size=" . count($unit['data']) . "\n";
            if ($idx == 0 && count($unit['data']) > 0) {
                echo "    First 10 bytes: ";
                for ($i = 0; $i < min(10, count($unit['data'])); $i++) {
                    printf("%02X ", $unit['data'][$i]);
                }
                echo "\n";
            }
        }
    }
});

// 设置音视频标志
$parser->setOnTag(function($tag) use ($demuxer) {
    // 直接调用 parseChunks，但需要通过 moofTag 来触发回调
    $demuxer->_dispatch = true;
    $demuxer->parseChunks($tag);
    
    // 检查是否应该触发回调
    if ($demuxer->_isInitialMetadataDispatched()) {
        if ($demuxer->_dispatch && ($demuxer->_audioTrack['length'] || $demuxer->_videoTrack['length'])) {
            if ($demuxer->_onDataAvailable) {
                ($demuxer->_onDataAvailable)($demuxer->_audioTrack, $demuxer->_videoTrack);
                // 清空已处理的样本
                $demuxer->_audioTrack['samples'] = [];
                $demuxer->_audioTrack['length'] = 0;
                $demuxer->_videoTrack['samples'] = [];
                $demuxer->_videoTrack['length'] = 0;
            }
        }
    }
});

echo "Starting parsing...\n";
$offset = $parser->setFlv($flvArray);

echo "\n=== Parsing Complete ===\n";
echo "Total video samples collected: " . count($videoSamples) . "\n";
echo "Total audio samples collected: " . count($audioSamples) . "\n";
?>