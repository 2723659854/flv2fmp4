<?php

ini_set('memory_limit', '512M');

require_once 'php/Flv2Fmp4.php';

$flv = __DIR__.'/test.flv';
$flvData = file_get_contents($flv);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

$flv2fmp4 = new Flv2Fmp4();

$videoSamples = [];

$flv2fmp4->onInitSegment = function ($data) {
    echo "onInitSegment: " . count($data) . " bytes\n";
};

$flv2fmp4->onMediaSegment = function ($data) use (&$videoSamples) {
    static $count = 0;
    $count++;
    echo "onMediaSegment #$count: " . count($data) . " bytes\n";

    // Parse segment to find mdat
    for ($i = 0; $i < count($data) - 4; $i++) {
        if ($data[$i] == 0x6D && $data[$i+1] == 0x64 && $data[$i+2] == 0x61 && $data[$i+3] == 0x74) {
            $size = ($data[$i-4] << 24) | ($data[$i-3] << 16) | ($data[$i-2] << 8) | $data[$i-1];
            echo "  mdat size: $size\n";
            echo "  First 20 bytes: " . implode(', ', array_slice($data, $i + 8, 20)) . "\n";
            break;
        }
    }
};

$flv2fmp4->onMediaInfo = function ($mi, $t) {
    echo "onMediaInfo\n";
};

echo "Starting conversion...\n";
$offset = $flv2fmp4->setflv($flvArray);
echo "Done! offset=$offset\n";
?>