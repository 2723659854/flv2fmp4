<?php

require_once 'FlvDemux.php';
require_once 'MediaInfo.php';
require_once 'SPSParser.php';

class TagDemux {
    private $_config = [];
    private $_onError = null;
    private $_onMediaInfo = null;
    private $_onTrackMetadata = null;
    private $_onDataAvailable = null;
    private $_dataOffset = 0;
    private $_firstParse = true;
    private $_dispatch = false;
    private $_hasAudio = false;
    private $_hasVideo = false;
    private $_audioInitialMetadataDispatched = false;
    private $_videoInitialMetadataDispatched = false;
    private $_mediaInfo = null;
    private $_metadata = null;
    private $_audioMetadata = null;
    private $_videoMetadata = null;
    private $_naluLengthSize = 4;
    public $_timestampBase = 0;
    private $_timescale = 1000;
    private $_duration = 0;
    private $_durationOverrided = false;
    private $_referenceFrameRate = [
        'fixed' => true,
        'fps' => 23.976,
        'fps_num' => 23976,
        'fps_den' => 1000
    ];
    private $_videoTrack = null;
    private $_audioTrack = null;
    private $_littleEndian = false;

    public function __construct() {
        $this->_littleEndian = $this->_checkLittleEndian();
        $this->_mediaInfo = new MediaInfo();
        $this->_videoTrack = [
            'type' => 'video',
            'id' => 1,
            'sequenceNumber' => 0,
            'addcoefficient' => 2,
            'samples' => [],
            'length' => 0
        ];
        $this->_audioTrack = [
            'type' => 'audio',
            'id' => 2,
            'sequenceNumber' => 1,
            'addcoefficient' => 2,
            'samples' => [],
            'length' => 0
        ];
    }

    private function _checkLittleEndian() {
        $test = pack('S', 256);
        $bytes = unpack('C*', $test);
        return $bytes[1] === 0;
    }

    public function setHasAudio($s) {
        $this->_mediaInfo->hasAudio = $this->_hasAudio = $s;
    }

    public function getMediaInfo() {
        return $this->_mediaInfo;
    }

    public function setHasVideo($s) {
        $this->_mediaInfo->hasVideo = $this->_hasVideo = $s;
    }

    public function onMediaInfo($callback) {
        $this->_onMediaInfo = $callback;
    }

    public function setOnTrackMetadata($callback) {
        $this->_onTrackMetadata = $callback;
    }

    public function setOnDataAvailable($callback) {
        $this->_onDataAvailable = $callback;
    }

    public function parseMetadata($arr) {
        $data = FlvDemux::parseMetadata($arr);
        $this->_parseScriptData($data);
    }

    private function _parseScriptData($obj) {
        $scriptData = $obj;

        if (isset($scriptData['onMetaData'])) {
            if ($this->_metadata) {
                // Found another onMetaData tag
            }
            $this->_metadata = $scriptData;
            $onMetaData = $this->_metadata['onMetaData'];

            if (isset($onMetaData['hasAudio']) && is_bool($onMetaData['hasAudio'])) {
                $this->_hasAudio = $onMetaData['hasAudio'];
                $this->_mediaInfo->hasAudio = $this->_hasAudio;
            }
            if (isset($onMetaData['hasVideo']) && is_bool($onMetaData['hasVideo'])) {
                $this->_hasVideo = $onMetaData['hasVideo'];
                $this->_mediaInfo->hasVideo = $this->_hasVideo;
            }
            if (isset($onMetaData['audiodatarate']) && is_numeric($onMetaData['audiodatarate'])) {
                $this->_mediaInfo->audioDataRate = $onMetaData['audiodatarate'];
            }
            if (isset($onMetaData['videodatarate']) && is_numeric($onMetaData['videodatarate'])) {
                $this->_mediaInfo->videoDataRate = $onMetaData['videodatarate'];
            }
            if (isset($onMetaData['width']) && is_numeric($onMetaData['width'])) {
                $this->_mediaInfo->width = $onMetaData['width'];
            }
            if (isset($onMetaData['height']) && is_numeric($onMetaData['height'])) {
                $this->_mediaInfo->height = $onMetaData['height'];
            }
            if (isset($onMetaData['duration']) && is_numeric($onMetaData['duration'])) {
                if (!$this->_durationOverrided) {
                    $duration = (int)floor($onMetaData['duration'] * $this->_timescale);
                    $this->_duration = $duration;
                    $this->_mediaInfo->duration = $duration;
                }
            } else {
                $this->_mediaInfo->duration = 0;
            }
            if (isset($onMetaData['framerate']) && is_numeric($onMetaData['framerate'])) {
                $fps_num = (int)floor($onMetaData['framerate'] * 1000);
                if ($fps_num > 0) {
                    $fps = $fps_num / 1000;
                    $this->_referenceFrameRate['fixed'] = true;
                    $this->_referenceFrameRate['fps'] = $fps;
                    $this->_referenceFrameRate['fps_num'] = $fps_num;
                    $this->_referenceFrameRate['fps_den'] = 1000;
                    $this->_mediaInfo->fps = $fps;
                }
            }
            if (isset($onMetaData['keyframes']) && is_array($onMetaData['keyframes'])) {
                $this->_mediaInfo->hasKeyframesIndex = true;
                $keyframes = $onMetaData['keyframes'];
                $keyframes['times'] = $onMetaData['times'];
                $keyframes['filepositions'] = $onMetaData['filepositions'];
                $this->_mediaInfo->keyframesIndex = $this->_parseKeyframesIndex($keyframes);
                $onMetaData['keyframes'] = null;
            } else {
                $this->_mediaInfo->hasKeyframesIndex = false;
            }
            $this->_dispatch = false;
            $this->_mediaInfo->metadata = $onMetaData;
        }
    }

    private function _parseKeyframesIndex($keyframes) {
        $times = [];
        $filepositions = [];

        for ($i = 1; $i < count($keyframes['times']); $i++) {
            $time = $this->_timestampBase + (int)floor($keyframes['times'][$i] * 1000);
            $times[] = $time;
            $filepositions[] = $keyframes['filepositions'][$i];
        }

        return ['times' => $times, 'filepositions' => $filepositions];
    }

    public function moofTag($tags) {
        foreach ($tags as $tag) {
            $this->_dispatch = true;
            $this->parseChunks($tag);
        }
        if ($this->_isInitialMetadataDispatched()) {
            if ($this->_dispatch && ($this->_audioTrack['length'] || $this->_videoTrack['length'])) {
                if ($this->_onDataAvailable) {
                    ($this->_onDataAvailable)($this->_audioTrack, $this->_videoTrack);
                }
            }
        }
    }

    public function parseChunks($flvtag) {
        switch ($flvtag->tagType) {
            case 8:
                $this->_parseAudioData($flvtag->body, 0, count($flvtag->body), $flvtag->getTime());
                break;
            case 9:
                $this->_parseVideoData($flvtag->body, 0, count($flvtag->body), $flvtag->getTime(), 0);
                break;
            case 18:
                $this->parseMetadata($flvtag->body);
                break;
        }
    }

    private function _parseVideoData($arrayBuffer, $dataOffset, $dataSize, $tagTimestamp, $tagPosition) {
        if ($dataSize <= 1) {
            return;
        }

        $spec = $arrayBuffer[$dataOffset];
        $frameType = ($spec & 240) >> 4;
        $codecId = $spec & 15;

        if ($codecId !== 7) {
            return;
        }

        $this->_parseAVCVideoPacket($arrayBuffer, $dataOffset + 1, $dataSize - 1, $tagTimestamp, $tagPosition, $frameType);
    }

    private function _parseAVCVideoPacket($arrayBuffer, $dataOffset, $dataSize, $tagTimestamp, $tagPosition, $frameType) {
        if ($dataSize < 4) {
            return;
        }

        $le = $this->_littleEndian;
        $packetType = $arrayBuffer[$dataOffset];
        $cts = $this->_readUint32BE($arrayBuffer, $dataOffset) & 0x00FFFFFF;

        if ($packetType === 0) {
            $this->_parseAVCDecoderConfigurationRecord($arrayBuffer, $dataOffset + 4, $dataSize - 4);
        } else if ($packetType === 1) {
            $this->_parseAVCVideoData($arrayBuffer, $dataOffset + 4, $dataSize - 4, $tagTimestamp, $tagPosition, $frameType, $cts);
        } else if ($packetType === 2) {
            // AVC end of sequence - empty
        }
    }

    private function _parseAVCDecoderConfigurationRecord($arrayBuffer, $dataOffset, $dataSize) {
        if ($dataSize < 7) {
            return;
        }

        $meta = $this->_videoMetadata;
        $track = $this->_videoTrack;
        $le = $this->_littleEndian;

        if (!$meta) {
            $meta = $this->_videoMetadata = [];
            $meta['type'] = 'video';
            $meta['id'] = $track['id'];
            $meta['timescale'] = $this->_timescale;
            $meta['duration'] = $this->_duration;
        }

        $version = $arrayBuffer[$dataOffset];
        $avcProfile = $arrayBuffer[$dataOffset + 1];
        $profileCompatibility = $arrayBuffer[$dataOffset + 2];
        $avcLevel = $arrayBuffer[$dataOffset + 3];

        if ($version !== 1 || $avcProfile === 0) {
            return;
        }

        $this->_naluLengthSize = ($arrayBuffer[$dataOffset + 4] & 3) + 1;
        if ($this->_naluLengthSize !== 3 && $this->_naluLengthSize !== 4) {
            return;
        }

        $spsCount = $arrayBuffer[$dataOffset + 5] & 31;
        if ($spsCount === 0 || $spsCount > 1) {
            return;
        }

        $offset = 6;

        for ($i = 0; $i < $spsCount; $i++) {
            $len = $this->_readUint16BE($arrayBuffer, $dataOffset + $offset);
            $offset += 2;

            if ($len === 0) {
                continue;
            }

            $sps = array_slice($arrayBuffer, $dataOffset + $offset, $len);
            $offset += $len;

            $config = SPSParser::parseSPS($sps);
            $meta['codecWidth'] = $config['codec_size']['width'];
            $meta['codecHeight'] = $config['codec_size']['height'];
            $meta['presentWidth'] = $config['present_size']['width'];
            $meta['presentHeight'] = $config['present_size']['height'];

            $meta['profile'] = $config['profile_string'];
            $meta['level'] = $config['level_string'];
            $meta['bitDepth'] = $config['bit_depth'];
            $meta['chromaFormat'] = $config['chroma_format'];
            $meta['sarRatio'] = $config['sar_ratio'];
            $meta['frameRate'] = $config['frame_rate'];

            if (!$config['frame_rate']['fixed'] ||
                $config['frame_rate']['fps_num'] === 0 ||
                $config['frame_rate']['fps_den'] === 0) {
                $meta['frameRate'] = $this->_referenceFrameRate;
            }

            $fps_den = $meta['frameRate']['fps_den'];
            $fps_num = $meta['frameRate']['fps_num'];
            $meta['refSampleDuration'] = (int)floor($meta['timescale'] * ($fps_den / $fps_num));

            $codecArray = array_slice($sps, 1, 3);
            $codecString = 'avc1.';
            foreach ($codecArray as $byte) {
                $h = dechex($byte);
                if (strlen($h) < 2) {
                    $h = '0' . $h;
                }
                $codecString .= $h;
            }
            $meta['codec'] = $codecString;

            $mi = $this->_mediaInfo;
            $mi->width = $meta['codecWidth'];
            $mi->height = $meta['codecHeight'];
            $mi->fps = $meta['frameRate']['fps'];
            $mi->profile = $meta['profile'];
            $mi->level = $meta['level'];
            $mi->chromaFormat = $config['chroma_format_string'];
            $mi->sarNum = $meta['sarRatio']['width'];
            $mi->sarDen = $meta['sarRatio']['height'];
            $mi->videoCodec = $codecString;

            if ($mi->hasAudio) {
                if ($mi->audioCodec != null) {
                    $mi->mimeType = 'video/x-flv; codecs="' . $mi->videoCodec . ',' . $mi->audioCodec . '"';
                }
            } else {
                $mi->mimeType = 'video/x-flv; codecs="' . $mi->videoCodec . '"';
            }
            if ($mi->isComplete()) {
                if ($this->_onMediaInfo) {
                    ($this->_onMediaInfo)($mi);
                }
            }
        }

        $ppsCount = $arrayBuffer[$dataOffset + $offset];
        if ($ppsCount === 0 || $ppsCount > 1) {
            return;
        }

        $offset++;

        for ($i = 0; $i < $ppsCount; $i++) {
            $len = $this->_readUint16BE($arrayBuffer, $dataOffset + $offset);
            $offset += 2;

            if ($len === 0) {
                continue;
            }

            $offset += $len;
        }

        $meta['avcc'] = array_slice($arrayBuffer, $dataOffset, $dataSize);

        if ($this->_isInitialMetadataDispatched()) {
            if ($this->_dispatch && ($this->_audioTrack['length'] || $this->_videoTrack['length'])) {
                if ($this->_onDataAvailable) {
                    ($this->_onDataAvailable)($this->_audioTrack, $this->_videoTrack);
                }
            }
        } else {
            $this->_videoInitialMetadataDispatched = true;
        }

        $this->_dispatch = false;
        if ($this->_onTrackMetadata) {
            ($this->_onTrackMetadata)('video', $meta);
        }
    }

    public function timestampBase($i) {
        $this->_timestampBase = $i;
    }

    private function _parseAVCVideoData($arrayBuffer, $dataOffset, $dataSize, $tagTimestamp, $tagPosition, $frameType, $cts) {
        $le = $this->_littleEndian;

        $units = [];
        $length = 0;
        $offset = 0;
        $lengthSize = $this->_naluLengthSize;
        $dts = $this->_timestampBase + $tagTimestamp;
        $keyframe = ($frameType === 1);

        while ($offset < $dataSize) {
            if ($offset + 4 >= $dataSize) {
                break;
            }

            $naluSize = $this->_readUint32BE($arrayBuffer, $dataOffset + $offset);
            if ($lengthSize === 3) {
                $naluSize >>= 8;
            }
            if ($naluSize > $dataSize - $lengthSize) {
                return;
            }

            $unitType = $arrayBuffer[$dataOffset + $offset + $lengthSize] & 0x1F;

            if ($unitType === 5) {
                $keyframe = true;
            }

            $data = array_slice($arrayBuffer, $dataOffset + $offset + $lengthSize, $naluSize);
            $unit = ['type' => $unitType, 'data' => $data];
            $units[] = $unit;
            $length += count($data);

            $offset += $lengthSize + $naluSize;
        }

        if (count($units)) {
            $avcSample = [
                'units' => $units,
                'length' => $length,
                'isKeyframe' => $keyframe,
                'dts' => $dts,
                'cts' => $cts,
                'pts' => $dts + $cts
            ];
            if ($keyframe) {
                $avcSample['fileposition'] = $tagPosition;
            }
            $this->_videoTrack['samples'][] = $avcSample;
            $this->_videoTrack['length'] += $length;
            $this->_dispatch = true;
        }
    }

    private function _parseAudioData($arrayBuffer, $dataOffset, $dataSize, $tagTimestamp) {
        if ($dataSize <= 1) {
            return;
        }

        $meta = $this->_audioMetadata;
        $track = $this->_audioTrack;

        if (!$meta || !isset($meta['codec'])) {
            $meta = $this->_audioMetadata = [];
            $meta['type'] = 'audio';
            $meta['id'] = $track['id'];
            $meta['timescale'] = $this->_timescale;
            $meta['duration'] = $this->_duration;

            $soundSpec = $arrayBuffer[$dataOffset];
            $soundFormat = $soundSpec >> 4;

            if ($soundFormat !== 10) {
                return;
            }

            $soundRateIndex = ($soundSpec & 12) >> 2;
            $soundRateTable = [5500, 11025, 22050, 44100, 48000];

            if ($soundRateIndex >= count($soundRateTable)) {
                return;
            }
            $soundRate = $soundRateTable[$soundRateIndex];

            $soundType = $soundSpec & 1;

            $meta['audioSampleRate'] = $soundRate;
            $meta['channelCount'] = ($soundType === 0 ? 1 : 2);
            $meta['refSampleDuration'] = (int)floor(1024 / $meta['audioSampleRate'] * $meta['timescale']);
            $meta['codec'] = 'mp4a.40.5';
        }

        $aacData = $this->_parseAACAudioData($arrayBuffer, $dataOffset + 1, $dataSize - 1);
        if ($aacData === null) {
            return;
        }

        if ($aacData['packetType'] === 0) {
            if (isset($meta['config'])) {
                // Found another AudioSpecificConfig
            }
            $misc = $aacData['data'];
            $meta['audioSampleRate'] = $misc['samplingRate'];
            $meta['channelCount'] = $misc['channelCount'];
            $meta['codec'] = $misc['codec'];
            $meta['config'] = $misc['config'];
            $meta['refSampleDuration'] = (int)floor(1024 / $meta['audioSampleRate'] * $meta['timescale']);

            if ($this->_isInitialMetadataDispatched()) {
                if ($this->_dispatch && ($this->_audioTrack['length'] || $this->_videoTrack['length'])) {
                    if ($this->_onDataAvailable) {
                        ($this->_onDataAvailable)($this->_audioTrack, $this->_videoTrack);
                    }
                }
            } else {
                $this->_audioInitialMetadataDispatched = true;
            }

            $this->_dispatch = false;
            if ($this->_onTrackMetadata) {
                ($this->_onTrackMetadata)('audio', $meta);
            }

            $mi = $this->_mediaInfo;
            $mi->audioCodec = 'mp4a.40.' . $misc['originalAudioObjectType'];
            $mi->audioSampleRate = $meta['audioSampleRate'];
            $mi->audioChannelCount = $meta['channelCount'];
            if ($mi->hasVideo) {
                if ($mi->videoCodec != null) {
                    $mi->mimeType = 'video/x-flv; codecs="' . $mi->videoCodec . ',' . $mi->audioCodec . '"';
                }
            } else {
                $mi->mimeType = 'video/x-flv; codecs="' . $mi->audioCodec . '"';
            }
            if ($mi->isComplete()) {
                if ($this->_onDataAvailable) {
                    ($this->_onDataAvailable)($this->_audioTrack, $this->_videoTrack);
                }
            }
            return;
        } else if ($aacData['packetType'] === 1) {
            $dts = $this->_timestampBase + $tagTimestamp;
            $aacSample = ['unit' => $aacData['data'], 'dts' => $dts, 'pts' => $dts];
            $this->_audioTrack['samples'][] = $aacSample;
            $this->_audioTrack['length'] += count($aacData['data']);
            $this->_dispatch = true;
        }
    }

    private function _parseAACAudioData($arrayBuffer, $dataOffset, $dataSize) {
        if ($dataSize <= 1) {
            return null;
        }

        $result = [];
        $packetType = $arrayBuffer[$dataOffset];

        if ($packetType === 0) {
            $result['data'] = $this->_parseAACAudioSpecificConfig($arrayBuffer, $dataOffset + 1, $dataSize - 1);
        } else {
            $result['data'] = array_slice($arrayBuffer, $dataOffset + 1);
        }

        $result['packetType'] = $packetType;
        return $result;
    }

    private function _parseAACAudioSpecificConfig($arrayBuffer, $dataOffset, $dataSize) {
        $array = array_slice($arrayBuffer, $dataOffset, $dataSize);
        $config = null;

        $mpegSamplingRates = [
            96000, 88200, 64000, 48000, 44100, 32000,
            24000, 22050, 16000, 12000, 11025, 8000, 7350
        ];

        $audioObjectType = 0;
        $originalAudioObjectType = 0;
        $audioExtensionObjectType = null;
        $samplingIndex = 0;
        $extensionSamplingIndex = null;

        $audioObjectType = $originalAudioObjectType = $array[0] >> 3;
        $samplingIndex = (($array[0] & 0x07) << 1) | ($array[1] >> 7);

        if ($samplingIndex < 0 || $samplingIndex >= count($mpegSamplingRates)) {
            return null;
        }

        $samplingFrequence = $mpegSamplingRates[$samplingIndex];

        $channelConfig = ($array[1] & 0x78) >> 3;
        if ($channelConfig < 0 || $channelConfig >= 8) {
            return null;
        }

        if ($audioObjectType === 5) {
            $extensionSamplingIndex = (($array[1] & 0x07) << 1) | ($array[2] >> 7);
            $audioExtensionObjectType = ($array[2] & 0x7C) >> 2;
        }

        $audioObjectType = 2;
        $config = array_fill(0, 2, 0);
        $extensionSamplingIndex = $samplingIndex;

        $config[0] = $audioObjectType << 3;
        $config[0] |= ($samplingIndex & 0x0F) >> 1;
        $config[1] = ($samplingIndex & 0x0F) << 7;
        $config[1] |= ($channelConfig & 0x0F) << 3;

        return [
            'config' => $config,
            'samplingRate' => $samplingFrequence,
            'channelCount' => $channelConfig,
            'codec' => 'mp4a.40.' . $audioObjectType,
            'originalAudioObjectType' => $originalAudioObjectType
        ];
    }

    private function _isInitialMetadataDispatched() {
        if ($this->_hasAudio && $this->_hasVideo) {
            return $this->_audioInitialMetadataDispatched && $this->_videoInitialMetadataDispatched;
        }
        if ($this->_hasAudio && !$this->_hasVideo) {
            return $this->_audioInitialMetadataDispatched;
        }
        if (!$this->_hasAudio && $this->_hasVideo) {
            return $this->_videoInitialMetadataDispatched;
        }
        return false;
    }

    private function _readUint32BE($buffer, $offset) {
        return ($buffer[$offset] << 24) | 
               ($buffer[$offset + 1] << 16) | 
               ($buffer[$offset + 2] << 8) | 
               $buffer[$offset + 3];
    }

    private function _readUint16BE($buffer, $offset) {
        return ($buffer[$offset] << 8) | $buffer[$offset + 1];
    }
}
?>