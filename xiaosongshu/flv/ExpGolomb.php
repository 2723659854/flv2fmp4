<?php

namespace xiaosongshu\flv;
/**
 * 翻译自 Bilibili flv.js 中的 Exp-Golomb 解码器 (exp-golomb.js)
 *
 * Copyright (C) 2016 Bilibili. All Rights Reserved.
 * @author zheng qian <xqq@xqq.im>
 *
 * Licensed under the Apache License, Version 2.0.
 *
 * PHP 移植注意事项：
 * - PHP 中没有无符号右移（>>>），使用辅助方法 uRShift32 模拟 32 位无符号右移。
 * - 所有位运算结果保持 32 位无符号截断（& 0xFFFFFFFF），以匹配 JavaScript 行为。
 * - 输入为原始字节字符串，通过 substr/unpack 模拟 DataView 的大端读取。
 */
class ExpGolomb
{
    public $_buffer;          // 原始字节字符串
    public $_buffer_index;    // 当前读取位置
    public $_total_bytes;     // 总字节数
    public $_total_bits;      // 总位数
    public $_current_word;    // 当前缓存字（32位无符号整数）
    public $_current_word_bits_left; // 当前缓存字剩余可用位数

    /**
     * @param string $uint8array 原始字节流（二进制字符串）
     */
    public function __construct($uint8array)
    {
        $this->_buffer = $uint8array;
        $this->_buffer_index = 0;
        $this->_total_bytes = strlen($uint8array);
        $this->_total_bits = $this->_total_bytes * 8;
        $this->_current_word = 0;
        $this->_current_word_bits_left = 0;
    }

    public function destroy()
    {
        $this->_buffer = null;
    }

    /**
     * 从缓冲区填充当前字（最多4字节，大端序）
     */
    public function _fillCurrentWord()
    {
        $buffer_bytes_left = $this->_total_bytes - $this->_buffer_index;
        if ($buffer_bytes_left <= 0) {
            throw new \RuntimeException('ExpGolomb: _fillCurrentWord() but no bytes available');
        }

        $bytes_read = min(4, $buffer_bytes_left);
        // 截取所需字节，不足4字节时右侧用 \0 填充，模拟 Uint8Array(4) 初始化为0
        $bytes = substr($this->_buffer, $this->_buffer_index, $bytes_read);
        $word = str_pad($bytes, 4, "\0", STR_PAD_RIGHT);
        // 以大端序读取无符号32位整数
        $unpacked = unpack('N', $word);
        $this->_current_word = $unpacked[1]; // 可能为负数，但数值正确

        $this->_buffer_index += $bytes_read;
        $this->_current_word_bits_left = $bytes_read * 8;
    }

    /**
     * 32位无符号右移（模拟 JavaScript 的 >>> 运算符）
     *
     * @param int $val 输入值（视为32位无符号整数）
     * @param int $bits 右移位数
     * @return int
     */
    public static function uRShift32($val, $bits)
    {
        $val &= 0xFFFFFFFF; // 确保32位无符号
        if ($bits == 0) {
            return $val;
        }
        if ($bits >= 32) {
            return 0;
        }
        // 算术右移后用掩码清除符号扩展位
        return ($val >> $bits) & ((1 << (32 - $bits)) - 1);
    }

    /**
     * 读取指定位数
     *
     * @param int $bits 位数（1-32）
     * @return int
     */
    public function readBits($bits)
    {
        if ($bits > 32) {
            throw new \InvalidArgumentException('ExpGolomb: readBits() bits exceeded max 32bits!');
        }

        // 当前缓存字剩余位数足够
        if ($bits <= $this->_current_word_bits_left) {
            $result = self::uRShift32($this->_current_word, 32 - $bits);
            $this->_current_word = ($this->_current_word << $bits) & 0xFFFFFFFF;
            $this->_current_word_bits_left -= $bits;
            return $result;
        }

        // 需要跨字读取
        $result = $this->_current_word_bits_left ? $this->_current_word : 0;
        $result = self::uRShift32($result, 32 - $this->_current_word_bits_left);
        $bits_need_left = $bits - $this->_current_word_bits_left;

        $this->_fillCurrentWord();
        $bits_read_next = min($bits_need_left, $this->_current_word_bits_left);

        $result2 = self::uRShift32($this->_current_word, 32 - $bits_read_next);
        $this->_current_word = ($this->_current_word << $bits_read_next) & 0xFFFFFFFF;
        $this->_current_word_bits_left -= $bits_read_next;

        $result = (($result << $bits_read_next) | $result2) & 0xFFFFFFFF;
        return $result;
    }

    /**
     * 读取一个比特作为布尔值
     *
     * @return bool
     */
    public function readBool()
    {
        return $this->readBits(1) === 1;
    }

    /**
     * 读取一个字节（8位）
     *
     * @return int
     */
    public function readByte()
    {
        return $this->readBits(8);
    }

    /**
     * 跳过前导零比特，返回跳过的零的数量
     *
     * @return int
     */
    public function _skipLeadingZero()
    {
        $zero_count = 0;
        while ($zero_count < $this->_current_word_bits_left) {
            // 从最高位向低位检查，掩码 0x80000000 >>> zero_count
            if (($this->_current_word & (0x80000000 >> $zero_count)) !== 0) {
                // 消耗掉这些零比特
                $this->_current_word = ($this->_current_word << $zero_count) & 0xFFFFFFFF;
                $this->_current_word_bits_left -= $zero_count;
                return $zero_count;
            }
            $zero_count++;
        }
        // 当前字全为零，填充新字并递归
        $this->_fillCurrentWord();
        return $zero_count + $this->_skipLeadingZero();
    }

    /**
     * 读取无符号指数哥伦布编码值 (UE Golomb)
     *
     * @return int
     */
    public function readUEG()
    {
        $leading_zeros = $this->_skipLeadingZero();
        return $this->readBits($leading_zeros + 1) - 1;
    }

    /**
     * 读取有符号指数哥伦布编码值 (SE Golomb)
     *
     * @return int
     */
    public function readSEG()
    {
        $value = $this->readUEG();
        if ($value & 0x01) {
            return self::uRShift32($value + 1, 1);
        } else {
            return -1 * self::uRShift32($value, 1);
        }
    }
}