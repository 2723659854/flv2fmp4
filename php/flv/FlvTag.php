<?php
/**
 * FLV Tag 类
 */
class FlvTag {
    public $tagType;
    public $dataSize;
    public $Timestamp;
    public $StreamID;
    public $body;
    public $size;
    
    function __construct() {
        $this->tagType = 0;
        $this->dataSize = [];
        $this->Timestamp = [];
        $this->StreamID = [];
        $this->body = [];
        $this->size = [];
    }
    
    function getTime() {
        $arr = $this->Timestamp;
        return ($arr[3] << 24) | ($arr[2] << 16) | ($arr[1] << 8) | $arr[0];
    }
}
