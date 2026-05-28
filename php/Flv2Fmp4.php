<?php
/**
 * FLV 转 fMP4 主类
 */
class Flv2Fmp4 {
    private $_config = [];
    
    public $onInitSegment = null;
    public $onMediaSegment = null;
    public $onMediaInfo = null;
    public $seekCallBack = null;
    
    private $loadmetadata = false;
    private $ftyp_moov = null;
    private $metaSuccRun = false;
    private $metas = [];
    private $parseChunk = null;
    private $hasAudio = false;
    private $hasVideo = false;
    
    private $_pendingResolveSeekPoint = -1;
    private $_tempBaseTime = 0;
    
    private $setflvBase = null;
    
    private $flvParse = null;
    private $tagDemux = null;
    private $m4mof = null;
    
    function __construct($config = []) {
        $this->_config = ['_isLive' => false];
        foreach ($config as $key => $value) {
            $this->_config[$key] = $value;
        }
        
        $this->flvParse = new FlvParse();
        $this->tagDemux = new TagDemux();
        $this->m4mof = new MP4Moof($this->_config);
        
        $this->setflvBase = [$this, 'setflvBasefrist'];
        
        $self = $this;
        $this->tagDemux->setOnTrackMetadata(function($type, $meta) use ($self) {
            $self->Metadata($type, $meta);
        });
        
        $this->tagDemux->setOnDataAvailable(function($audioTrack, $videoTrack) use ($self) {
            $self->onDataAvailable($audioTrack, $videoTrack);
        });
        
        $this->m4mof->setOnMediaSegment(function($track, $value) use ($self) {
            $self->onMdiaSegment($track, $value);
        });
    }
    
    function seek($baseTime) {
        $this->setflvBase = [$this, 'setflvBasefrist'];
        
        if ($baseTime == null || $baseTime == 0) {
            $baseTime = 0;
            $this->_pendingResolveSeekPoint = -1;
        }
        
        if ($this->_tempBaseTime != $baseTime) {
            $this->_tempBaseTime = $baseTime;
            $this->tagDemux->setTimestampBase($baseTime);
            $this->m4mof->seek($baseTime);
            $this->m4mof->insertDiscontinuity();
            $this->_pendingResolveSeekPoint = $baseTime;
        }
    }
    
    function setflvBasefrist($arraybuff, $baseTime) {
        echo "[Flv2Fmp4] 首次接收FLV数据\n";
        
        $offset = $this->flvParse->setFlv($arraybuff);
        
        if ($this->flvParse->getTags() && $this->flvParse->getTags()[0]->tagType != 18) {
            echo "[Flv2Fmp4] 警告: 缺少metadata标签\n";
        }
        
        if (count($this->flvParse->getTags()) > 0) {
            $this->tagDemux->setHasAudio($this->hasAudio = $this->flvParse->getHasAudio());
            $this->tagDemux->setHasVideo($this->hasVideo = $this->flvParse->getHasVideo());
            
            echo "[Flv2Fmp4] FLV信息: 音频=" . ($this->hasAudio ? '是' : '否') . ", 视频=" . ($this->hasVideo ? '是' : '否') . "\n";
            
            if ($this->_tempBaseTime != 0 && $this->_tempBaseTime == $this->flvParse->getTags()[0]->getTime()) {
                $this->tagDemux->setTimestampBase(0);
            }
            
            $this->tagDemux->moofTag($this->flvParse->getTags());
            $this->setflvBase = [$this, 'setflvBaseUsually'];
        }
        
        return $offset;
    }
    
    function setflvBaseUsually($arraybuff, $baseTime) {
        $offset = $this->flvParse->setFlv($arraybuff);
        
        if (count($this->flvParse->getTags()) > 0) {
            $this->tagDemux->moofTag($this->flvParse->getTags());
        }
        
        return $offset;
    }
    
    function onMdiaSegment($track, $value) {
        echo "[Flv2Fmp4] 媒体段回调: $track, 数据大小=" . count($value['data']) . "\n";
        
        if ($this->onMediaSegment) {
            call_user_func($this->onMediaSegment, $value['data']);
        }
        
        if ($this->_pendingResolveSeekPoint != -1 && $track == 'video') {
            $seekpoint = $this->_pendingResolveSeekPoint;
            $this->_pendingResolveSeekPoint = -1;
            
            if ($this->seekCallBack) {
                $this->seekCallBack($seekpoint);
            }
        }
    }
    
    function Metadata($type, $meta) {
        echo "[Flv2Fmp4] 元数据回调: $type\n";
        
        switch ($type) {
            case 'video':
                $this->metas[] = $meta;
                $this->m4mof->setVideoMeta($meta);
                
                if ($this->hasVideo && !$this->hasAudio) {
                    $this->metaSucc();
                    return;
                }
                break;
            case 'audio':
                $this->metas[] = $meta;
                $this->m4mof->setAudioMeta($meta);
                
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
    
    function metaSucc($mi = null) {
        if ($this->onMediaInfo) {
            call_user_func($this->onMediaInfo, $mi ?: $this->tagDemux->getMediaInfo(), ['hasAudio' => $this->hasAudio, 'hasVideo' => $this->hasVideo]);
        }
        
        if (count($this->metas) == 0) {
            $this->metaSuccRun = true;
            return;
        }
        
        if ($mi) return;
        
        echo "[Flv2Fmp4] 生成初始化段, 元数据数=" . count($this->metas) . "\n";
        $this->ftyp_moov = MP4::generateInitSegment($this->metas);
        
        echo "[Flv2Fmp4] 初始化段大小=" . count($this->ftyp_moov) . " bytes\n";
        
        if ($this->onInitSegment && !$this->loadmetadata) {
            call_user_func($this->onInitSegment, $this->ftyp_moov);
            $this->loadmetadata = true;
        }
    }
    
    function onDataAvailable($audiotrack, $videotrack) {
        $this->m4mof->remux($audiotrack, $videotrack);
    }
    
    function setflv($arraybuff, $baseTime = 0) {
        echo "[Flv2Fmp4] 输入FLV数据, 大小=" . count($arraybuff) . " bytes\n";
        return call_user_func($this->setflvBase, $arraybuff, $baseTime);
    }
}
