<?php
/**
 * FLV 转 fMP4 测试脚本
 */
ini_set('memory_limit', '512M');
require_once 'php/flv/FlvTag.php';
require_once 'php/flv/FlvParse.php';
require_once 'php/flv/TagDemux.php';
require_once 'php/mp4/MP4.php';
require_once 'php/mp4/MP4Moof.php';
require_once 'php/Flv2Fmp4.php';

// 输入FLV文件
$inputFile = 'test.flv';
// 输出目录
$outputDir = 'output';

// 确保输出目录存在
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// 清空输出目录
$files = glob($outputDir . '/*');
foreach ($files as $file) {
    unlink($file);
}

echo "=== FLV 转 fMP4 测试 ===\n";
echo "输入文件: $inputFile\n";

if (!file_exists($inputFile)) {
    die("错误: 输入文件不存在!\n");
}

// 读取FLV文件
$flvData = file_get_contents($inputFile);
$flvArray = [];
for ($i = 0; $i < strlen($flvData); $i++) {
    $flvArray[] = ord($flvData[$i]);
}

echo "FLV文件大小: " . count($flvArray) . " bytes\n";

// 创建转换器
$flv2fmp4 = new Flv2Fmp4();

$initSegment = null;
$segments = [];
$segmentIndex = 0;

// 初始化段回调
$flv2fmp4->onInitSegment = function($data) use (&$initSegment) {
    echo "\n[回调] 初始化段生成\n";
    echo "大小: " . count($data) . " bytes\n";
    $initSegment = $data;
    
    // 写入init.mp4
    $file = fopen('output/init.mp4', 'wb');
    foreach ($data as $byte) {
        fwrite($file, chr($byte));
    }
    fclose($file);
    echo "已写入: output/init.mp4\n";
};

// 媒体段回调
$flv2fmp4->onMediaSegment = function($data) use (&$segments, &$segmentIndex) {
    $segmentIndex++;
    echo "\n[回调] 媒体段#$segmentIndex\n";
    echo "大小: " . count($data) . " bytes\n";
    $segments[] = $data;
    
    // 写入segment文件
    $file = fopen("output/segment_$segmentIndex.m4s", 'wb');
    foreach ($data as $byte) {
        fwrite($file, chr($byte));
    }
    fclose($file);
    echo "已写入: output/segment_$segmentIndex.m4s\n";
};

// 媒体信息回调
$flv2fmp4->onMediaInfo = function($mediaInfo, $tracks) {
    echo "\n[回调] 媒体信息:\n";
    echo "  宽度: " . $mediaInfo['width'] . "\n";
    echo "  高度: " . $mediaInfo['height'] . "\n";
    echo "  帧率: " . $mediaInfo['fps'] . "\n";
    echo "  时长: " . $mediaInfo['duration'] . "\n";
    echo "  音频: " . ($tracks['hasAudio'] ? '是' : '否') . "\n";
    echo "  视频: " . ($tracks['hasVideo'] ? '是' : '否') . "\n";
};

// 开始转换
echo "\n=== 开始转换 ===\n";
$startTime = microtime(true);

try {
    $flv2fmp4->setflv($flvArray);
} catch (Exception $e) {
    echo "转换错误: " . $e->getMessage() . "\n";
}

$endTime = microtime(true);

// 合并成完整的MP4文件（用于测试）
echo "\n=== 合并文件 ===\n";
if ($initSegment && count($segments) > 0) {
    $fullData = [];
    foreach ($initSegment as $byte) {
        $fullData[] = $byte;
    }
    foreach ($segments as $segment) {
        foreach ($segment as $byte) {
            $fullData[] = $byte;
        }
    }
    
    $file = fopen('output/output.mp4', 'wb');
    foreach ($fullData as $byte) {
        fwrite($file, chr($byte));
    }
    fclose($file);
    
    echo "完整MP4文件大小: " . count($fullData) . " bytes\n";
    echo "已写入: output/output.mp4\n";
}

echo "\n=== 转换完成 ===\n";
echo "耗时: " . number_format(($endTime - $startTime) * 1000, 2) . " ms\n";
echo "生成文件数: " . (1 + count($segments)) . "\n";

// 使用ffmpeg验证
echo "\n=== 使用FFmpeg验证 ===\n";
$ffmpegPath = 'D:\\ffmpeg\\bin\\ffmpeg.exe';
if (file_exists($ffmpegPath)) {
    $cmd = "\"$ffmpegPath\" -v error -i output/output.mp4 -f null - 2>&1";
    $output = shell_exec($cmd);
    if ($output) {
        echo "FFmpeg错误:\n$output\n";
    } else {
        echo "FFmpeg验证通过!\n";
    }
} else {
    echo "FFmpeg未找到，跳过验证\n";
}
