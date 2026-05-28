<?php

ini_set('memory_limit', '512M');

require_once 'php/flv/FlvParse.php';

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

            if ($packetType == 1 && $videoTagCount <= 2) {
                echo "Video data tag #$videoTagCount at tag $i, packetType=$packetType, frameType=$frameType\n";
                echo "Body size: " . count($tag->body) . "\n";

                // Parse all NALUs in this tag
                $offset = 5; // NALU data starts at index 5
                $naluCount = 0;

                while ($offset + 4 <= count($tag->body)) {
                    $naluLen = ($tag->body[$offset] << 24) | ($tag->body[$offset + 1] << 16) | ($tag->body[$offset + 2] << 8) | $tag->body[$offset + 3];
                    $naluType = $tag->body[$offset + 4] & 0x1F;

                    echo "  NALU #$naluCount: length=$naluLen, type=$naluType\n";

                    if ($naluLen > 0 && $offset + 4 + $naluLen <= count($tag->body)) {
                        $offset += 4 + $naluLen;
                        $naluCount++;
                    } else {
                        echo "  Invalid NALU length, stopping\n";
                        break;
                    }
                }
                echo "\n";
            }
        }
    }
}
?>