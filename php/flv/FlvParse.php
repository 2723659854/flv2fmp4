<?php
/**
 * FLV 解析器
 */
class FlvParse {
    private $tempUint8 = [];
    private $arrTag = [];
    private $index = 0;
    private $tempArr = [];
    private $stop = false;
    private $offset = 0;
    private $frist = true;
    private $_hasAudio = false;
    private $_hasVideo = false;
    
    /**
     * 接受外部的FLV二进制数据
     */
    function setFlv($uint8) {
        $this->stop = false;
        $this->arrTag = [];
        $this->index = 0;
        $this->tempUint8 = $uint8;
        
        $len = count($this->tempUint8);
        if ($len > 13 && 
            $this->tempUint8[0] == 70 && 
            $this->tempUint8[1] == 76 && 
            $this->tempUint8[2] == 86) {
            
            $this->probe($this->tempUint8);
            $this->read(9);
            $this->read(4);
            $this->parse();
            $this->frist = false;
            return $this->offset;
        } else if (!$this->frist) {
            return $this->parse();
        } else {
            return $this->offset;
        }
    }
    
    function probe($data) {
        if ($data[0] !== 0x46 || $data[1] !== 0x4C || $data[2] !== 0x56 || $data[3] !== 0x01) {
            return ['match' => false];
        }
        
        $hasAudio = (($data[4] & 4) >> 2) !== 0;
        $hasVideo = ($data[4] & 1) !== 0;
        
        if (!$hasAudio && !$hasVideo) {
            return ['match' => false];
        }
        
        $this->_hasAudio = $hasAudio;
        $this->_hasVideo = $hasVideo;
        
        return [
            'match' => true,
            'hasAudioTrack' => $hasAudio,
            'hasVideoTrack' => $hasVideo
        ];
    }
    
    /**
     * 开始解析
     */
    function parse() {
        while ($this->index < count($this->tempUint8) && !$this->stop) {
            $this->offset = $this->index;
            
            $t = new FlvTag();
            if (count($this->tempUint8) - $this->index >= 11) {
                $t->tagType = ($this->read(1)[0]);
                $t->dataSize = $this->read(3);
                $t->Timestamp = $this->read(4);
                $t->StreamID = $this->read(3);
            } else {
                $this->stop = true;
                continue;
            }
            
            $bodySum = $this->getBodySum($t->dataSize);
            if (count($this->tempUint8) - $this->index >= ($bodySum + 4)) {
                $t->body = $this->read($bodySum);
                
                if ($t->tagType == 9 && $this->_hasVideo) {
                    $this->arrTag[] = $t;
                }
                if ($t->tagType == 8 && $this->_hasAudio) {
                    $this->arrTag[] = $t;
                }
                if ($t->tagType == 18) {
                    if (count($this->arrTag) == 0) {
                        $this->arrTag[] = $t;
                    }
                }
                
                $t->size = $this->read(4);
            } else {
                $this->stop = true;
                continue;
            }
            
            $this->offset = $this->index;
        }
        
        return $this->offset;
    }
    
    function read($length) {
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            if (isset($this->tempUint8[$this->index])) {
                $result[] = $this->tempUint8[$this->index];
                $this->index++;
            }
        }
        return $result;
    }
    
    /**
     * 计算tag包体大小
     */
    function getBodySum($arr) {
        $str = '';
        foreach ($arr as $byte) {
            $hex = dechex($byte);
            if (strlen($hex) == 1) {
                $hex = '0' . $hex;
            }
            $str .= $hex;
        }
        return hexdec($str);
    }
    
    function getTags() {
        return $this->arrTag;
    }
    
    function getHasAudio() {
        return $this->_hasAudio;
    }
    
    function getHasVideo() {
        return $this->_hasVideo;
    }
}
