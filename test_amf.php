<?php

require_once 'php/utils/decodeUTF8.php';
require_once 'php/flv/FlvDemux.php';

echo "Testing AMF Parsing\n";
echo "==================\n\n";

// Create FlvDemux instance to initialize self::$le
$demux = new FlvDemux();

// Load FLV file
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
        
        // Show tag header
        echo "Tag header bytes:\n";
        for ($i = 0; $i < 11; $i++) {
            echo sprintf("  [%d] = 0x%02X (%d)\n", $i, $flvArray[$offset + $i], $flvArray[$offset + $i]);
        }
        
        $dataSize = ($flvArray[$offset + 1] << 16) | ($flvArray[$offset + 2] << 8) | $flvArray[$offset + 3];
        echo "Data size: $dataSize bytes\n";
        
        // Extract just the body
        $bodyOffset = $offset + 11; // Skip tag header
        $body = array_slice($flvArray, $bodyOffset, $dataSize);
        
        echo "\nMetadata body (first 30 bytes):\n";
        for ($i = 0; $i < min(30, count($body)); $i++) {
            echo sprintf("  [%d] = 0x%02X (%d) '%c'\n", $i, $body[$i], $body[$i], $body[$i] >= 32 && $body[$i] <= 126 ? chr($body[$i]) : '.');
        }
        
        // Parse the first script element
        echo "\nParsing first script element:\n";
        $firstElement = FlvDemux::parseScript($body, 0);
        echo "Result: " . ($firstElement['data'] ?? 'null') . "\n";
        echo "Size: " . ($firstElement['size'] ?? 'null') . "\n";
        
        // Let's manually parse the first string
        echo "\nManual parsing:\n";
        $type = $body[0];
        echo "Type: $type (2 = string, 3 = object)\n";
        
        if ($type == 2) {
            $length = ($body[1] << 8) | $body[2];
            echo "String length: $length\n";
            
            $strBytes = array_slice($body, 3, $length);
            $str = '';
            foreach ($strBytes as $byte) {
                $str .= chr($byte);
            }
            echo "String value: '$str'\n";
        }
        
        break;
    }
    
    // Skip to next tag
    $dataSize = ($flvArray[$offset + 1] << 16) | ($flvArray[$offset + 2] << 8) | $flvArray[$offset + 3];
    $offset += 11 + $dataSize + 4;
}
?>