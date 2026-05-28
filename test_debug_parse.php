<?php

require_once 'php/utils/decodeUTF8.php';
require_once 'php/flv/FlvDemux.php';

// Create FlvDemux instance to initialize self::$le
$demux = new FlvDemux();

echo "Debugging parseString and parseScript\n";
echo "====================================\n\n";

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
        
        $dataSize = ($flvArray[$offset + 1] << 16) | ($flvArray[$offset + 2] << 8) | $flvArray[$offset + 3];
        $bodyOffset = $offset + 11;
        $body = array_slice($flvArray, $bodyOffset, $dataSize);
        
        echo "\nMetadata body size: " . count($body) . " bytes\n";
        
        // Debug: parseString directly
        echo "\n1. Testing parseString directly:\n";
        $strResult = FlvDemux::parseString($body, 1); // Start at offset 1 (after type byte)
        echo "   parseString result: '" . ($strResult['data'] ?? 'null') . "'\n";
        echo "   parseString size: " . ($strResult['size'] ?? 'null') . "\n";
        
        // Debug: parseScript
        echo "\n2. Testing parseScript:\n";
        $scriptResult = FlvDemux::parseScript($body, 0);
        echo "   parseScript result: '" . ($scriptResult['data'] ?? 'null') . "'\n";
        echo "   parseScript size: " . ($scriptResult['size'] ?? 'null') . "\n";
        
        // Debug: manually parse
        echo "\n3. Manual parsing:\n";
        $type = $body[0];
        echo "   Type: 0x" . sprintf('%02X', $type) . "\n";
        
        $length = ($body[1] << 8) | $body[2];
        echo "   String length: $length\n";
        
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= chr($body[3 + $i]);
        }
        echo "   String: '$str'\n";
        echo "   Expected total size: " . (1 + 2 + $length) . "\n";
        
        break;
    }
    
    $dataSize = ($flvArray[$offset + 1] << 16) | ($flvArray[$offset + 2] << 8) | $flvArray[$offset + 3];
    $offset += 11 + $dataSize + 4;
}
?>