<?php

namespace xiaosongshu\flv;
/**
 * 翻译自 flv.js 的 flvdemux.js
 * 用于解析 FLV 文件中的 AMF (Action Message Format) 数据。
 *
 * PHP 移植注意事项：
 * - 原始代码使用 DataView 读取二进制，这里用 unpack 配合大端格式。
 * - 所有偏移量为绝对位置，直接针对传入的完整二进制字符串。
 * - AMF 日期类型返回毫秒级 Unix 时间戳（整数），而非 DateTime 对象。
 * - 大小端：FLV/AMF 统一使用大端序，已内置。
 */
class FlvDemux
{
    /**
     * 读取 8 位无符号整数
     */
    public static function readUInt8($data, $offset)
    {
        return ord($data[$offset]);
    }

    /**
     * 读取 16 位大端无符号整数
     */
    public static function readUInt16BE($data, $offset)
    {
        return unpack('n', substr($data, $offset, 2))[1];
    }

    /**
     * 读取 32 位大端无符号整数
     */
    public static function readUInt32BE($data, $offset)
    {
        return unpack('N', substr($data, $offset, 4))[1];
    }

    /**
     * 读取 16 位大端有符号整数
     */
    public static function readInt16BE($data, $offset)
    {
        $val = self::readUInt16BE($data, $offset);
        if ($val >= 0x8000) {
            $val -= 0x10000;
        }
        return $val;
    }

    /**
     * 读取 64 位大端双精度浮点数（IEEE 754）
     */
    public static function readFloat64BE($data, $offset)
    {
        // 'J' 表示大端双精度（PHP 7.0+）
        return unpack('J', substr($data, $offset, 8))[1];
    }

    /**
     * 将字节序列按 UTF-8 解码为字符串。
     * 假设输入已经是合法的 UTF-8 字节流。
     */
    public static function decodeUTF8($bytes)
    {
        // 如需严格转换，可使用 mb_convert_encoding($bytes, 'UTF-8', 'UTF-8')
        return $bytes;
    }

    /**
     * 解析 AMF 短字符串
     *
     * @param string $buffer 二进制数据
     * @param int $dataOffset 绝对偏移
     * @return array ['data' => string, 'size' => int 该字符串占用的字节数]
     */
    public static function parseString($buffer, $dataOffset)
    {
        $length = self::readUInt16BE($buffer, $dataOffset);
        if ($length > 0) {
            $str = self::decodeUTF8(substr($buffer, $dataOffset + 2, $length));
        } else {
            $str = '';
        }
        return [
            'data' => $str,
            'size' => 2 + $length,
        ];
    }

    /**
     * 解析 AMF 长字符串（4 字节长度）
     */
    public static function parseLongString($buffer, $dataOffset)
    {
        $length = self::readUInt32BE($buffer, $dataOffset);
        if ($length > 0) {
            $str = self::decodeUTF8(substr($buffer, $dataOffset + 4, $length));
        } else {
            $str = '';
        }
        return [
            'data' => $str,
            'size' => 4 + $length,
        ];
    }

    /**
     * 解析 AMF 日期类型
     *
     * @return array ['data' => int 毫秒时间戳, 'size' => 10]
     */
    public static function parseDate($buffer, $dataOffset)
    {
        $timestamp = self::readFloat64BE($buffer, $dataOffset);
        $localTimeOffset = self::readInt16BE($buffer, $dataOffset + 8);
        $timestamp += $localTimeOffset * 60 * 1000; // 转为 UTC 毫秒
        return [
            'data' => (int)$timestamp,
            'size' => 10,
        ];
    }

    /**
     * 解析 AMF 对象（键值对），适用于嵌套解析
     */
    public static function parseObject($buffer, $dataOffset, $dataSize = null)
    {
        $name = self::parseString($buffer, $dataOffset);
        $value = self::parseScript($buffer, $dataOffset + $name['size']);
        $objectEnd = false; // 原始代码中 parseScript 不返回 objectEnd，此处保持 false
        return [
            'data' => [
                'name' => $name['data'],
                'value' => $value['data'],
            ],
            'size' => $value['size'], // parseScript 返回的绝对偏移
            'objectEnd' => $objectEnd,
        ];
    }

    /**
     * 解析 AMF 变量（混合数组中的元素，实际与 Object 一致）
     */
    public static function parseVariable($buffer, $dataOffset, $dataSize = null)
    {
        return self::parseObject($buffer, $dataOffset, $dataSize);
    }

    /**
     * 解析顶层 FLV metadata，返回键值对
     *
     * @param string $arr 完整二进制数据
     * @return array
     */
    public static function parseMetadata($arr)
    {
        $name = self::parseScript($arr, 0);
        $value = self::parseScript($arr, $name['size'], strlen($arr) - $name['size']);
        return [$name['data'] => $value['data']];
    }

    /**
     * 解析 AMF 脚本数据，支持所有 AMF 类型
     *
     * @param string $arr 二进制数据
     * @param int $offset 起始偏移
     * @param int|null $dataSize 该数据块的大小，null 表示直至数据末尾
     * @return array ['data' => mixed, 'size' => int 下一次读取的绝对偏移]
     */
    public static function parseScript($arr, $offset, $dataSize = null)
    {
        if ($dataSize === null) {
            $dataSize = strlen($arr) - $offset;
        }
        $dataOffset = $offset; // 当前绝对偏移
        $objectEnd = false;

        $type = self::readUInt8($arr, $dataOffset);
        $dataOffset += 1;

        switch ($type) {
            case 0: // Number (Double)
                $value = self::readFloat64BE($arr, $dataOffset);
                $dataOffset += 8;
                break;

            case 1: // Boolean
                $b = self::readUInt8($arr, $dataOffset);
                $value = (bool)$b;
                $dataOffset += 1;
                break;

            case 2: // String
                $amfstr = self::parseString($arr, $dataOffset);
                $value = $amfstr['data'];
                $dataOffset += $amfstr['size'];
                break;

            case 3: // Object
                $value = [];
                $terminal = 0;
                // 检查末尾是否有缺失的 ScriptDataObjectEnd (0x000009)
                if ($dataSize >= 4) {
                    $tailCheck = self::readUInt32BE($arr, $offset + $dataSize - 4) & 0x00FFFFFF;
                    if ($tailCheck === 9) {
                        $terminal = 3;
                    }
                }
                $limit = $offset + $dataSize - 4 - $terminal;
                while ($dataOffset < $limit) {
                    $amfobj = self::parseObject($arr, $dataOffset, $dataSize - ($dataOffset - $offset) - $terminal);
                    if ($amfobj['objectEnd']) {
                        break;
                    }
                    $value[$amfobj['data']['name']] = $amfobj['data']['value'];
                    $dataOffset = $amfobj['size'];
                }
                // 消费末尾的 ObjectEnd 标记
                if ($dataOffset <= $offset + $dataSize - 3) {
                    $marker = self::readUInt32BE($arr, $dataOffset - 1) & 0x00FFFFFF;
                    if ($marker === 9) {
                        $dataOffset += 3;
                    }
                }
                break;

            case 8: // ECMA Array (Mixed array)
                $value = [];
                $dataOffset += 4; // 跳过 ECMAArrayLength (UI32)
                $terminal = 0;
                if ($dataSize >= 4) {
                    $tailCheck = self::readUInt32BE($arr, $offset + $dataSize - 4) & 0x00FFFFFF;
                    if ($tailCheck === 9) {
                        $terminal = 3;
                    }
                }
                $limit = $offset + $dataSize - 8 - $terminal;
                while ($dataOffset < $limit) {
                    $amfvar = self::parseVariable($arr, $dataOffset);
                    if ($amfvar['objectEnd']) {
                        break;
                    }
                    $value[$amfvar['data']['name']] = $amfvar['data']['value'];
                    $dataOffset = $amfvar['size'];
                }
                if ($dataOffset <= $offset + $dataSize - 3) {
                    $marker = self::readUInt32BE($arr, $dataOffset - 1) & 0x00FFFFFF;
                    if ($marker === 9) {
                        $dataOffset += 3;
                    }
                }
                break;

            case 9: // ScriptDataObjectEnd
                $value = null;
                $dataOffset = $offset + 1; // 等效于退出当前块
                $objectEnd = true;
                break;

            case 10: // Strict Array
                $strictArrayLength = self::readUInt32BE($arr, $dataOffset);
                $dataOffset += 4;
                $value = [];
                for ($i = 0; $i < $strictArrayLength; $i++) {
                    $val = self::parseScript($arr, $dataOffset);
                    $value[] = $val['data'];
                    $dataOffset = $val['size'];
                }
                break;

            case 11: // Date
                $date = self::parseDate($arr, $dataOffset);
                $value = $date['data'];
                $dataOffset += $date['size'];
                break;

            case 12: // Long String
                $amfLongStr = self::parseLongString($arr, $dataOffset);
                $value = $amfLongStr['data'];
                $dataOffset += $amfLongStr['size'];
                break;

            default:
                // 未知类型，跳过整个数据块
                $dataOffset = $offset + $dataSize;
                break;
        }

        return [
            'data' => $value,
            'size' => $dataOffset,
        ];
    }
}