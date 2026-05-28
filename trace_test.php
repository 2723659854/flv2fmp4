<?php

ini_set('memory_limit', '512M');

require_once 'php/flv/FlvParse.php';
require_once 'php/flv/FlvDemux.php';
require_once 'php/flv/TagDemux.php';

echo "FLV to MP4 Converter - Trace Test\n";
echo "=================================\n\n";

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
echo "Has Video: " . ($parser->_hasVideo ? 'Yes' : 'No') . "\n";

// Check first few tags
echo "\nFirst 5 tags:\n";
for ($i = 0; $i < min(5, count($parser->arrTag)); $i++) {
    $tag = $parser->arrTag[$i];
    echo "Tag " . ($i+1) . ": type=" . $tag->tagType . ", time=" . $tag->getTime() . ", bodySize=" . count($tag->body) . "\n";
}

// Test metadata parsing with first tag (should be metadata)
echo "\n\nTesting metadata parsing with first tag:\n";
$firstTag = $parser->arrTag[0];
if ($firstTag->tagType == 18) {
    echo "First tag is metadata (type 18)\n";
    $meta = FlvDemux::parseMetadata($firstTag->body);
    echo "Parsed metadata keys: " . implode(', ', array_keys($meta)) . "\n";
    if (isset($meta['onMetaData'])) {
        $onMetaData = $meta['onMetaData'];
        echo "onMetaData keys: " . count((array)$onMetaData) . "\n";
        if (is_object($onMetaData)) {
            $props = get_object_vars($onMetaData);
            foreach ($props as $key => $value) {
                if (is_scalar($value)) {
                    echo "  $key: $value\n";
                } else {
                    echo "  $key: " . gettype($value) . "\n";
                }
            }
        }
    }
}

// Now test the full conversion flow
echo "\n\nTesting full TagDemux flow:\n";
echo "----------------------------\n";

$tagDemux = new TagDemux();

$trackMetadataReceived = [];
$mediaInfoReceived = null;
$dataAvailableCalled = false;

$tagDemux->setOnTrackMetadata(function($type, $meta) use (&$trackMetadataReceived) {
    echo "[CALLBACK] onTrackMetadata: $type\n";
    $trackMetadataReceived[] = $type;
    echo "           codec: " . ($meta['codec'] ?? 'N/A') . "\n";
});

$tagDemux->onMediaInfo(function($mediaInfo) use (&$mediaInfoReceived) {
    echo "[CALLBACK] onMediaInfo\n";
    $mediaInfoReceived = $mediaInfo;
});

$tagDemux->setOnDataAvailable(function($audioTrack, $videoTrack) use (&$dataAvailableCalled) {
    echo "[CALLBACK] onDataAvailable\n";
    $dataAvailableCalled = true;
});

$tagDemux->setHasAudio($parser->_hasAudio);
$tagDemux->setHasVideo($parser->_hasVideo);

echo "\nCalling moofTag with all " . count($parser->arrTag) . " tags...\n";
$tagDemux->moofTag($parser->arrTag);

echo "\n\nResults:\n";
echo "--------\n";
echo "Track metadata received: " . implode(', ', $trackMetadataReceived) . "\n";
echo "Media info received: " . ($mediaInfoReceived ? 'Yes' : 'No') . "\n";
echo "Data available called: " . ($dataAvailableCalled ? 'Yes' : 'No') . "\n";
?>