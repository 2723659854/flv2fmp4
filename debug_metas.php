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
    
    // Search for avc1 box and check width/height
    for ($i = 0; $i < count($data) - 8; $i++) {
        if ($data[$i+4] == 0x61 && $data[$i+5] == 0x76 && $data[$i+6] == 0x63 && $data[$i+7] == 0x31) {
            $width = ($data[$i + 28] << 8) | $data[$i + 29];
            $height = ($data[$i + 30] << 8) | $data[$i + 31];
            echo "avc1 width: $width, height: $height\n";
            break;
        }
    }
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
};

echo "Starting conversion...\n";
$offset = $flv2fmp4->setflv($flvArray);
echo "Done! offset=$offset\n";

// Check metas
echo "\nChecking metas array:\n";
foreach ($flv2fmp4->metas as $i => $meta) {
    echo "Meta $i:\n";
    echo "  type: " . ($meta['type'] ?? 'unknown') . "\n";
    if (isset($meta['codecWidth'])) {
        echo "  codecWidth: {$meta['codecWidth']}\n";
        echo "  codecHeight: {$meta['codecHeight']}\n";
    }
    if (isset($meta['sampleRate'])) {
        echo "  sampleRate: {$meta['sampleRate']}\n";
    }
}
?>