<?php

require_once 'FlvTag.php';

class FlvParse {
    private $tempUint8 = [];
    public $arrTag = [];
    private $index = 0;
    private $tempArr = [];
    private $stop = false;
    public $offset = 0;
    private $frist = true;
    public $_hasAudio = false;
    public $_hasVideo = false;
    private $_onTag = null;

    public function setFlv($uint8) {
        $this->stop = false;
        $this->arrTag = [];
        $this->index = 0;
        $this->tempUint8 = $uint8;
        
        if (count($this->tempUint8) > 13 && 
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

    public function setOnTag($callback) {
        $this->_onTag = $callback;
    }

    public function probe($buffer) {
        $data = $buffer;
        $mismatch = ['match' => false];

        if ($data[0] !== 0x46 || $data[1] !== 0x4C || $data[2] !== 0x56 || $data[3] !== 0x01) {
            return $mismatch;
        }

        $hasAudio = (($data[4] & 4) >> 2) !== 0;
        $hasVideo = ($data[4] & 1) !== 0;

        if (!$hasAudio && !$hasVideo) {
            return $mismatch;
        }
        
        $this->_hasAudio = $hasAudio;
        $this->_hasVideo = $hasVideo;
        
        return [
            'match' => true,
            'hasAudioTrack' => $hasAudio,
            'hasVideoTrack' => $hasVideo
        ];
    }

    public function parse() {
        $totalLength = count($this->tempUint8);
        
        while ($this->index < $totalLength && !$this->stop) {
            $this->offset = $this->index;

            if ($totalLength - $this->index < 11) {
                $this->stop = true;
                continue;
            }

            $t = new FlvTag();
            $read = $this->read(1);
            $t->tagType = $read[0];
            $t->dataSize = $this->read(3);
            $t->Timestamp = $this->read(4);
            $t->StreamID = $this->read(3);
            
            $bodySum = $this->getBodySum($t->dataSize);
            
            if ($totalLength - $this->index < ($bodySum + 4)) {
                $this->stop = true;
                continue;
            }
            
            $t->body = $this->read($bodySum);
            
            $shouldAdd = false;
            if ($t->tagType == 9 && $this->_hasVideo) {
                $shouldAdd = true;
            } else if ($t->tagType == 8 && $this->_hasAudio) {
                $shouldAdd = true;
            } else if ($t->tagType == 18 && count($this->arrTag) == 0) {
                $shouldAdd = true;
            }
            
            if ($shouldAdd) {
                if ($this->_onTag) {
                    call_user_func($this->_onTag, $t);
                } else {
                    $this->arrTag[] = $t;
                }
            }
            
            $this->read(4);
            $this->offset = $this->index;
        }

        return $this->offset;
    }

    private function read($length) {
        $u8a = array_slice($this->tempUint8, $this->index, $length);
        $this->index += $length;
        return $u8a;
    }

    private function getBodySum($arr) {
        return ($arr[0] << 16) | ($arr[1] << 8) | $arr[2];
    }
}
?>