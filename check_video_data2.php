<?php

ini_set('memory_limit', '512M');

require_once 'php/flv/FlvParse.php';
require_once 'php/flv/TagDemux.php';

$flv = __DIR__.'/test.flv';
$flvData = file_get_contents($flv);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

$parser = new FlvParse();
$parser->setFlv($flvArray);

echo "Finding video data tags...\n";

$videoTagCount = 0;
for ($i = 0; $i < count($parser->arrTag); $i++) {
    $tag = $parser->arrTag[$i];
    if ($tag->tagType == 9 && count($tag->body) > 1) {
        $spec = $tag->body[0];
        $frameType = ($spec & 240) >> 4;
        $codecId = $spec & 15;

        if ($codecId == 7 && count($tag->body) > 1) {
            $packetType = $tag->body[1];
            $videoTagCount++;

            if ($packetType == 1 && $videoTagCount <= 3) {
                echo "Video data tag #$videoTagCount at tag $i, packetType=$packetType, frameType=$frameType\n";
                echo "Body size: " . count($tag->body) . "\n";
                echo "First 20 bytes: " . implode(', ', array_slice($tag->body, 0, 20)) . "\n";

                // FLV structure: [spec, packetType, cts(3 bytes), NALU data...]
                // NALU data starts at index 5
                $naluLen = ($tag->body[5] << 24) | ($tag->body[6] << 16) | ($tag->body[7] << 8) | $tag->body[8];
                echo "NALU length (from index 5): $naluLen\n";
                echo "NALU type byte: " . $tag->body[9] . " (0x" . sprintf('%02X', $tag->body[9]) . ")\n";
                echo "\n";
            }
        }
    }
}
?>