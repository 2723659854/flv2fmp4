<?php

require_once __DIR__ . '/../utils/decodeUTF8.php';

class FlvDemux {
    private static $le;

    public function __construct() {
        self::$le = true;
    }

    public static function parseObject($arrayBuffer, $dataOffset, $dataSize) {
        $name = self::parseString($arrayBuffer, $dataOffset, $dataSize);
        $value = self::parseScript($arrayBuffer, $dataOffset + $name['size']);
        $isObjectEnd = $value['objectEnd'];

        return [
            'data' => [
                'name' => $name['data'],
                'value' => $value['data']
            ],
            'size' => $value['size'],
            'objectEnd' => $isObjectEnd
        ];
    }

    public static function parseVariable($arrayBuffer, $dataOffset, $dataSize) {
        return self::parseObject($arrayBuffer, $dataOffset, $dataSize);
    }

    public static function parseLongString($arrayBuffer, $dataOffset, $dataSize) {
        $length = self::readUint32($arrayBuffer, $dataOffset, !self::$le);

        $str = '';
        if ($length > 0) {
            $sub = array_slice($arrayBuffer, $dataOffset + 4, $length);
            $str = decodeUTF8($sub);
        }

        return [
            'data' => $str,
            'size' => 4 + $length
        ];
    }

    public static function parseDate($arrayBuffer, $dataOffset, $dataSize) {
        $timestamp = self::readFloat64($arrayBuffer, $dataOffset, !self::$le);
        $localTimeOffset = self::readInt16($arrayBuffer, $dataOffset + 8, !self::$le);
        $timestamp += $localTimeOffset * 60 * 1000;

        return [
            'data' => $timestamp,
            'size' => 8 + 2
        ];
    }

    public static function parseString($arrayBuffer, $dataOffset, $dataSize) {
        $length = self::readUint16($arrayBuffer, $dataOffset, !self::$le);
        $str = '';
        if ($length > 0) {
            $sub = array_slice($arrayBuffer, $dataOffset + 2, $length);
            $str = decodeUTF8($sub);
        }
        return [
            'data' => $str,
            'size' => 2 + $length
        ];
    }

    public static function parseMetadata($arr) {
        $name = self::parseScript($arr, 0);
        $value = self::parseScript($arr, $name['size'], count($arr) - $name['size']);
        $data = [];
        $data[$name['data']] = $value['data'];
        return $data;
    }

    public static function parseScript($arr, $offset, $dataSize = null) {
        $dataOffset = $offset;
        $object = [];
        $uint8 = $arr;
        $buffer = $uint8;
        $dv = $buffer;
        
        if ($dataSize === null) {
            $dataSize = count($arr) - $offset;
        }

        $value = null;
        $objectEnd = false;
        $type = $dv[$dataOffset];
        $dataOffset += 1;

        switch ($type) {
            case 0:
                $value = self::readFloat64($buffer, $dataOffset, !self::$le);
                $dataOffset += 8;
                break;
            case 1:
                $b = $dv[$dataOffset];
                $value = $b !== 0;
                $dataOffset += 1;
                break;
            case 2:
                $amfstr = self::parseString($buffer, $dataOffset);
                $value = $amfstr['data'];
                $dataOffset += $amfstr['size'];
                break;
            case 3:
                $value = [];
                $terminal = 0;
                if ((self::readUint32($buffer, $dataSize - 4, !self::$le) & 0x00FFFFFF) === 9) {
                    $terminal = 3;
                }
                while ($dataOffset < $dataSize - 4) {
                    $amfobj = self::parseObject($buffer, $dataOffset, $dataSize - $offset - $terminal);
                    if ($amfobj['objectEnd']) break;
                    $value[$amfobj['data']['name']] = $amfobj['data']['value'];
                    $dataOffset = $amfobj['size'];
                }
                if ($dataOffset <= $dataSize - 3) {
                    $marker = self::readUint32($buffer, $dataOffset - 1, !self::$le) & 0x00FFFFFF;
                    if ($marker === 9) {
                        $dataOffset += 3;
                    }
                }
                break;
            case 8:
                $value = [];
                $dataOffset += 4;
                $terminal = 0;
                if ((self::readUint32($buffer, $dataSize - 4, !self::$le) & 0x00FFFFFF) === 9) {
                    $terminal = 3;
                }
                while ($dataOffset < $dataSize - 8) {
                    $amfvar = self::parseVariable($buffer, $dataOffset);
                    if ($amfvar['objectEnd']) break;
                    $value[$amfvar['data']['name']] = $amfvar['data']['value'];
                    $dataOffset = $amfvar['size'];
                }
                if ($dataOffset <= $dataSize - 3) {
                    $marker = self::readUint32($buffer, $dataOffset - 1, !self::$le) & 0x00FFFFFF;
                    if ($marker === 9) {
                        $dataOffset += 3;
                    }
                }
                break;
            case 9:
                $value = null;
                $dataOffset = 1;
                $objectEnd = true;
                break;
            case 10:
                $value = [];
                $strictArrayLength = self::readUint32($buffer, $dataOffset, !self::$le);
                $dataOffset += 4;
                for ($i = 0; $i < $strictArrayLength; $i++) {
                    $val = self::parseScript($buffer, $dataOffset);
                    $value[] = $val['data'];
                    $dataOffset = $val['size'];
                }
                break;
            case 11:
                $date = self::parseDate($buffer, $dataOffset + 1, $dataSize - 1);
                $value = $date['data'];
                $dataOffset += $date['size'];
                break;
            case 12:
                $amfLongStr = self::parseString($buffer, $dataOffset + 1, $dataSize - 1);
                $value = $amfLongStr['data'];
                $dataOffset += $amfLongStr['size'];
                break;
            default:
                $dataOffset = $dataSize;
                break;
        }
        
        return [
            'data' => $value,
            'size' => $dataOffset,
            'objectEnd' => $objectEnd
        ];
    }

    private static function readUint32($buffer, $offset, $littleEndian) {
        if ($littleEndian) {
            return $buffer[$offset] | 
                   ($buffer[$offset + 1] << 8) | 
                   ($buffer[$offset + 2] << 16) | 
                   ($buffer[$offset + 3] << 24);
        } else {
            return ($buffer[$offset] << 24) | 
                   ($buffer[$offset + 1] << 16) | 
                   ($buffer[$offset + 2] << 8) | 
                   $buffer[$offset + 3];
        }
    }

    private static function readUint16($buffer, $offset, $littleEndian) {
        if ($littleEndian) {
            return $buffer[$offset] | ($buffer[$offset + 1] << 8);
        } else {
            return ($buffer[$offset] << 8) | $buffer[$offset + 1];
        }
    }

    private static function readInt16($buffer, $offset, $littleEndian) {
        $val = self::readUint16($buffer, $offset, $littleEndian);
        if ($val >= 0x8000) {
            $val -= 0x10000;
        }
        return $val;
    }

    private static function readFloat64($buffer, $offset, $littleEndian) {
        $bytes = array_slice($buffer, $offset, 8);
        if (!$littleEndian) {
            $bytes = array_reverse($bytes);
        }
        $packed = '';
        foreach ($bytes as $byte) {
            $packed .= chr($byte);
        }
        return unpack('d', $packed)[1];
    }
}
?>