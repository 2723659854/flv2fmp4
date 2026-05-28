<?php

class FlvTag {
    public $tagType = -1;
    public $dataSize = [];
    public $Timestamp = [];
    public $StreamID = [];
    public $body = [];
    public $time = -1;
    public $arr = [];
    public $size = [];

    public function getTime() {
        $this->arr = [];
        foreach ($this->Timestamp as $byte) {
            $hex = dechex($byte);
            if (strlen($hex) == 1) {
                $hex = '0' . $hex;
            }
            $this->arr[] = $hex;
        }
        array_pop($this->arr);
        $time = implode('', $this->arr);
        $this->time = hexdec($time);
        return $this->time;
    }
}
?>