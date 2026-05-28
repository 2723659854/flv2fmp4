<?php

ini_set('memory_limit', '512M');

require_once 'php/Flv2Fmp4.php';

$flv = __DIR__.'/test.flv';
$flvData = file_get_contents($flv);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

$flv2fmp4 = new Flv2Fmp4();

$flv2fmp4->onInitSegment = function ($data) {
    echo "onInitSegment: " . count($data) . " bytes\n";
};

$flv2fmp4->onMediaSegment = function ($data) {
    static $count = 0;
    $count++;
    echo "onMediaSegment #$count: " . count($data) . " bytes\n";
};

$flv2fmp4->onMediaInfo = function ($mi, $t) {
    echo "onMediaInfo:\n";
    echo "  width: {$mi->width}\n";
    echo "  height: {$mi->height}\n";
    echo "  fps: {$mi->fps}\n";
    echo "  profile: {$mi->profile}\n";
    echo "  level: {$mi->level}\n";
};

echo "Starting conversion...\n";
$offset = $flv2fmp4->setflv($flvArray);
echo "Done! offset=$offset\n";
?>