<?php

require_once 'php/utils/decodeUTF8.php';
require_once 'php/flv/FlvDemux.php';

echo "Testing String Parsing\n";
echo "=====================\n\n";

// Test simple string
$testStr = "onMetaData";
$bytes = [];
for ($i = 0; $i < strlen($testStr); $i++) {
    $bytes[] = ord($testStr[$i]);
}

echo "Original string: $testStr\n";
echo "Bytes: " . implode(', ', $bytes) . "\n";

// Add AMF string header (type 2 + uint16 length)
$amfStr = [2, 0, strlen($testStr)];
$amfStr = array_merge($amfStr, $bytes);

echo "AMF string bytes: " . implode(', ', $amfStr) . "\n";

// Parse it
$result = FlvDemux::parseScript($amfStr, 0);
echo "Parsed result: " . ($result['data'] ?? 'null') . "\n";

// Test decodeUTF8 directly
$decoded = decodeUTF8($bytes);
echo "Direct decodeUTF8 result: $decoded\n";

// Test with UTF-8 characters
$utf8Str = "测试中文";
$utf8Bytes = [];
for ($i = 0; $i < strlen($utf8Str); $i++) {
    $utf8Bytes[] = ord($utf8Str[$i]);
}
echo "\nUTF-8 test: $utf8Str\n";
echo "UTF-8 bytes: " . implode(', ', $utf8Bytes) . "\n";
$decodedUtf8 = decodeUTF8($utf8Bytes);
echo "Decoded UTF-8: $decodedUtf8\n";

// Test with actual FLV metadata
echo "\n\nTesting with FLV metadata...\n";
$flvData = file_get_contents('test.flv');
$flvBytes = unpack('C*', $flvData);
$flvArray = array_values($flvBytes);

// Find the first metadata tag
$offset = 9; // Skip FLV header
$offset += 4; // Skip previous tag size

while ($offset < count($flvArray)) {
    $tagType = $flvArray[$offset];
    if ($tagType == 18) { // Metadata
        echo "Found metadata tag at offset $offset\n";
        $dataSize = ($flvArray[$offset + 1] << 16) | ($flvArray[$offset + 2] << 8) | $flvArray[$offset + 3];
        echo "Metadata size: $dataSize\n";
        
        // Extract just the body
        $bodyOffset = $offset + 11; // Skip tag header
        $body = array_slice($flvArray, $bodyOffset, $dataSize);
        
        // Parse first string (should be "onMetaData")
        $firstStr = FlvDemux::parseScript($body, 0);
        echo "First string: " . ($firstStr['data'] ?? 'null') . "\n";
        echo "String size: " . $firstStr['size'] . "\n";
        
        // Try to parse the value after the first string
        $value = FlvDemux::parseScript($body, $firstStr['size']);
        echo "Value type: " . gettype($value['data']) . "\n";
        if (is_array($value['data'])) {
            echo "Value is array with " . count($value['data']) . " keys\n";
            $count = 0;
            foreach ($value['data'] as $key => $val) {
                if ($count >= 5) break;
                echo "  $key: " . (is_scalar($val) ? $val : gettype($val)) . "\n";
                $count++;
            }
        }
        
        break;
    }
    
    // Skip to next tag
    $dataSize = ($flvArray[$offset + 1] << 16) | ($flvArray[$offset + 2] << 8) | $flvArray[$offset + 3];
    $offset += 11 + $dataSize + 4;
}
?>