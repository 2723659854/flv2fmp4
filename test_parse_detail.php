<?php

ini_set('memory_limit', '512M');

require_once 'php/flv/FlvParse.php';
require_once 'php/flv/FlvDemux.php';
require_once 'php/flv/TagDemux.php';

echo "FLV to MP4 Converter - Parse Detail Debug\n";
echo "==========================================\n\n";

// Load FLV file
$flvData = file_get_contents('test.flv');
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

// Parse FLV
$parser = new FlvParse();
$parser->setFlv($flvArray);

echo "FLV Parser: " . count($parser->arrTag) . " tags found\n\n";

// Check second tag (audio) in detail
$secondTag = $parser->arrTag[1];
echo "Second tag (audio):\n";
echo "  tagType: $secondTag->tagType\n";
echo "  body size: " . count($secondTag->body) . "\n";
echo "  body[0] (sound spec): " . $secondTag->body[0] . "\n";
$soundSpec = $secondTag->body[0];
$soundFormat = $soundSpec >> 4;
echo "  soundFormat: $soundFormat (expected 10 for AAC)\n";
$soundRateIndex = ($soundSpec & 12) >> 2;
echo "  soundRateIndex: $soundRateIndex\n";
$soundType = $soundSpec & 1;
echo "  soundType: $soundType (0=mono, 1=stereo)\n\n";

// Check third tag (video) in detail
$thirdTag = $parser->arrTag[2];
echo "Third tag (video):\n";
echo "  tagType: $thirdTag->tagType\n";
echo "  body size: " . count($thirdTag->body) . "\n";
echo "  body[0] (spec): " . $thirdTag->body[0] . "\n";
$spec = $thirdTag->body[0];
$frameType = ($spec & 240) >> 4;
$codecId = $spec & 15;
echo "  frameType: $frameType (1=keyframe, 2=inter)\n";
echo "  codecId: $codecId (expected 7 for AVC/H.264)\n\n";

// Now test _parseAudioData directly
echo "Testing _parseAudioData directly:\n";
$tagDemux = new TagDemux();
$tagDemux->setHasAudio(true);
$tagDemux->setHasVideo(true);

// Access private method via reflection
$reflection = new ReflectionClass($tagDemux);
$method = $reflection->getMethod('_parseAudioData');
$method->setAccessible(true);

echo "Calling _parseAudioData...\n";
$method->invoke($tagDemux, $secondTag->body, 0, count($secondTag->body), $secondTag->getTime());

echo "\nAfter _parseAudioData:\n";
echo "  _audioMetadata: " . ($tagDemux->_audioMetadata ? 'set' : 'null') . "\n";
if ($tagDemux->_audioMetadata) {
    echo "  codec: " . ($tagDemux->_audioMetadata['codec'] ?? 'N/A') . "\n";
}

// Test _parseVideoData directly
echo "\nTesting _parseVideoData directly:\n";
$tagDemux2 = new TagDemux();
$tagDemux2->setHasAudio(true);
$tagDemux2->setHasVideo(true);

$method2 = $reflection->getMethod('_parseVideoData');
$method2->setAccessible(true);

echo "Calling _parseVideoData...\n";
$method2->invoke($tagDemux2, $thirdTag->body, 0, count($thirdTag->body), $thirdTag->getTime(), 0);

echo "\nAfter _parseVideoData:\n";
echo "  _videoMetadata: " . ($tagDemux2->_videoMetadata ? 'set' : 'null') . "\n";
if ($tagDemux2->_videoMetadata) {
    echo "  codec: " . ($tagDemux2->_videoMetadata['codec'] ?? 'N/A') . "\n";
}
?>