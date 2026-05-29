<?php

namespace xiaosongshu;

use xiaosongshu\flv\FlvParse;
use xiaosongshu\mp4\MP4;
use xiaosongshu\mp4\MP4Remuxer;

class Flv2Fmp4
{
    public $_config;
    public $onInitSegment = null;
    public $onMediaSegment = null;
    public $onMediaInfo = null;
    public $seekCallBack = null;
    public $error = null; // 可选错误回调

    public $loadmetadata = false;
    public $ftyp_moov = null;
    public $metaSuccRun = false;
    public $metas = [];
    public $parseChunk = null;
    public $hasVideo = false;
    public $hasAudio = false;

    public $_pendingResolveSeekPoint = -1;
    public $_tempBaseTime = 0;

    // 动态方法指针（存储可调用对象）
    public $setflvBase;

    public $m4mof;

    /**
     * @param array $config 配置数组，例如 ['_isLive' => true]
     */
    public function __construct($config = [])
    {
        $this->_config = ['_isLive' => false];
        $this->_config = array_merge($this->_config, $config);

        // 外部方法赋值（由调用者设置）
        // $this->onInitSegment = null;
        // $this->onMediaSegment = null;
        // $this->onMediaInfo = null;
        // $this->seekCallBack = null;

        $this->loadmetadata = false;
        $this->ftyp_moov = null;
        $this->metaSuccRun = false;
        $this->metas = [];
        $this->parseChunk = null;
        $this->hasVideo = false;
        $this->hasAudio = false;
        $this->_pendingResolveSeekPoint = -1;
        $this->_tempBaseTime = 0;

        // 处理flv数据入口（动态方法）
        $this->setflvBase = [$this, 'setflvBasefrist'];

        global $tagDemux;
        // 注意：假设 $tagDemux 中的属性是 public 的，直接赋值回调
        $tagDemux->_onTrackMetadata = [$this, 'Metadata'];
        $tagDemux->_onMediaInfo = [$this, 'metaSucc'];
        $tagDemux->_onDataAvailable = [$this, 'onDataAvailable'];

        $this->m4mof = new MP4Remuxer($this->_config);
        $this->m4mof->setOnMediaSegment([$this, 'onMdiaSegment']);
    }

    /**
     * 跳转
     * @param int $baseTime 跳转时间（毫秒）
     */
    public function seek($baseTime = null)
    {
        $this->setflvBase = [$this, 'setflvBasefrist'];
        if ($baseTime === null || $baseTime == 0) {
            $baseTime = 0;
            $this->_pendingResolveSeekPoint = -1;
        }
        if ($this->_tempBaseTime != $baseTime) {
            $this->_tempBaseTime = $baseTime;
            global $tagDemux;
            $tagDemux->timestampBase($baseTime);
            $this->m4mof->seek($baseTime);
            $this->m4mof->insertDiscontinuity();
            $this->_pendingResolveSeekPoint = $baseTime;
        }
    }

    /**
     * 第一次接受数据，和 seek 时候接受数据入口。
     * 不要主动调用，由 setflvBase 动态调用。
     *
     * @param string $arraybuff 二进制数据
     * @param int $baseTime 基础时间
     * @return int 偏移量
     */
    public function setflvBasefrist($arraybuff, $baseTime)
    {
        // 注意：flvparse::setFlv 期望参数是二进制字符串，返回解析后的偏移量
        // 并且填充 flvparse::$arrTag 静态属性（或全局变量）
        $offset = FlvParse::setFlv($arraybuff); // 假设静态方法

        // 假设 flvparse::$arrTag 是数组，每个元素是对象，有 type 属性（18表示script）
        if (isset(FlvParse::$arrTag[0]) && FlvParse::$arrTag[0]->type != 18) {
            if ($this->error) {
                call_user_func($this->error, new \Exception('without metadata tag'));
            }
        }
        if (count(FlvParse::$arrTag) > 0) {
            global $tagDemux;
            $this->hasAudio = FlvParse::$_hasAudio;   // 假设静态属性
            $this->hasVideo = FlvParse::$_hasVideo;
            $tagDemux->setHasAudio($this->hasAudio);
            $tagDemux->setHasVideo($this->hasVideo);

            if ($this->_tempBaseTime != 0 && $this->_tempBaseTime == FlvParse::$arrTag[0]->getTime()) {
                $tagDemux->timestampBase(0);
            }
            $tagDemux->moofTag(FlvParse::$arrTag);
            $this->setflvBase = [$this, 'setflvBaseUsually'];
        }
        return $offset;
    }

    /**
     * 后续接受数据接口。
     * 不要主动调用，由 setflvBase 动态调用。
     *
     * @param string $arraybuff
     * @param int $baseTime
     * @return int
     */
    public function setflvBaseUsually($arraybuff, $baseTime)
    {
        $offset = FlvParse::setFlv($arraybuff);
        if (count(FlvParse::$arrTag) > 0) {
            global $tagDemux;
            $tagDemux->moofTag(FlvParse::$arrTag);
        }
        return $offset;
    }

    /**
     * moof 回调，由 m4mof 触发
     *
     * @param string $track 轨道类型 'audio' 或 'video'
     * @param array $value 包含 'data' 等字段
     */
    public function onMdiaSegment($track, $value)
    {
        if ($this->onMediaSegment) {
            call_user_func($this->onMediaSegment, $value['data']); // 原 JS 传入 new Uint8Array(value.data)
        }
        if ($this->_pendingResolveSeekPoint != -1 && $track == 'video') {
            $seekpoint = $this->_pendingResolveSeekPoint;
            $this->_pendingResolveSeekPoint = -1;
            if ($this->seekCallBack) {
                call_user_func($this->seekCallBack, $seekpoint);
            }
        }
    }

    /**
     * 音视频轨道元数据回调
     *
     * @param string $type 'video' 或 'audio'
     * @param array $meta 元数据
     */
    public function Metadata($type, $meta)
    {
        switch ($type) {
            case 'video':
                $this->metas[] = $meta;
                $this->m4mof->_videoMeta = $meta;
                if ($this->hasVideo && !$this->hasAudio) {
                    $this->metaSucc();
                    return;
                }
                break;
            case 'audio':
                $this->metas[] = $meta;
                $this->m4mof->_audioMeta = $meta;
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

    /**
     * metadata 解读成功后触发，生成初始化片段
     *
     * @param mixed $mi 可选的 mediaInfo
     */
    public function metaSucc($mi = null)
    {
        if ($this->onMediaInfo) {
            global $tagDemux;
            call_user_func($this->onMediaInfo, $mi ?: $tagDemux->_mediaInfo, ['hasAudio' => $this->hasAudio, 'hasVideo' => $this->hasVideo]);
        }
        // 获取 ftyp 和 moov
        if (count($this->metas) == 0) {
            $this->metaSuccRun = true;
            return;
        }
        if ($mi !== null) {
            return;
        }
        $this->ftyp_moov = MP4::generateInitSegment($this->metas);
        if ($this->onInitSegment && $this->loadmetadata == false) {
            call_user_func($this->onInitSegment, $this->ftyp_moov);
            $this->loadmetadata = true;
        }
    }

    /**
     * 从 tagdemux 获得音视频样本数据
     *
     * @param array $audiotrack
     * @param array $videotrack
     */
    public function onDataAvailable($audiotrack, $videotrack)
    {
        $this->m4mof->remux($audiotrack, $videotrack);
    }

    /**
     * 传入 FLV 二进制数据的统一入口
     *
     * @param string $arraybuff 二进制数据
     * @param int $baseTime FLV 数据开始时间（毫秒）
     * @return int 偏移量
     */
    public function setflv($arraybuff, $baseTime)
    {
        return call_user_func($this->setflvBase, $arraybuff, $baseTime);
    }

    /**
     * 本地调试代码，返回解析后的 tag 数组
     *
     * @param string $arraybuff
     * @return array
     */
    public function setflvloc($arraybuff)
    {
        $offset = FlvParse::setFlv($arraybuff);
        if (count(FlvParse::$arrTag) > 0) {
            return FlvParse::$arrTag;
        }
        return [];
    }
}
