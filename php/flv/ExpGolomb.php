<?php

class ExpGolomb {
    private $_buffer = [];
    private $_buffer_index = 0;
    private $_total_bytes = 0;
    private $_total_bits = 0;
    private $_current_word = 0;
    private $_current_word_bits_left = 0;

    public function __construct($uint8array) {
        $this->_buffer = $uint8array;
        $this->_buffer_index = 0;
        $this->_total_bytes = count($uint8array);
        $this->_total_bits = count($uint8array) * 8;
        $this->_current_word = 0;
        $this->_current_word_bits_left = 0;
    }

    public function destroy() {
        $this->_buffer = null;
    }

    private function _fillCurrentWord() {
        $buffer_bytes_left = $this->_total_bytes - $this->_buffer_index;
        if ($buffer_bytes_left <= 0) {
            throw new Exception('ExpGolomb: _fillCurrentWord() but no bytes available');
        }

        $bytes_read = min(4, $buffer_bytes_left);
        $word = [0, 0, 0, 0];
        for ($i = 0; $i < $bytes_read; $i++) {
            $word[$i] = $this->_buffer[$this->_buffer_index + $i];
        }

        $this->_current_word = ($word[0] << 24) | ($word[1] << 16) | ($word[2] << 8) | $word[3];
        $this->_current_word = $this->_current_word & 0xFFFFFFFF;
        $this->_buffer_index += $bytes_read;
        $this->_current_word_bits_left = $bytes_read * 8;
    }

    public function readBits($bits) {
        if ($bits > 32) {
            throw new Exception('ExpGolomb: readBits() bits exceeded max 32bits!');
        }

        if ($bits <= $this->_current_word_bits_left) {
            $shift = 32 - $bits;
            $result = ($this->_current_word >> $shift) & (0xFFFFFFFF >> (32 - $bits));
            $this->_current_word = ($this->_current_word << $bits) & 0xFFFFFFFF;
            $this->_current_word_bits_left -= $bits;
            return $result;
        }

        $result = 0;
        if ($this->_current_word_bits_left > 0) {
            $result = $this->_current_word & (0xFFFFFFFF >> (32 - $this->_current_word_bits_left));
        }
        $bits_need_left = $bits - $this->_current_word_bits_left;
        $this->_current_word_bits_left = 0;

        $this->_fillCurrentWord();
        $bits_read_next = min($bits_need_left, $this->_current_word_bits_left);

        $shift = 32 - $bits_read_next;
        $result2 = ($this->_current_word >> $shift) & (0xFFFFFFFF >> (32 - $bits_read_next));
        $this->_current_word = ($this->_current_word << $bits_read_next) & 0xFFFFFFFF;
        $this->_current_word_bits_left -= $bits_read_next;

        $result = ($result << $bits_read_next) | $result2;
        return $result;
    }

    public function readBool() {
        return $this->readBits(1) === 1;
    }

    public function readByte() {
        return $this->readBits(8);
    }

    private function _skipLeadingZero() {
        $zero_count = 0;
        for ($zero_count = 0; $zero_count < $this->_current_word_bits_left; $zero_count++) {
            if (($this->_current_word & (0x80000000 >> $zero_count)) !== 0) {
                $this->_current_word = ($this->_current_word << $zero_count) & 0xFFFFFFFF;
                $this->_current_word_bits_left -= $zero_count;
                return $zero_count;
            }
        }
        $this->_fillCurrentWord();
        return $zero_count + $this->_skipLeadingZero();
    }

    public function readUEG() {
        $leading_zeros = $this->_skipLeadingZero();
        return $this->readBits($leading_zeros + 1) - 1;
    }

    public function readSEG() {
        $value = $this->readUEG();
        if ($value & 0x01) {
            return ($value + 1) >> 1;
        } else {
            return -1 * ($value >> 1);
        }
    }
}
?>