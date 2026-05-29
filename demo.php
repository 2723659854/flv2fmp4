<?php
/**
 * FLV 转 fMP4 测试脚本（CLI 修正版）
 */
ini_set('memory_limit', '512M');

// 自动加载（根据你的项目结构调整）
require_once __DIR__.'/xiaosongshu/flv/FlvTag.php';
require_once __DIR__.'/xiaosongshu/flv/FlvParse.php';
require_once __DIR__.'/xiaosongshu/flv/TagDemux.php';
require_once __DIR__.'/xiaosongshu/mp4/MP4.php';
require_once __DIR__.'/xiaosongshu/mp4/MP4Remuxer.php';
require_once __DIR__.'/xiaosongshu/Flv2Fmp4.php';   // 你的入口类

// 输入输出
$inputFile = __DIR__.'/test.flv';
$outputDir = __DIR__.'/output';

if (!file_exists($inputFile)) {
    die("错误: 输入文件不存在!\n");
}

// 清空输出目录
if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);
array_map('unlink', glob("$outputDir/*"));

echo "=== FLV 转 fMP4 测试 ===\n";
echo "输入文件: $inputFile\n";

// 读取 FLV 文件为二进制字符串（关键！不要转为 int 数组）
$flvBinary = file_get_contents($inputFile);
echo "FLV文件大小: " . strlen($flvBinary) . " bytes\n";

// 创建转换器
$flv2fmp4 = new xiaosongshu\Flv2Fmp4();   // 假设你的 Flv2Fmp4 是 foreign 类的别名或包装

$initSegment = null;
$segments = [];
$segmentIndex = 0;

// 初始化段回调（接收二进制字符串）
$flv2fmp4->onInitSegment = function($data) use (&$initSegment, $outputDir) {
    echo "\n[回调] 初始化段生成\n";
    echo "大小: " . strlen($data) . " bytes\n";
    $initSegment = $data;
    file_put_contents("$outputDir/init.mp4", $data);
    echo "已写入: $outputDir/init.mp4\n";
};

// 媒体段回调（接收二进制字符串）
$flv2fmp4->onMediaSegment = function($data) use (&$segments, &$segmentIndex, $outputDir) {
    $segmentIndex++;
    echo "\n[回调] 媒体段#$segmentIndex\n";
    echo "大小: " . strlen($data) . " bytes\n";
    $segments[] = $data;
    file_put_contents("$outputDir/segment_$segmentIndex.m4s", $data);
    echo "已写入: $outputDir/segment_$segmentIndex.m4s\n";
};

// 媒体信息回调
$flv2fmp4->onMediaInfo = function($mediaInfo, $tracks) {
    echo "\n[回调] 媒体信息:\n";
    echo "  宽度: " . ($mediaInfo['width'] ?? 'N/A') . "\n";
    echo "  高度: " . ($mediaInfo['height'] ?? 'N/A') . "\n";
    echo "  帧率: " . ($mediaInfo['fps'] ?? 'N/A') . "\n";
    echo "  时长: " . ($mediaInfo['duration'] ?? 0) . "\n";
    echo "  音频: " . ($tracks['hasAudio'] ? '是' : '否') . "\n";
    echo "  视频: " . ($tracks['hasVideo'] ? '是' : '否') . "\n";
};

// 开始转换
echo "\n=== 开始转换 ===\n";
$startTime = microtime(true);

try {
    // 直接传递二进制字符串，而不是数组
    $flv2fmp4->setflv($flvBinary);
} catch (Exception $e) {
    echo "转换错误: " . $e->getMessage() . "\n";
    exit(1);
}

$endTime = microtime(true);

// 合并成完整 MP4 文件（可选）
echo "\n=== 合并文件 ===\n";
if ($initSegment && !empty($segments)) {
    $fullBinary = $initSegment . implode('', $segments);
    file_put_contents("$outputDir/output.mp4", $fullBinary);
    echo "完整MP4文件大小: " . strlen($fullBinary) . " bytes\n";
    echo "已写入: $outputDir/output.mp4\n";
}

echo "\n=== 转换完成 ===\n";
echo "耗时: " . number_format(($endTime - $startTime) * 1000, 2) . " ms\n";
echo "生成文件数: " . (1 + count($segments)) . "\n";

// 可选：用 ffmpeg 验证
$ffmpegPath = 'D:\\ffmpeg\\bin\\ffmpeg.exe';
if (file_exists($ffmpegPath)) {
    echo "\n=== 使用 FFmpeg 验证 ===\n";
    $cmd = escapeshellcmd("$ffmpegPath -v error -i \"$outputDir/output.mp4\" -f null - 2>&1");
    $output = shell_exec($cmd);
    if (trim($output)) {
        echo "FFmpeg 错误:\n$output\n";
    } else {
        echo "FFmpeg 验证通过!\n";
    }
}