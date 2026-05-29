<?php

namespace xiaosongshu\flv;

class FlvParse
{
    public static $tempUint8;   // 当前接收的 FLV 二进制数据（字符串）
    public static $arrTag = []; // 收集到的标签数组
    public static $index = 0;   // 当前读取位置（字节偏移）
    public static $stop = false;
    public static $offset = 0;  // 上一次标签起始偏移
    public static $frist = true; // 是否第一次调用 setFlv
    public static $_hasAudio = false;
    public static $_hasVideo = false;

    /**
     * 接收外部 FLV 二进制数据并解析
     *
     * @param string $uint8 二进制字符串（原始字节）
     * @return int 本次解析结束时的偏移量
     */
    public static function setFlv($uint8)
    {
        self::$stop = false;
        self::$arrTag = [];
        self::$index = 0;
        self::$tempUint8 = $uint8;

        $len = strlen(self::$tempUint8);
        // 检查 FLV 文件头：至少 13 字节，且前三个字节为 'F' 'L' 'V'
        if ($len > 13 && ord(self::$tempUint8[0]) == 70 && ord(self::$tempUint8[1]) == 76 && ord(self::$tempUint8[2]) == 86) {
            self::probe(self::$tempUint8);
            self::read(9);  // 跳过 9 字节 FLV 头
            self::read(4);  // 跳过第一个 PreviousTagSize (4 字节)
            self::parse();
            self::$frist = false;
            return self::$offset;
        } elseif (!self::$frist) {
            // 非首次调用，直接继续解析
            return self::parse();
        } else {
            return self::$offset;
        }
    }

    /**
     * 探测 FLV 文件头中的音视频标志
     *
     * @param string $buffer 二进制数据
     * @return array
     */
    public static function probe($buffer)
    {
        // FLV 头部固定：'FLV' + 版本（1）
        if (ord($buffer[0]) !== 0x46 || ord($buffer[1]) !== 0x4C || ord($buffer[2]) !== 0x56 || ord($buffer[3]) !== 0x01) {
            return ['match' => false];
        }

        $flags = ord($buffer[4]);
        $hasAudio = (($flags & 4) >> 2) !== 0;
        $hasVideo = ($flags & 1) !== 0;

        if (!$hasAudio && !$hasVideo) {
            return ['match' => false];
        }

        self::$_hasAudio = $hasAudio;
        self::$_hasVideo = $hasVideo;
        // 同步到全局标志
        TagDemux::$_hasAudio = $hasAudio;
        TagDemux::$_hasVideo = $hasVideo;

        return [
            'match' => true,
            'hasAudioTrack' => $hasAudio,
            'hasVideoTrack' => $hasVideo
        ];
    }

    /**
     * 解析 FLV 标签流
     *
     * @return int 当前索引位置（即偏移量）
     */
    public static function parse()
    {
        $len = strlen(self::$tempUint8);
        while (self::$index < $len && !self::$stop) {
            self::$offset = self::$index;

            $t = new FlvTag();
            // 标签头至少需要 11 字节
            if ($len - self::$index >= 11) {
                $t->tagType = ord(self::read(1)[0]);           // 1 字节类型
                $t->dataSize = self::read3BytesAsInt();        // 3 字节 body 大小
                $t->Timestamp = self::read4BytesExtended();    // 4 字节时间戳（含扩展位）
                $t->StreamID = self::read3BytesAsInt();        // 3 字节流 ID（通常为 0）
            } else {
                self::$stop = true;
                continue;
            }

            // 检查剩余数据是否足够读取 body + 4 字节 PreviousTagSize
            $bodySize = $t->dataSize;
            if ($len - self::$index >= ($bodySize + 4)) {
                $t->body = self::read($bodySize);  // 读取 body 二进制字符串
                // 仅收集我们关心的标签
                if ($t->tagType == 9 && self::$_hasVideo) {
                    self::$arrTag[] = $t;
                }
                if ($t->tagType == 8 && self::$_hasAudio) {
                    self::$arrTag[] = $t;
                }
                if ($t->tagType == 18) {
                    if (count(self::$arrTag) == 0) {
                        self::$arrTag[] = $t;
                    } else {
                        // 模拟 console.log 行为，实际使用时可根据需要处理
                        // 此处保留注释，表示截获的自定义数据
                    }
                }
                $t->size = self::read4BytesAsInt(); // 读取 PreviousTagSize (4 字节)
            } else {
                self::$stop = true;
                continue;
            }
            self::$offset = self::$index;
        }
        return self::$offset;
    }

    /**
     * 读取指定长度的字节，并前进索引
     *
     * @param int $length 字节数
     * @return string 读取到的二进制数据
     */
    public static function read($length)
    {
        $data = substr(self::$tempUint8, self::$index, $length);
        self::$index += $length;
        return $data;
    }

    /**
     * 将 3 字节转换为整数
     *
     * @param string $bytes 3 字节字符串
     * @return int
     */
    public static function getBodySum($bytes)
    {
        if (strlen($bytes) < 3) {
            return 0;
        }
        return (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]);
    }

    /**
     * 直接从当前缓冲区读取 3 字节并转换为整数
     *
     * @return int
     */
    public static function read3BytesAsInt()
    {
        $bytes = self::read(3);
        return self::getBodySum($bytes);
    }

    /**
     * 读取 4 字节时间戳（FLV 时间戳格式：24位时间戳 + 8位扩展，大端序）
     *
     * @return int
     */
    public static function read4BytesExtended()
    {
        $bytes = self::read(4);
        return unpack('N', $bytes)[1];
    }

    /**
     * 读取 4 字节 PreviousTagSize（无符号 32 位大端整数）
     *
     * @return int
     */
    public static function read4BytesAsInt()
    {
        $bytes = self::read(4);
        return unpack('N', $bytes)[1];
    }

    /**
     * 获取所有已解析的标签
     *
     * @return array
     */
    public static function getTags()
    {
        return self::$arrTag;
    }

    /**
     * 重置解析器状态
     */
    public static function reset()
    {
        self::$tempUint8 = '';
        self::$arrTag = [];
        self::$index = 0;
        self::$stop = false;
        self::$offset = 0;
        self::$frist = true;
        self::$_hasAudio = false;
        self::$_hasVideo = false;
    }
}