<?php

ini_set('memory_limit', '512M');

require_once 'php/flv/FlvParse.php';

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
        $codecId = $spec & 15;

        if ($codecId == 7 && count($tag->body) > 1) {
            $packetType = $tag->body[1];
            if ($packetType == 0) {
                echo "Found AVCDecoderConfigurationRecord at tag $i\n";
                echo "Body size: " . count($tag->body) . "\n";

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

                    // Extract SPS data (without length prefix)
                    $spsData = array_slice($tag->body, $offset + 2, $spsLen);
                    echo "SPS first 20 bytes: " . implode(', ', array_slice($spsData, 0, 20)) . "\n";

                    // Parse SPS
                    require_once 'php/flv/SPSParser.php';
                    $config = SPSParser::parseSPS($spsData);
                    echo "SPS config:\n";
                    echo "  codec_width: " . $config['codec_size']['width'] . "\n";
                    echo "  codec_height: " . $config['codec_size']['height'] . "\n";
                    echo "  present_width: " . $config['present_size']['width'] . "\n";
                    echo "  present_height: " . $config['present_size']['height'] . "\n";
                }
                break;
            }
        }
    }
}
?>