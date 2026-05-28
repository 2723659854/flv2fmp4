<?php

require_once 'php/flv/FlvParse.php';

$flv = __DIR__.'/test.flv';
$flvData = file_get_contents($flv);
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

echo "FLV 文件格式分析\n";
echo "=================\n\n";

$parser = new FlvParse();
$parser->setFlv($flvArray);

echo "FLV 文件大小: " . count($flvArray) . " bytes\n";
echo "标签数量: " . count($parser->arrTag) . "\n";
echo "包含音频: " . ($parser->_hasAudio ? '是' : '否') . "\n";
echo "包含视频: " . ($parser->_hasVideo ? '是' : '否') . "\n\n";

// 分析前几个标签的格式
for ($i = 0; $i < min(5, count($parser->arrTag)); $i++) {
    $tag = $parser->arrTag[$i];
    echo "标签 " . ($i + 1) . ":\n";

    switch ($tag->tagType) {
        case 8:
            echo "  类型: 音频\n";
            if (count($tag->body) > 0) {
                $soundSpec = $tag->body[0];
                $soundFormat = $soundSpec >> 4;
                $soundRateIndex = ($soundSpec & 12) >> 2;
                $soundType = $soundSpec & 1;

                $formatName = match($soundFormat) {
                    0 => 'Linear PCM, platform endian',
                    1 => 'ADPCM',
                    2 => 'MP3',
                    3 => 'Linear PCM, little endian',
                    4 => 'Nellymoser 16-kHz mono',
                    5 => 'Nellymoser 8-kHz mono',
                    6 => 'Nellymoser',
                    7 => 'AAC',
                    8 => 'Speex',
                    9 => 'MP3 8-kHz',
                    10 => 'AAC',
                    13 => 'MP3 48-kHz',
                    14 => 'MP3 24-kHz',
                    15 => 'AAC',
                    default => '未知'
                };

                $rateName = match($soundRateIndex) {
                    0 => '5.5 kHz',
                    1 => '11 kHz',
                    2 => '22 kHz',
                    3 => '44 kHz',
                    4 => '48 kHz',
                    default => '未知'
                };

                echo "  音频格式: $soundFormat ($formatName)\n";
                echo "  采样率: $soundRateIndex ($rateName)\n";
                echo "  声道: " . ($soundType == 0 ? '单声道' : '立体声') . "\n";

                if ($soundFormat !== 7 && $soundFormat !== 10) {
                    echo "  ⚠️ 不支持: 当前只支持 AAC 格式 (soundFormat=7 或 10)\n";
                }
            }
            break;

        case 9:
            echo "  类型: 视频\n";
            if (count($tag->body) > 0) {
                $spec = $tag->body[0];
                $frameType = ($spec & 240) >> 4;
                $codecId = $spec & 15;

                $frameName = match($frameType) {
                    1 => '关键帧 (I-frame)',
                    2 => '非关键帧 (P/B-frame)',
                    3 => '独占非关键帧',
                    4 => '生成帧',
                    5 => '视频信息/命令帧',
                    default => '未知'
                };

                $codecName = match($codecId) {
                    1 => 'H.263',
                    2 => 'H.263',
                    3 => '屏幕视频',
                    4 => 'VP6',
                    5 => 'VP6',
                    6 => '屏幕视频',
                    7 => 'H.264/AVC',
                    12 => 'H.265',
                    default => '未知'
                };

                echo "  帧类型: $frameType ($frameName)\n";
                echo "  视频编码: $codecId ($codecName)\n";

                if ($codecId !== 7) {
                    echo "  ⚠️ 不支持: 当前只支持 H.264/AVC 格式 (codecId=7)\n";
                }
            }
            break;

        case 18:
            echo "  类型: 元数据\n";
            break;
    }
    echo "\n";
}

echo "=================\n";
echo "结论: 您的 FLV 文件使用了不支持的编码格式。\n";
echo "当前 PHP 工具只支持:\n";
echo "  - 音频: AAC 格式\n";
echo "  - 视频: H.264/AVC 格式\n\n";
echo "解决方案: 使用 FFmpeg 将文件转换为支持的格式:\n";
echo "ffmpeg -i test.flv -c:a aac -c:v libx264 -profile:v baseline output.flv\n";
?>