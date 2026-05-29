<?php

namespace xiaosongshu\flv;

class FlvTag
{
    public $tagType = -1;
    public $dataSize = -1;
    public $Timestamp = '';
    public $StreamID = -1;
    public $body = '';
    public $time = -1;
    public $arr = [];
    public $size = -1;

    public function __construct() {}

    public function getTime()
    {
        if (strlen($this->Timestamp) < 3) return 0;
        $time = (ord($this->Timestamp[0]) << 16) |
            (ord($this->Timestamp[1]) << 8)  |
            ord($this->Timestamp[2]);
        $this->time = $time;
        return $time;
    }
}