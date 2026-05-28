<?php

class SampleInfo {
    public $dts = 0;
    public $pts = 0;
    public $duration = 0;
    public $originalDts = 0;
    public $isSyncPoint = false;
    public $fileposition = null;

    public function __construct($dts, $pts, $duration, $originalDts, $isSync) {
        $this->dts = $dts;
        $this->pts = $pts;
        $this->duration = $duration;
        $this->originalDts = $originalDts;
        $this->isSyncPoint = $isSync;
        $this->fileposition = null;
    }
}

class MediaSegmentInfo {
    public $beginDts = 0;
    public $endDts = 0;
    public $beginPts = 0;
    public $endPts = 0;
    public $originalBeginDts = 0;
    public $originalEndDts = 0;
    public $syncPoints = [];
    public $firstSample = null;
    public $lastSample = null;

    public function __construct() {
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

    public function appendSyncPoint($sampleInfo) {
        $sampleInfo->isSyncPoint = true;
        $this->syncPoints[] = $sampleInfo;
    }
}

class IDRSampleList {
    private $_list = [];

    public function __construct() {
        $this->_list = [];
    }

    public function clear() {
        $this->_list = [];
    }

    public function appendArray($syncPoints) {
        $list = $this->_list;

        if (count($syncPoints) === 0) {
            return;
        }

        if (count($list) > 0 && $syncPoints[0]->originalDts < $list[count($list) - 1]->originalDts) {
            $this->clear();
        }

        foreach ($syncPoints as $point) {
            $list[] = $point;
        }
    }

    public function getLastSyncPointBeforeDts($dts) {
        if (count($this->_list) == 0) {
            return null;
        }

        $list = $this->_list;
        $idx = 0;
        $last = count($list) - 1;
        $mid = 0;
        $lbound = 0;
        $ubound = $last;

        if ($dts < $list[0]->dts) {
            $idx = 0;
            $lbound = $ubound + 1;
        }

        while ($lbound <= $ubound) {
            $mid = $lbound + (int)floor(($ubound - $lbound) / 2);
            if ($mid === $last || ($dts >= $list[$mid]->dts && $dts < $list[$mid + 1]->dts)) {
                $idx = $mid;
                break;
            } else if ($list[$mid]->dts < $dts) {
                $lbound = $mid + 1;
            } else {
                $ubound = $mid - 1;
            }
        }
        return $this->_list[$idx];
    }
}

class MediaSegmentInfoList {
    private $_type = '';
    private $_list = [];
    private $_lastAppendLocation = -1;

    public function __construct($type) {
        $this->_type = $type;
        $this->_list = [];
        $this->_lastAppendLocation = -1;
    }

    public function getType() {
        return $this->_type;
    }

    public function getLength() {
        return count($this->_list);
    }

    public function isEmpty() {
        return count($this->_list) === 0;
    }

    public function clear() {
        $this->_list = [];
        $this->_lastAppendLocation = -1;
    }

    private function _searchNearestSegmentBefore($originalBeginDts) {
        $list = $this->_list;
        if (count($list) === 0) {
            return -2;
        }
        $last = count($list) - 1;
        $mid = 0;
        $lbound = 0;
        $ubound = $last;

        $idx = 0;

        if ($originalBeginDts < $list[0]->originalBeginDts) {
            $idx = -1;
            return $idx;
        }

        while ($lbound <= $ubound) {
            $mid = $lbound + (int)floor(($ubound - $lbound) / 2);
            if ($mid === $last || ($originalBeginDts > $list[$mid]->lastSample->originalDts &&
                    ($originalBeginDts < $list[$mid + 1]->originalBeginDts))) {
                $idx = $mid;
                break;
            } else if ($list[$mid]->originalBeginDts < $originalBeginDts) {
                $lbound = $mid + 1;
            } else {
                $ubound = $mid - 1;
            }
        }
        return $idx;
    }

    private function _searchNearestSegmentAfter($originalBeginDts) {
        return $this->_searchNearestSegmentBefore($originalBeginDts) + 1;
    }

    public function append($mediaSegmentInfo) {
        $list = $this->_list;
        $msi = $mediaSegmentInfo;
        $lastAppendIdx = $this->_lastAppendLocation;
        $insertIdx = 0;

        if ($lastAppendIdx !== -1 && $lastAppendIdx < count($list) &&
            $msi->originalBeginDts >= $list[$lastAppendIdx]->lastSample->originalDts &&
            (($lastAppendIdx === count($list) - 1) ||
                ($lastAppendIdx < count($list) - 1 &&
                    $msi->originalBeginDts < $list[$lastAppendIdx + 1]->originalBeginDts))) {
            $insertIdx = $lastAppendIdx + 1;
        } else {
            if (count($list) > 0) {
                $insertIdx = $this->_searchNearestSegmentBefore($msi->originalBeginDts) + 1;
            }
        }

        $this->_lastAppendLocation = $insertIdx;
        array_splice($list, $insertIdx, 0, [$msi]);
        $this->_list = $list;
    }

    public function getLastSegmentBefore($originalBeginDts) {
        $idx = $this->_searchNearestSegmentBefore($originalBeginDts);
        if ($idx >= 0) {
            return $this->_list[$idx];
        } else {
            return null;
        }
    }

    public function getLastSampleBefore($originalBeginDts) {
        $segment = $this->getLastSegmentBefore($originalBeginDts);
        if ($segment != null) {
            return $segment->lastSample;
        } else {
            return null;
        }
    }

    public function getLastSyncPointBefore($originalBeginDts) {
        $segmentIdx = $this->_searchNearestSegmentBefore($originalBeginDts);
        $syncPoints = $this->_list[$segmentIdx]->syncPoints;
        while (count($syncPoints) === 0 && $segmentIdx > 0) {
            $segmentIdx--;
            $syncPoints = $this->_list[$segmentIdx]->syncPoints;
        }
        if (count($syncPoints) > 0) {
            return $syncPoints[count($syncPoints) - 1];
        } else {
            return null;
        }
    }
}
?>