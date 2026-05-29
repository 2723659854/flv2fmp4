<?php

namespace xiaosongshu\flv;
/**
 * 翻译自 flv.js 的 media-info.js
 * 用于存储媒体文件的基本信息。
 *
 * Copyright (C) 2016 Bilibili. All Rights Reserved.
 * @author zheng qian <xqq@xqq.im>
 *
 * Licensed under the Apache License, Version 2.0.
 */
class MediaInfo
{
    public $mimeType = null;
    public $duration = null;

    public $hasAudio = null;
    public $hasVideo = null;
    public $audioCodec = null;
    public $videoCodec = null;
    public $audioDataRate = null;
    public $videoDataRate = null;

    public $audioSampleRate = null;
    public $audioChannelCount = null;

    public $width = null;
    public $height = null;
    public $fps = null;
    public $profile = null;
    public $level = null;
    public $chromaFormat = null;
    public $sarNum = null;
    public $sarDen = null;

    public $metadata = null;
    public $segments = null;      // MediaInfo[]
    public $segmentCount = null;
    public $hasKeyframesIndex = null;
    public $keyframesIndex = null;

    /**
     * 检查是否所有必要的媒体信息都已填写完整
     *
     * @return bool
     */
    public function isComplete()
    {
        $audioInfoComplete = ($this->hasAudio === false) ||
            ($this->hasAudio === true &&
                $this->audioCodec !== null &&
                $this->audioSampleRate !== null &&
                $this->audioChannelCount !== null);

        $videoInfoComplete = ($this->hasVideo === false) ||
            ($this->hasVideo === true &&
                $this->videoCodec !== null &&
                $this->width !== null &&
                $this->height !== null &&
                $this->fps !== null &&
                $this->profile !== null &&
                $this->level !== null &&
                $this->chromaFormat !== null &&
                $this->sarNum !== null &&
                $this->sarDen !== null);

        // keyframesIndex 可能不存在
        return $this->mimeType !== null &&
            $this->duration !== null &&
            $this->metadata !== null &&
            $this->hasKeyframesIndex !== null &&
            $audioInfoComplete &&
            $videoInfoComplete;
    }

    /**
     * 是否支持跳转（存在关键帧索引）
     *
     * @return bool
     */
    public function isSeekable()
    {
        return $this->hasKeyframesIndex === true;
    }
}