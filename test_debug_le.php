<?php

require_once 'php/utils/decodeUTF8.php';
require_once 'php/flv/FlvDemux.php';

echo "Testing self::\$le value\n";
echo "=======================\n\n";

// First, create an instance to initialize self::$le
$demux = new FlvDemux();

// Check self::$le value using reflection
$reflection = new ReflectionClass('FlvDemux');
$property = $reflection->getProperty('le');
$property->setAccessible(true);
$leValue = $property->getValue();

echo "self::\$le value: " . ($leValue ? 'true' : 'false') . " (" . var_export($leValue, true) . ")\n";
echo "!self::\$le value: " . (!$leValue ? 'true' : 'false') . "\n";

// Test readUint16 with both endianness
$testBytes = [0x00, 0x0A]; // 10 in big-endian
echo "\nTesting readUint16:\n";
echo "Bytes: [0x00, 0x0A]\n";
echo "Big-endian (!self::\$le = " . (!$leValue) . "): " . FlvDemux::readUint16($testBytes, 0, !$leValue) . "\n";
echo "Little-endian (self::\$le = " . ($leValue) . "): " . FlvDemux::readUint16($testBytes, 0, $leValue) . "\n";

// Test with actual FLV metadata
echo "\n\nTesting with FLV metadata:\n";
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
        
        // Debug the first few bytes
        echo "\nFirst 5 bytes of body:\n";
        for ($i = 0; $i < 5; $i++) {
            echo "  body[$i] = 0x" . sprintf('%02X', $body[$i]) . "\n";
        }
        
        // Parse type (first byte)
        $type = $body[0];
        echo "\nType byte: 0x" . sprintf('%02X', $type) . " (should be 0x02 for string)\n";
        
        // Parse length (bytes 1-2)
        $length = FlvDemux::readUint16($body, 1, !$leValue);
        echo "String length (big-endian): $length\n";
        $lengthLE = FlvDemux::readUint16($body, 1, $leValue);
        echo "String length (little-endian): $lengthLE\n";
        
        // Extract string
        $strBytes = array_slice($body, 3, $length);
        $str = '';
        foreach ($strBytes as $byte) {
            $str .= chr($byte);
        }
        echo "Extracted string: '$str'\n";
        
        // Now test parseScript
        echo "\nTesting parseScript:\n";
        $result = FlvDemux::parseScript($body, 0);
        echo "Result data: '" . ($result['data'] ?? 'null') . "'\n";
        echo "Result size: " . ($result['size'] ?? 'null') . "\n";
        
        break;
    }
    
    $dataSize = ($flvArray[$offset + 1] << 16) | ($flvArray[$offset + 2] << 8) | $flvArray[$offset + 3];
    $offset += 11 + $dataSize + 4;
}
?>