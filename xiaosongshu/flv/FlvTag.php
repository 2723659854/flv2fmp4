<?php

namespace xiaosongshu\flv;
/**
 * 翻译自 flv.js 的 flvTag.js
 * FLV 标签（Tag）数据容器，并负责将存储的 Timestamp 字节数组解析为毫秒时间戳。
 *
 * PHP 移植注意事项：
 * - 原 JS 中 Timestamp 是 Uint8Array（4 字节），此处用原始二进制字符串模拟。
 * - getTime() 复现了通过十六进制字符串拼接后去掉最后一个字节（高 8 位扩展）的逻辑，
 *   最终得到 24 位无符号整数时间戳（单位毫秒）。
 */
class FlvTag
{
    public $tagType = -1;    // 8=音频, 9=视频, 18=脚本数据
    public $dataSize = -1;   // body 长度（字节）
    public $Timestamp = '';  // 4 字节原始二进制字符串（模拟 Uint8Array）
    public $StreamID = -1;   // 流 ID，通常为 0
    public $body = '';       // 标签体二进制数据
    public $time = -1;       // 解析后的毫秒时间戳
    public $arr = [];        // 临时存储十六进制字符串数组
    public $size = -1;       // 整个标签占用的字节数（11 字节头 + body + 4 字节 PreviousTagSize）

    public function __construct()
    {
        // 默认值已通过属性初始值设定
    }

    /**
     * 将 Timestamp（4 字节原始数据）转换为毫秒时间戳
     * 算法源自 FLV 规范：前 3 字节为时间戳低 24 位（大端），第 4 字节为时间戳高 8 位。
     * 原代码只取前 3 字节拼接，因此这里也保持相同行为。
     *
     * @return int 毫秒时间戳
     */
    public function getTime()
    {
        $this->arr = [];
        $len = strlen($this->Timestamp);
        // 逐字节转换为十六进制字符串，不足两位补零
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($this->Timestamp[$i]);
            $hex = dechex($byte);
            if (strlen($hex) == 1) {
                $hex = '0' . $hex;
            }
            $this->arr[] = $hex;
        }
        // 移除最后一个字节（扩展高 8 位）
        array_pop($this->arr);
        // 拼接剩余 3 字节十六进制字符串
        $timeHex = implode('', $this->arr);
        $this->time = intval($timeHex, 16);
        return $this->time;
    }
}