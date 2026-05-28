<?php
$file = 'output/init.mp4';
$handle = fopen($file, 'rb');
$header = fread($handle, 32);
fclose($handle);

echo "文件头 (十六进制):\n";
for ($i = 0; $i < strlen($header); $i++) {
    printf("%02X ", ord($header[$i]));
    if (($i + 1) % 8 == 0) echo "\n";
}
echo "\n\n文件头 (ASCII):\n";
echo substr($header, 0, 8) . "\n";
echo substr($header, 8, 4) . "\n";
?>