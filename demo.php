<?php

ini_set('memory_limit', '512M');

require_once 'php/Flv2Fmp4.php';

$flv2fmp4 = new Flv2Fmp4();

$flv = __DIR__.'/test.flv';
if (file_exists($flv)) {
    echo "flv文件存在\r\n";
}else{
    echo "flv文件不存在\r\n";
    exit;
}

$segmentCount = 0;
$audioCount = 0;
$videoCount = 0;

// 设置初始化段回调（生成 MP4 的 ftyp 和 moov 盒）
$flv2fmp4->onInitSegment = function ($data) {
    echo "onInitSegment called! data length: " . count($data) . " bytes\r\n";
    file_put_contents(__DIR__.'/output/init.mp4', pack('C*', ...$data));
    echo "init.mp4 saved\r\n";
};

// 设置媒体段回调（生成 m4s 媒体片段）
$flv2fmp4->onMediaSegment = function ($data) use (&$segmentCount) {
    $segmentCount++;
    echo "onMediaSegment called! segment #$segmentCount, data length: " . count($data) . " bytes\r\n";
    file_put_contents(__DIR__.'/output/segment_' . $segmentCount . '.m4s', pack('C*', ...$data));
};

// 设置媒体信息回调
$flv2fmp4->onMediaInfo = function ($mediaInfo, $tracks) {
    echo "onMediaInfo called!\r\n";
    if ($mediaInfo) {
        echo "  Width: {$mediaInfo->width}\r\n";
        echo "  Height: {$mediaInfo->height}\r\n";
        echo "  Duration: {$mediaInfo->duration}\r\n";
        echo "  MIME: {$mediaInfo->mimeType}\r\n";
    }
    echo "  Tracks: " . ($tracks['hasAudio'] ? 'hasAudio ' : '') . ($tracks['hasVideo'] ? 'hasVideo' : '') . "\r\n";
};

// 创建output目录
if (!is_dir(__DIR__.'/output')) {
    mkdir(__DIR__.'/output', 0777, true);
}

echo "Loading FLV file...\r\n";
$flvData = file_get_contents($flv);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);
echo "FLV file loaded, size: " . count($flvArray) . " bytes\r\n";

echo "Calling setflv()...\r\n";
$offset = $flv2fmp4->setflv($flvArray);
echo "setflv() completed, offset: $offset\r\n";
echo "Total segments created: $segmentCount\r\n";
