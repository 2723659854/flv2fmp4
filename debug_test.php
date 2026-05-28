<?php

// Increase memory limit
ini_set('memory_limit', '512M');

require_once 'php/Flv2Fmp4.php';

echo "FLV to MP4 Converter - Debug Test\n";
echo "=================================\n\n";

// Load FLV file
$flvData = file_get_contents('test.flv');
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

echo "FLV file size: " . count($flvArray) . " bytes\n\n";

// First, let's parse the FLV manually to see what we're working with
$parser = new FlvParse();
$offset = $parser->setFlv($flvArray);

echo "FLV Parser Results:\n";
echo "-------------------\n";
echo "Has Audio: " . ($parser->_hasAudio ? 'Yes' : 'No') . "\n";
echo "Has Video: " . ($parser->_hasVideo ? 'Yes' : 'No') . "\n";
echo "Tags found: " . count($parser->arrTag) . "\n";

if (count($parser->arrTag) > 0) {
    echo "\nFirst few tags:\n";
    for ($i = 0; $i < min(5, count($parser->arrTag)); $i++) {
        $tag = $parser->arrTag[$i];
        $type = ['', 'Audio', 'Video', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Metadata'][$tag->tagType];
        if (!$type) $type = 'Unknown';
        echo "Tag " . ($i + 1) . ": Type=" . $type . " (code=" . $tag->tagType . "), Time=" . $tag->getTime() . ", Size=" . count($tag->body) . "\n";
    }
}

echo "\n\nNow testing full conversion with callbacks:\n";
echo "-------------------------------------------\n";

$flv2fmp4 = new Flv2Fmp4();

$initSegmentGenerated = false;
$mediaSegmentsGenerated = 0;
$mediaInfoReceived = false;

$flv2fmp4->onInitSegment = function($data) use (&$initSegmentGenerated) {
    $initSegmentGenerated = true;
    echo "✓ onInitSegment called! Size: " . count($data) . " bytes\n";
    file_put_contents('output/init.mp4', pack('C*', ...$data));
};

$flv2fmp4->onMediaSegment = function($data) use (&$mediaSegmentsGenerated) {
    $mediaSegmentsGenerated++;
    echo "✓ onMediaSegment #" . $mediaSegmentsGenerated . " called! Size: " . count($data) . " bytes\n";
    file_put_contents('output/segment_' . $mediaSegmentsGenerated . '.m4s', pack('C*', ...$data));
};

$flv2fmp4->onMediaInfo = function($mediaInfo, $tracks) use (&$mediaInfoReceived) {
    $mediaInfoReceived = true;
    echo "\n✓ onMediaInfo called!\n";
    echo "  - Has Audio: " . ($tracks['hasAudio'] ? 'Yes' : 'No') . "\n";
    echo "  - Has Video: " . ($tracks['hasVideo'] ? 'Yes' : 'No') . "\n";
    if ($mediaInfo) {
        echo "  - Width: " . $mediaInfo->width . "\n";
        echo "  - Height: " . $mediaInfo->height . "\n";
        echo "  - FPS: " . $mediaInfo->fps . "\n";
        echo "  - Duration: " . $mediaInfo->duration . "\n";
        echo "  - MIME Type: " . $mediaInfo->mimeType . "\n";
    }
};

echo "\nProcessing FLV...\n";
$offset = $flv2fmp4->setflv($flvArray);

echo "\nProcessing complete! Offset: " . $offset . "\n";

echo "\nSummary:\n";
echo "--------\n";
echo "Init Segment Generated: " . ($initSegmentGenerated ? 'Yes' : 'No') . "\n";
echo "Media Segments Generated: " . $mediaSegmentsGenerated . "\n";
echo "Media Info Received: " . ($mediaInfoReceived ? 'Yes' : 'No') . "\n";

// Check if output directory has files
if (file_exists('output')) {
    $files = scandir('output');
    $files = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
    if (count($files) > 0) {
        echo "\nOutput files:\n";
        foreach ($files as $file) {
            $size = filesize('output/' . $file);
            echo "  - " . $file . ": " . $size . " bytes\n";
        }
    }
}
?>