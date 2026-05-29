<?php

namespace xiaosongshu\mp4;

class MediaSegmentInfo
{
    public $beginDts;
    public $endDts;
    public $beginPts;
    public $endPts;
    public $originalBeginDts;
    public $originalEndDts;
    public $syncPoints;    // array of SampleInfo, for video IDR frames only
    public $firstSample;   // SampleInfo
    public $lastSample;    // SampleInfo

    public function __construct()
    {
        $this->beginDts = 0;
        $this->endDts = 0;
        $this->beginPts = 0;
        $this->endPts = 0;
        $this->originalBeginDts = 0;
        $this->originalEndDts = 0;
        $this->syncPoints = [];
        $this->firstSample = null;
        $this->lastSample = null;
    }

    // also called Random Access Point
    public function appendSyncPoint($sampleInfo)
    {
        $sampleInfo->isSyncPoint = true;
        $this->syncPoints[] = $sampleInfo;
    }
}