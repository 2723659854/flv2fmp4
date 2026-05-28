<?php

ini_set('memory_limit', '512M');

require_once 'php/flv/FlvParse.php';
require_once 'php/flv/FlvDemux.php';
require_once 'php/flv/TagDemux.php';

echo "FLV to MP4 Converter - Detailed TagDemux Debug\n";
echo "==============================================\n\n";

// Load FLV file
$flvData = file_get_contents('test.flv');
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

echo "FLV file size: " . count($flvArray) . " bytes\n\n";

// Parse FLV
$parser = new FlvParse();
$parser->setFlv($flvArray);

echo "FLV Parser: " . count($parser->arrTag) . " tags found\n";
echo "Has Audio: " . ($parser->_hasAudio ? 'Yes' : 'No') . "\n";
echo "Has Video: " . ($parser->_hasVideo ? 'Yes' : 'No') . "\n\n";

// Create TagDemux with debugging
$tagDemux = new TagDemux();

// Set up callbacks
$trackMetadataCount = 0;
$mediaInfoCount = 0;
$dataAvailableCount = 0;

$tagDemux->setOnTrackMetadata(function($type, $meta) use (&$trackMetadataCount) {
    $trackMetadataCount++;
    echo "[CALLBACK] onTrackMetadata #$trackMetadataCount: $type\n";
    echo "           codec: " . ($meta['codec'] ?? 'N/A') . "\n";
});

$tagDemux->onMediaInfo(function($mediaInfo) use (&$mediaInfoCount) {
    $mediaInfoCount++;
    echo "[CALLBACK] onMediaInfo #$mediaInfoCount\n";
    echo "           width: " . ($mediaInfo->width ?? 'N/A') . "\n";
    echo "           height: " . ($mediaInfo->height ?? 'N/A') . "\n";
});

$tagDemux->setOnDataAvailable(function($audioTrack, $videoTrack) use (&$dataAvailableCount) {
    $dataAvailableCount++;
    echo "[CALLBACK] onDataAvailable #$dataAvailableCount\n";
    echo "           audio samples: " . count($audioTrack['samples']) . "\n";
    echo "           video samples: " . count($videoTrack['samples']) . "\n";
});

// Set audio/video flags
$tagDemux->setHasAudio($parser->_hasAudio);
$tagDemux->setHasVideo($parser->_hasVideo);

echo "TagDemux initial state:\n";
echo "  _hasAudio: " . ($parser->_hasAudio ? 'Yes' : 'No') . "\n";
echo "  _hasVideo: " . ($parser->_hasVideo ? 'Yes' : 'No') . "\n\n";

// Check first tag
$firstTag = $parser->arrTag[0];
echo "First tag:\n";
echo "  tagType: $firstTag->tagType\n";
echo "  body size: " . count($firstTag->body) . "\n";

// Parse first tag (metadata)
echo "\nParsing first tag (metadata)...\n";
$tagDemux->parseChunks($firstTag);

// Check second tag (audio)
echo "\nSecond tag:\n";
$secondTag = $parser->arrTag[1];
echo "  tagType: $secondTag->tagType\n";
echo "  body size: " . count($secondTag->body) . "\n";

echo "\nParsing second tag (audio)...\n";
$tagDemux->parseChunks($secondTag);

// Check third tag (video)
echo "\nThird tag:\n";
$thirdTag = $parser->arrTag[2];
echo "  tagType: $thirdTag->tagType\n";
echo "  body size: " . count($thirdTag->body) . "\n";

echo "\nParsing third tag (video)...\n";
$tagDemux->parseChunks($thirdTag);

echo "\n\nCallback counts after parsing first 3 tags:\n";
echo "  Track metadata callbacks: $trackMetadataCount\n";
echo "  Media info callbacks: $mediaInfoCount\n";
echo "  Data available callbacks: $dataAvailableCount\n";

// Parse all remaining tags
echo "\n\nParsing remaining " . (count($parser->arrTag) - 3) . " tags...\n";
for ($i = 3; $i < count($parser->arrTag); $i++) {
    $tag = $parser->arrTag[$i];
    $tagDemux->parseChunks($tag);
}

echo "\n\nFinal callback counts:\n";
echo "  Track metadata callbacks: $trackMetadataCount\n";
echo "  Media info callbacks: $mediaInfoCount\n";
echo "  Data available callbacks: $dataAvailableCount\n";
?>