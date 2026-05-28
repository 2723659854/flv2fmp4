<?php

require_once __DIR__ . '/flv/FlvParse.php';
require_once __DIR__ . '/flv/TagDemux.php';
require_once __DIR__ . '/mp4/MP4Remux.php';
require_once __DIR__ . '/mp4/MP4Moof.php';

class Flv2Fmp4 {
    private $_config = [];
    public $onInitSegment = null;
    public $onMediaSegment = null;
    public $onMediaInfo = null;
    public $seekCallBack = null;
    public $loadmetadata = false;
    public $ftyp_moov = null;
    public $metaSuccRun = false;
    public $metas = [];
    public $parseChunk = null;
    public $hasAudio = false;
    public $hasVideo = false;
    private $_pendingResolveSeekPoint = -1;
    private $_tempBaseTime = 0;
    private $_setflvBase = null;
    private $_tagDemux = null;
    private $_m4mof = null;

    public function __construct($config = []) {
        $this->_config = ['_isLive' => false];
        $this->_config = array_merge($this->_config, $config);

        $this->_setflvBase = [$this, 'setflvBasefrist'];

        $this->_tagDemux = new TagDemux();
        $this->_tagDemux->setOnTrackMetadata([$this, 'Metadata']);
        $this->_tagDemux->onMediaInfo([$this, 'metaSucc']);
        $this->_tagDemux->setOnDataAvailable([$this, 'onDataAvailable']);

        $this->_m4mof = new MP4Remuxer($this->_config);
        $this->_m4mof->setOnMediaSegment([$this, 'onMdiaSegment']);
    }

    public function seek($baseTime) {
        $this->_setflvBase = [$this, 'setflvBasefrist'];
        if ($baseTime === null || $baseTime === 0) {
            $baseTime = 0;
            $this->_pendingResolveSeekPoint = -1;
        }
        if ($this->_tempBaseTime != $baseTime) {
            $this->_tempBaseTime = $baseTime;
            $this->_tagDemux->_timestampBase = $baseTime;
            $this->_m4mof->seek($baseTime);
            $this->_m4mof->insertDiscontinuity();
            $this->_pendingResolveSeekPoint = $baseTime;
        }
    }

    public function setflvBasefrist($arraybuff, $baseTime) {
        $flvParse = new FlvParse();
        $offset = $flvParse->setFlv($arraybuff);
        
        if (count($flvParse->arrTag) > 0 && $flvParse->arrTag[0]->tagType != 18) {
            if (isset($this->error)) {
                $this->error(new Exception('without metadata tag'));
            }
        }
        
        if (count($flvParse->arrTag) > 0) {
            $this->_tagDemux->setHasAudio($flvParse->_hasAudio);
            $this->_tagDemux->setHasVideo($flvParse->_hasVideo);
            $this->hasAudio = $flvParse->_hasAudio;
            $this->hasVideo = $flvParse->_hasVideo;

            if ($this->_tempBaseTime != 0 && $this->_tempBaseTime == $flvParse->arrTag[0]->getTime()) {
                $this->_tagDemux->_timestampBase = 0;
            }
            $this->_tagDemux->moofTag($flvParse->arrTag);
            $this->_setflvBase = [$this, 'setflvBaseUsually'];
        }

        return $offset;
    }

    public function setflvBaseUsually($arraybuff, $baseTime) {
        $flvParse = new FlvParse();
        $offset = $flvParse->setFlv($arraybuff);

        if (count($flvParse->arrTag) > 0) {
            $this->_tagDemux->moofTag($flvParse->arrTag);
        }

        return $offset;
    }

    public function onMdiaSegment($track, $value) {
        if ($this->onMediaSegment) {
            ($this->onMediaSegment)($track, $value);
        }
        if ($this->_pendingResolveSeekPoint != -1 && $track == 'video') {
            $seekpoint = $this->_pendingResolveSeekPoint;
            $this->_pendingResolveSeekPoint = -1;
            if ($this->seekCallBack) {
                ($this->seekCallBack)($seekpoint);
            }
        }
    }

    public function Metadata($type, $meta) {
        switch ($type) {
            case 'video':
                $this->metas[] = $meta;
                $this->_m4mof->setVideoMeta($meta);
                if ($this->hasVideo && !$this->hasAudio) {
                    $this->metaSucc();
                    return;
                }
                break;
            case 'audio':
                $this->metas[] = $meta;
                $this->_m4mof->setAudioMeta($meta);
                if (!$this->hasVideo && $this->hasAudio) {
                    $this->metaSucc();
                    return;
                }
                break;
        }
        if ($this->hasVideo && $this->hasAudio && count($this->metas) > 1) {
            $this->metaSucc();
        }
    }

    public function metaSucc($mi = null) {
        if ($this->onMediaInfo) {
            $mediaInfo = $mi ?: $this->_tagDemux->getMediaInfo();
            ($this->onMediaInfo)($mediaInfo, ['hasAudio' => $this->hasAudio, 'hasVideo' => $this->hasVideo]);
        }
        
        if (count($this->metas) == 0) {
            $this->metaSuccRun = true;
            return;
        }
        
        if ($mi) {
            return;
        }
        
        $this->ftyp_moov = MP4::generateInitSegment($this->metas);
        if ($this->onInitSegment && $this->loadmetadata == false) {
            ($this->onInitSegment)($this->ftyp_moov);
            $this->loadmetadata = true;
        }
    }

    public function onDataAvailable($audiotrack, $videotrack) {
        $this->_m4mof->remux($audiotrack, $videotrack);
    }

    public function setflv($arraybuff, $baseTime = 0) {
        return call_user_func($this->_setflvBase, $arraybuff, $baseTime);
    }

    public function setflvloc($arraybuff) {
        $flvParse = new FlvParse();
        $offset = $flvParse->setFlv($arraybuff);

        if (count($flvParse->arrTag) > 0) {
            return $flvParse->arrTag;
        }
        return null;
    }
}

class Flv2Fmp4Foreign {
    private $_f2m = null;
    private $_onInitSegment = null;
    private $_onMediaSegment = null;
    private $_onMediaInfo = null;
    private $_seekCallBack = null;

    public function __construct($config = []) {
        $this->_f2m = new Flv2Fmp4($config);
    }

    public function seek($basetime) {
        $this->_f2m->seek($basetime);
    }

    public function setflv($arraybuff) {
        return $this->_f2m->setflv($arraybuff, 0);
    }

    public function setflvloc($arraybuff) {
        return $this->_f2m->setflvloc($arraybuff);
    }

    public function setOnInitSegment($fun) {
        $this->_onInitSegment = $fun;
        $this->_f2m->onInitSegment = $fun;
    }

    public function setOnMediaSegment($fun) {
        $this->_onMediaSegment = $fun;
        $this->_f2m->onMediaSegment = $fun;
    }

    public function setOnMediaInfo($fun) {
        $this->_onMediaInfo = $fun;
        $this->_f2m->onMediaInfo = $fun;
    }

    public function setSeekCallBack($fun) {
        $this->_seekCallBack = $fun;
        $this->_f2m->seekCallBack = $fun;
    }
}
?>