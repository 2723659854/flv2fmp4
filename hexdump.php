<?php
$file = 'output/output.mp4';
$handle = fopen($file, 'rb');
$bytes = fread($handle, 2000);
fclose($handle);

echo "=== 文件大小: " . filesize($file) . " bytes ===\n";

// 搜索关键box位置
echo "\n=== 搜索关键box位置 ===\n";
$pos = strpos($bytes, 'ftyp');
if ($pos !== false) {
    echo "ftyp 位于偏移量: 0x" . dechex($pos) . "\n";
}

$pos = strpos($bytes, 'moov');
if ($pos !== false) {
    echo "moov 位于偏移量: 0x" . dechex($pos) . "\n";
}

$pos = strpos($bytes, 'moof');
if ($pos !== false) {
    echo "moof 位于偏移量: 0x" . dechex($pos) . "\n";
} else {
    echo "未找到 moof\n";
}

$pos = strpos($bytes, 'mdat');
if ($pos !== false) {
    echo "mdat 位于偏移量: 0x" . dechex($pos) . "\n";
} else {
    echo "未找到 mdat\n";
}

// 查看 moov 后面的内容
$pos = strpos($bytes, 'moov');
if ($pos !== false) {
    $moovSize = (ord($bytes[$pos-4]) << 24) | (ord($bytes[$pos-3]) << 16) | (ord($bytes[$pos-2]) << 8) | ord($bytes[$pos-1]);
    echo "\nmoov box 大小: $moovSize bytes\n";
    echo "moov 结束位置: 0x" . dechex($pos + $moovSize) . "\n";
    
    // 查看 moov 结束后的内容
    $afterMoov = $pos + $moovSize;
    if ($afterMoov < strlen($bytes)) {
        echo "\nmoov 结束后的内容:\n";
        printf("偏移量 0x%08X: ", $afterMoov);
        for ($i = $afterMoov; $i < $afterMoov + 16 && $i < strlen($bytes); $i++) {
            printf("%02X ", ord($bytes[$i]));
        }
        echo "\n";
        printf("字符: ");
        for ($i = $afterMoov; $i < $afterMoov + 16 && $i < strlen($bytes); $i++) {
            $c = ord($bytes[$i]);
            echo ($c >= 32 && $c <= 126) ? chr($c) : '.';
        }
        echo "\n";
    }
}
?>