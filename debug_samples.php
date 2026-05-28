<?php
// 调试视频样本收集过程
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
$videoSampleCount = 0;
$audioSampleCount = 0;

$demuxer->onMediaInfo(function($mi) {
    echo "=== Media Info ===\n";
    echo "Width: {$mi->width}\n";
    echo "Height: {$mi->height}\n";
    echo "Has Audio: " . ($mi->hasAudio ? "Yes" : "No") . "\n";
    echo "Has Video: " . ($mi->hasVideo ? "Yes" : "No") . "\n";
});

$demuxer->setOnTrackMetadata(function($type, $meta) {
    echo "\n=== Track Metadata ($type) ===\n";
    echo "Codec: {$meta['codec']}\n";
    if ($type === 'video') {
        echo "Codec Width: {$meta['codecWidth']}, Height: {$meta['codecHeight']}\n";
        echo "NALU Length Size: " . (isset($GLOBALS['demuxer']->_naluLengthSize) ? $GLOBALS['demuxer']->_naluLengthSize : 'N/A') . "\n";
    }
});

$demuxer->setOnDataAvailable(function($audioTrack, $videoTrack) use (&$audioSampleCount, &$videoSampleCount) {
    $newAudio = count($audioTrack['samples']);
    $newVideo = count($videoTrack['samples']);
    
    echo "\n=== Data Available ===\n";
    echo "Audio samples: $newAudio (total: " . ($audioSampleCount + $newAudio) . ")\n";
    echo "Video samples: $newVideo (total: " . ($videoSampleCount + $newVideo) . ")\n";
    
    // 检查第一个视频样本
    if ($newVideo > 0 && $videoSampleCount == 0) {
        $firstSample = $videoTrack['samples'][0];
        echo "\nFirst video sample:\n";
        echo "  DTS: {$firstSample['dts']}\n";
        echo "  PTS: {$firstSample['pts']}\n";
        echo "  CTS: {$firstSample['cts']}\n";
        echo "  Is Keyframe: " . ($firstSample['isKeyframe'] ? "Yes" : "No") . "\n";
        echo "  Units count: " . count($firstSample['units']) . "\n";
        
        foreach ($firstSample['units'] as $idx => $unit) {
            echo "    Unit $idx: Type=" . dechex($unit['type']) . ", Size=" . count($unit['data']) . " bytes\n";
            if ($idx == 0) {
                echo "    First 10 bytes: ";
                for ($i = 0; $i < min(10, count($unit['data'])); $i++) {
                    printf("%02X ", $unit['data'][$i]);
                }
                echo "\n";
            }
        }
    }
    
    $audioSampleCount += $newAudio;
    $videoSampleCount += $newVideo;
});

// 设置音视频标志
$parser->setOnTag(function($tag) use ($demuxer) {
    $demuxer->parseChunks($tag);
});

echo "Starting parsing...\n";
$offset = $parser->setFlv($flvArray);

echo "\n=== Parsing Complete ===\n";
echo "Total offset: $offset\n";
echo "Total audio samples: $audioSampleCount\n";
echo "Total video samples: $videoSampleCount\n";
?>