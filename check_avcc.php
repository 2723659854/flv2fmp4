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

echo "Finding AVCDecoderConfigurationRecord...\n";

for ($i = 0; $i < count($parser->arrTag); $i++) {
    $tag = $parser->arrTag[$i];
    if ($tag->tagType == 9 && count($tag->body) > 1) {
        $spec = $tag->body[0];
        $frameType = ($spec & 240) >> 4;
        $codecId = $spec & 15;

        if ($codecId == 7 && count($tag->body) > 1) {
            $packetType = $tag->body[1];
            if ($packetType == 0) {
                echo "Found AVCDecoderConfigurationRecord at tag $i\n";
                echo "Body size: " . count($tag->body) . "\n";
                echo "First 20 bytes: " . implode(', ', array_slice($tag->body, 0, 20)) . "\n";

                // Parse structure
                $version = $tag->body[5];
                $avcProfile = $tag->body[6];
                $profileCompatibility = $tag->body[7];
                $avcLevel = $tag->body[8];
                $naluLengthSize = ($tag->body[9] & 3) + 1;
                $spsCount = $tag->body[10] & 31;

                echo "version: $version\n";
                echo "avcProfile: $avcProfile\n";
                echo "profileCompatibility: $profileCompatibility\n";
                echo "avcLevel: $avcLevel\n";
                echo "naluLengthSize: $naluLengthSize\n";
                echo "spsCount: $spsCount\n";

                if ($spsCount > 0) {
                    $offset = 11;
                    $spsLen = ($tag->body[$offset] << 8) | $tag->body[$offset + 1];
                    echo "SPS length: $spsLen\n";
                    echo "SPS first 10 bytes: " . implode(', ', array_slice($tag->body, $offset + 2, 10)) . "\n";
                }
                break;
            }
        }
    }
}
?>