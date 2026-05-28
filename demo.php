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

// 创建output目录
if (!is_dir(__DIR__.'/output')) {
    mkdir(__DIR__.'/output', 0777, true);
}

// 收集所有数据
$initData = null;
$segmentCount = 0;
$allSegments = [];

// 设置初始化段回调（生成 MP4 的 ftyp 和 moov 盒）
$flv2fmp4->onInitSegment = function ($data) use (&$initData) {
    echo "onInitSegment called! data length: " . count($data) . " bytes\r\n";
    $initData = $data;
    file_put_contents(__DIR__.'/output/init.mp4', pack('C*', ...$data));
    echo "init.mp4 saved\r\n";
};

// 设置媒体段回调（生成 m4s 媒体片段）
$flv2fmp4->onMediaSegment = function ($type, $data) use (&$segmentCount, &$allSegments) {
    $segmentCount++;
    echo "onMediaSegment called! segment #$segmentCount ($type), data length: " . count($data['data']) . " bytes\r\n";
    $allSegments[] = $data['data'];
    file_put_contents(__DIR__.'/output/segment_' . $segmentCount . '.m4s', pack('C*', ...$data['data']));
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

echo "Loading FLV file...\r\n";
$flvData = file_get_contents($flv);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);
echo "FLV file loaded, size: " . count($flvArray) . " bytes\r\n";

echo "Calling setflv()...\r\n";
$offset = $flv2fmp4->setflv($flvArray);
echo "setflv() completed, offset: $offset\r\n";
echo "Total segments created: $segmentCount\r\n";

// 合并生成完整的 MP4 文件
if ($initData !== null && count($allSegments) > 0) {
    $outputFile = __DIR__.'/output/output.mp4';

    // 将所有数据合并
    $allData = array_merge($initData, ...$allSegments);

    file_put_contents($outputFile, pack('C*', ...$allData));
    echo "MP4 file saved: $outputFile (" . filesize($outputFile) . " bytes)\r\n";
} else {
    echo "Warning: No data to save\r\n";
}
