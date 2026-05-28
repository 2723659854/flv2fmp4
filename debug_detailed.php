<?php
// 详细调试数据收集流程
require_once 'php/flv/FlvParse.php';
require_once 'php/flv/TagDemux.php';

$flvPath = __DIR__ . '/test.flv';
$flvData = file_get_contents($flvPath);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

echo "FLV file size: " . count($flvArray) . " bytes\n\n";

$parser = new FlvParse();
$demuxer = new TagDemux();

// 设置 hasAudio 和 hasVideo
$demuxer->setHasAudio(true);
$demuxer->setHasVideo(true);

// 设置回调来追踪解析过程
$tagCount = 0;
$audioDataCount = 0;
$videoDataCount = 0;
$audioMetadataDispatched = false;
$videoMetadataDispatched = false;

$parser->setOnTag(function($tag) use ($demuxer, &$tagCount) {
    $tagCount++;
    $demuxer->parseChunks($tag);
});

$demuxer->onMediaInfo(function($mi) {
    echo "=== Media Info Dispatched ===\n";
});

$demuxer->setOnTrackMetadata(function($type, $meta) use (&$audioMetadataDispatched, &$videoMetadataDispatched) {
    echo "=== Track Metadata ($type) ===\n";
    echo "Codec: {$meta['codec']}\n";
    if ($type === 'audio') {
        $audioMetadataDispatched = true;
    } else {
        $videoMetadataDispatched = true;
    }
});

$demuxer->setOnDataAvailable(function($audioTrack, $videoTrack) {
    global $audioDataCount, $videoDataCount;
    $newAudio = count($audioTrack['samples']);
    $newVideo = count($videoTrack['samples']);
    
    echo "\n=== onDataAvailable Called ===\n";
    echo "Audio samples: $newAudio\n";
    echo "Video samples: $newVideo\n";
    
    $audioDataCount += $newAudio;
    $videoDataCount += $newVideo;
});

echo "Starting parsing...\n";
$offset = $parser->setFlv($flvArray);

echo "\n=== Final State ===\n";
echo "Total tags parsed: $tagCount\n";
echo "Audio samples collected: $audioDataCount\n";
echo "Video samples collected: $videoDataCount\n";
echo "Audio Metadata Dispatched: " . ($audioMetadataDispatched ? "Yes" : "No") . "\n";
echo "Video Metadata Dispatched: " . ($videoMetadataDispatched ? "Yes" : "No") . "\n";
?>