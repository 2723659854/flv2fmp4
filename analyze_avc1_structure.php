<?php
// Analyze avc1 data structure
$data = [
    0x00, 0x00, 0x00, 0x00,  // bytes 0-3: reserved
    0x00, 0x00, 0x00, 0x01,  // bytes 4-7: data_reference_index
    0x00, 0x00, 0x00, 0x00,  // bytes 8-11: pre_defined
    0x00, 0x00, 0x00, 0x00,  // bytes 12-15: pre_defined
    0x00, 0x00, 0x00, 0x00,  // bytes 16-19: pre_defined
    0x02, 0xD0,              // bytes 20-21: width (720)
    0x02, 0xE6,              // bytes 22-23: height (742)
    0x00, 0x48, 0x00, 0x00,  // bytes 24-27: horizresolution
    0x00, 0x48, 0x00, 0x00,  // bytes 28-31: vertresolution
    0x00, 0x00, 0x00, 0x00,  // bytes 32-35: reserved
    0x00, 0x01,              // bytes 36-37: frame_count
    0x04,                    // byte 38: compressorname length
    0x67, 0x31, 0x31, 0x31,  // bytes 39-42: compressorname ("g111")
    0x00, 0x00, 0x00, 0x00,  // bytes 43-46: reserved
    0x00, 0x00, 0x00, 0x00,  // bytes 47-50: reserved
    0x00, 0x00, 0x00, 0x00,  // bytes 51-54: reserved
    0x00, 0x00, 0x00, 0x00,  // bytes 55-58: reserved
    0x00, 0x00, 0x00, 0x00,  // bytes 59-62: reserved
    0x00, 0x00, 0x00,        // bytes 63-65: depth (3 bytes)
    0x00, 0x18,              // bytes 66-67: pre_defined
    0xFF, 0xFF               // bytes 68-69: pre_defined
];

echo "Total bytes in avc1 data: " . count($data) . "\n";
echo "\nStructure analysis:\n";
echo "Bytes 0-3: reserved = " . dechex($data[0]) . " " . dechex($data[1]) . " " . dechex($data[2]) . " " . dechex($data[3]) . "\n";
echo "Bytes 4-7: data_reference_index = " . (($data[4] << 24) | ($data[5] << 16) | ($data[6] << 8) | $data[7]) . "\n";
echo "Bytes 8-19: pre_defined (12 bytes)\n";
echo "Bytes 20-21: width = " . (($data[20] << 8) | $data[21]) . " (0x" . dechex($data[20]) . dechex($data[21]) . ")\n";
echo "Bytes 22-23: height = " . (($data[22] << 8) | $data[23]) . " (0x" . dechex($data[22]) . dechex($data[23]) . ")\n";
echo "Bytes 24-27: horizresolution = " . dechex($data[24]) . " " . dechex($data[25]) . " " . dechex($data[26]) . " " . dechex($data[27]) . "\n";
echo "Bytes 28-31: vertresolution = " . dechex($data[28]) . " " . dechex($data[29]) . " " . dechex($data[30]) . " " . dechex($data[31]) . "\n";

// After adding box header (8 bytes), avc1 box structure:
echo "\nAfter adding box header (8 bytes):\n";
echo "Bytes 0-3: size\n";
echo "Bytes 4-7: type ('avc1')\n";
echo "Bytes 8-11: reserved\n";
echo "Bytes 12-15: data_reference_index\n";
echo "Bytes 16-27: pre_defined\n";
echo "Bytes 28-29: width\n";
echo "Bytes 30-31: height\n";
?>