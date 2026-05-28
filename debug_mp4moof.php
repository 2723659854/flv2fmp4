<?php
// 调试 MP4Moof 中的数据处理
require_once 'php/flv/FlvParse.php';
require_once 'php/flv/TagDemux.php';
require_once 'php/mp4/MP4Moof.php';

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
    echo "\n=== Data Available ===\n";
    echo "Audio samples in callback: " . count($audioTrack['samples']) . "\n";
    echo "Video samples in callback: " . count($videoTrack['samples']) . "\n";
    
    $videoSamples = $videoTrack['samples'];
    $audioSamples = $audioTrack['samples'];
    
    // 检查第一个视频样本
    if (count($videoSamples) > 0) {
        $firstVideo = $videoSamples[0];
        echo "\nFirst video sample:\n";
        echo "  DTS: {$firstVideo['dts']}\n";
        echo "  PTS: {$firstVideo['pts']}\n";
        echo "  Is Keyframe: " . ($firstVideo['isKeyframe'] ? "Yes" : "No") . "\n";
        echo "  Units count: " . count($firstVideo['units']) . "\n";
        
        foreach ($firstVideo['units'] as $idx => $unit) {
            echo "    Unit $idx: Type=" . dechex($unit['type']) . ", Size=" . count($unit['data']) . " bytes\n";
            if ($idx == 0 && count($unit['data']) > 0) {
                echo "    First 10 bytes: ";
                for ($i = 0; $i < min(10, count($unit['data'])); $i++) {
                    printf("%02X ", $unit['data'][$i]);
                }
                echo "\n";
            }
        }
    }
    
    // 检查第一个音频样本
    if (count($audioSamples) > 0) {
        $firstAudio = $audioSamples[0];
        echo "\nFirst audio sample:\n";
        echo "  DTS: {$firstAudio['dts']}\n";
        echo "  PTS: {$firstAudio['pts']}\n";
        echo "  Size: " . count($firstAudio['unit']) . " bytes\n";
        echo "  First 10 bytes: ";
        for ($i = 0; $i < min(10, count($firstAudio['unit'])); $i++) {
            printf("%02X ", $firstAudio['unit'][$i]);
        }
        echo "\n";
    }
});

// 设置音视频标志
$parser->setOnTag(function($tag) use ($demuxer) {
    $demuxer->parseChunks($tag);
});

echo "Starting parsing...\n";
$offset = $parser->setFlv($flvArray);

echo "\n=== Parsing Complete ===\n";
echo "Total video samples: " . count($videoSamples) . "\n";
echo "Total audio samples: " . count($audioSamples) . "\n";

// 检查视频样本中的第一个 NALU 是否是关键帧
if (count($videoSamples) > 0) {
    foreach ($videoSamples as $idx => $sample) {
        if ($sample['isKeyframe']) {
            echo "\nKeyframe found at sample $idx\n";
            echo "  DTS: {$sample['dts']}\n";
            foreach ($sample['units'] as $uIdx => $unit) {
                $naluType = $unit['type'] & 0x1F;
                $naluTypeName = [
                    1 => 'Coded slice (non-IDR)',
                    5 => 'Coded slice (IDR)',
                    7 => 'SPS',
                    8 => 'PPS'
                ][$naluType] ?? 'Unknown (' . $naluType . ')';
                echo "    NALU $uIdx: Type=$naluTypeName (" . dechex($unit['type']) . "), Size=" . count($unit['data']) . "\n";
            }
            break;
        }
    }
}
?>