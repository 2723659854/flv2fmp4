<?php

$initFile = __DIR__.'/output/init.mp4';
$data = file_get_contents($initFile);
$bytes = unpack('C*', $data);
$array = array_values($bytes);

echo "Full init.mp4 hex dump:\n";
$hex = '';
$ascii = '';
for ($i = 0; $i < count($array); $i++) {
    $hex .= sprintf('%02X ', $array[$i]);
    $ascii .= ($array[$i] >= 32 && $array[$i] <= 126) ? chr($array[$i]) : '.';
    
    if ((($i + 1) % 16) == 0) {
        printf("%04X: %s | %s\n", $i - 15, $hex, $ascii);
        $hex = '';
        $ascii = '';
    }
}
if ($hex) {
    printf("%04X: %s | %s\n", count($array) - strlen($hex) / 3, $hex, $ascii);
}

// Parse all boxes
echo "\n\nBox structure:\n";
$offset = 0;
while ($offset < count($array) - 8) {
    $size = ($array[$offset] << 24) | ($array[$offset+1] << 16) | ($array[$offset+2] << 8) | $array[$offset+3];
    $type = chr($array[$offset+4]) . chr($array[$offset+5]) . chr($array[$offset+6]) . chr($array[$offset+7]);
    
    echo "Offset: $offset (0x" . dechex($offset) . "), Size: $size, Type: $type\n";
    
    $offset += $size;
}
?>