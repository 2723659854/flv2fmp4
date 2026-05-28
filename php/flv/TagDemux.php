<?php
/**
 * FLV Tag 解析器
 */
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
    
    private $_mediaInfo = [];
    private $_metadata = null;
    private $_audioMetadata = null;
    private $_videoMetadata = null;
    
    private $_naluLengthSize = 4;
    private $_timestampBase = 0;
    private $_timescale = 1000;
    private $_duration = 0;
    private $_durationOverrided = false;
    
    private $_videoTrack = ['type' => 'video', 'id' => 1, 'sequenceNumber' => 0, 'addcoefficient' => 2, 'samples' => [], 'length' => 0];
    private $_audioTrack = ['type' => 'audio', 'id' => 2, 'sequenceNumber' => 1, 'addcoefficient' => 2, 'samples' => [], 'length' => 0];
    
    function __construct() {
        $this->_mediaInfo = [
            'hasAudio' => false,
            'hasVideo' => false,
            'audioDataRate' => 0,
            'videoDataRate' => 0,
            'width' => 0,
            'height' => 0,
            'duration' => 0,
            'fps' => 23.976,
            'hasKeyframesIndex' => false,
            'keyframesIndex' => null,
            'metadata' => null,
            'profile' => '',
            'level' => '',
            'chromaFormat' => '',
            'sarNum' => 0,
            'sarDen' => 0,
            'videoCodec' => '',
            'audioCodec' => '',
            'audioSampleRate' => 0,
            'audioChannelCount' => 0,
            'mimeType' => ''
        ];
    }
    
    function setHasAudio($s) {
        $this->_mediaInfo['hasAudio'] = $this->_hasAudio = $s;
    }
    
    function setHasVideo($s) {
        $this->_mediaInfo['hasVideo'] = $this->_hasVideo = $s;
    }
    
    function parseMetadata($arr) {
        $data = $this->_parseScriptData($arr);
        if ($data) {
            $this->_mediaInfo = $data;
        }
        echo "[TagDemux] 解析Metadata完成\n";
    }
    
    private function _parseScriptData($obj) {
        if (isset($obj['onMetaData'])) {
            if ($this->_metadata) {
                echo "[TagDemux] 发现另一个onMetaData标签\n";
            }
            $this->_metadata = $obj;
            $onMetaData = $obj['onMetaData'];
            
            if (isset($onMetaData['hasAudio'])) {
                $this->_hasAudio = $onMetaData['hasAudio'];
                $this->_mediaInfo['hasAudio'] = $this->_hasAudio;
            }
            if (isset($onMetaData['hasVideo'])) {
                $this->_hasVideo = $onMetaData['hasVideo'];
                $this->_mediaInfo['hasVideo'] = $this->_hasVideo;
            }
            if (isset($onMetaData['width'])) {
                $this->_mediaInfo['width'] = $onMetaData['width'];
            }
            if (isset($onMetaData['height'])) {
                $this->_mediaInfo['height'] = $onMetaData['height'];
            }
            if (isset($onMetaData['duration'])) {
                if (!$this->_durationOverrided) {
                    $duration = floor($onMetaData['duration'] * $this->_timescale);
                    $this->_duration = $duration;
                    $this->_mediaInfo['duration'] = $duration;
                }
            }
            
            $this->_dispatch = false;
            $this->_mediaInfo['metadata'] = $onMetaData;
            echo "[TagDemux] 解析onMetaData完成\n";
            return $this->_mediaInfo;
        }
        return null;
    }
    
    /**
     * 传入tags输出moof和mdat
     */
    function moofTag($tags) {
        echo "[TagDemux] moofTag 开始处理 " . count($tags) . " 个标签\n";
        
        foreach ($tags as $tag) {
            $this->_dispatch = true;
            $this->parseChunks($tag);
        }
        
        echo "[TagDemux] moofTag 处理完成, _isInitialMetadataDispatched=" . ($this->_isInitialMetadataDispatched() ? 'true' : 'false') . "\n";
        echo "[TagDemux] _dispatch=" . ($this->_dispatch ? 'true' : 'false') . ", audioTrack.length=" . $this->_audioTrack['length'] . ", videoTrack.length=" . $this->_videoTrack['length'] . "\n";
        echo "[TagDemux] _audioInitialMetadataDispatched=" . ($this->_audioInitialMetadataDispatched ? 'true' : 'false') . ", _videoInitialMetadataDispatched=" . ($this->_videoInitialMetadataDispatched ? 'true' : 'false') . "\n";
        
        if ($this->_isInitialMetadataDispatched()) {
            if ($this->_dispatch && ($this->_audioTrack['length'] || $this->_videoTrack['length'])) {
                echo "[TagDemux] 调用 _onDataAvailable\n";
                if ($this->_onDataAvailable) {
                    call_user_func($this->_onDataAvailable, $this->_audioTrack, $this->_videoTrack);
                }
                $this->_audioTrack['samples'] = [];
                $this->_audioTrack['length'] = 0;
                $this->_videoTrack['samples'] = [];
                $this->_videoTrack['length'] = 0;
            }
        }
    }
    
    function parseChunks($flvtag) {
        switch ($flvtag->tagType) {
            case 8: // Audio
                echo "[TagDemux] 解析音频标签, 时间戳=" . $flvtag->getTime() . "\n";
                $this->_parseAudioData($flvtag->body, 0, count($flvtag->body), $flvtag->getTime());
                break;
            case 9: // Video
                echo "[TagDemux] 解析视频标签, 时间戳=" . $flvtag->getTime() . "\n";
                $this->_parseVideoData($flvtag->body, 0, count($flvtag->body), $flvtag->getTime(), 0);
                break;
            case 18: // ScriptDataObject
                echo "[TagDemux] 解析脚本数据标签\n";
                $this->parseMetadata($flvtag->body);
                break;
        }
    }
    
    private function _parseVideoData($arrayBuffer, $dataOffset, $dataSize, $tagTimestamp, $tagPosition) {
        if ($dataSize <= 1) {
            echo "[TagDemux] 无效的视频数据包\n";
            return;
        }

        $spec = $arrayBuffer[$dataOffset];
        $frameType = ($spec & 240) >> 4;
        $codecId = $spec & 15;

        if ($codecId !== 7) {
            echo "[TagDemux] 不支持的视频编码: $codecId\n";
            return;
        }

        echo "[TagDemux] _parseVideoData: dataSize=$dataSize, frameType=$frameType, codecId=$codecId\n";
        $this->_parseAVCVideoPacket($arrayBuffer, $dataOffset + 1, $dataSize - 1, $tagTimestamp, $tagPosition, $frameType);
    }
    
    private function _parseAVCVideoPacket($arrayBuffer, $dataOffset, $dataSize, $tagTimestamp, $tagPosition, $frameType) {
        if ($dataSize < 4) {
            echo "[TagDemux] 无效的AVC数据包\n";
            return;
        }

        $packetType = $arrayBuffer[$dataOffset];
        $cts = ($arrayBuffer[$dataOffset + 1] << 16) | ($arrayBuffer[$dataOffset + 2] << 8) | $arrayBuffer[$dataOffset + 3];
        if ($cts & 0x800000) {
            $cts |= 0xFF000000;
        }

        if ($packetType === 0) {
            echo "[TagDemux] 解析AVCDecoderConfigurationRecord\n";
            $this->_parseAVCDecoderConfigurationRecord($arrayBuffer, $dataOffset + 4, $dataSize - 4);
        } else if ($packetType === 1) {
            $this->_parseAVCVideoData($arrayBuffer, $dataOffset + 4, $dataSize - 4, $tagTimestamp, $tagPosition, $frameType, $cts);
        } else if ($packetType === 2) {
            // AVC end of sequence
        }
    }
    
    private function _parseAVCDecoderConfigurationRecord($arrayBuffer, $dataOffset, $dataSize) {
        echo "[TagDemux] _parseAVCDecoderConfigurationRecord called: dataOffset=$dataOffset, dataSize=$dataSize\n";
        if ($dataSize < 7) {
            echo "[TagDemux] 无效的AVCDecoderConfigurationRecord: dataSize=$dataSize < 7\n";
            return;
        }
        
        $meta = $this->_videoMetadata;
        $track = $this->_videoTrack;
        
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

        echo "[TagDemux] AVCDecoderConfigurationRecord: version=$version, avcProfile=$avcProfile, spsCount检查中...\n";

        if ($version !== 1 || $avcProfile === 0) {
            echo "[TagDemux] 无效的AVCDecoderConfigurationRecord: version=$version, avcProfile=$avcProfile\n";
            return;
        }

        $this->_naluLengthSize = ($arrayBuffer[$dataOffset + 4] & 3) + 1;

        $spsCount = $arrayBuffer[$dataOffset + 5] & 31;
        echo "[TagDemux] SPS数量: $spsCount\n";
        if ($spsCount === 0 || $spsCount > 1) {
            echo "[TagDemux] 无效的H264 SPS数量: $spsCount\n";
            return;
        }

        $offset = $dataOffset + 6;

        for ($i = 0; $i < $spsCount; $i++) {
            $len = ($arrayBuffer[$offset] << 8) | $arrayBuffer[$offset + 1];
            $offset += 2;

            echo "[TagDemux] SPS长度: $len\n";

            if ($len === 0) {
                echo "[TagDemux] SPS长度为0，跳过\n";
                continue;
            }

            $sps = array_slice($arrayBuffer, $offset, $len);
            $offset += $len;

            echo "[TagDemux] SPS数组长度: " . count($sps) . "\n";
            
            $config = $this->_parseSPS($sps);
            $meta['codecWidth'] = $config['codec_size']['width'];
            $meta['codecHeight'] = $config['codec_size']['height'];
            $meta['presentWidth'] = $config['present_size']['width'];
            $meta['presentHeight'] = $config['present_size']['height'];
            
            $meta['profile'] = $config['profile_string'];
            $meta['level'] = $config['level_string'];
            $meta['frameRate'] = $config['frame_rate'];
            
            $fps_den = $meta['frameRate']['fps_den'];
            $fps_num = $meta['frameRate']['fps_num'];
            $meta['refSampleDuration'] = floor($meta['timescale'] * ($fps_den / $fps_num));
            
            $codecArray = array_slice($sps, 1, 3);
            $codecString = 'avc1.';
            foreach ($codecArray as $byte) {
                $h = dechex($byte);
                if (strlen($h) < 2) $h = '0' . $h;
                $codecString .= $h;
            }
            $meta['codec'] = $codecString;
            
            $this->_mediaInfo['width'] = $meta['codecWidth'];
            $this->_mediaInfo['height'] = $meta['codecHeight'];
            $this->_mediaInfo['fps'] = $meta['frameRate']['fps'];
            $this->_mediaInfo['profile'] = $meta['profile'];
            $this->_mediaInfo['level'] = $meta['level'];
            $this->_mediaInfo['videoCodec'] = $codecString;
        }
        
        $ppsCount = $arrayBuffer[$offset];
        $offset++;
        
        for ($i = 0; $i < $ppsCount; $i++) {
            $len = ($arrayBuffer[$offset] << 8) | $arrayBuffer[$offset + 1];
            $offset += 2;
            if ($len === 0) continue;
            $offset += $len;
        }

        $meta['avcc'] = array_slice($arrayBuffer, $dataOffset, $dataSize);
        echo "[TagDemux] 解析AVCDecoderConfigurationRecord完成, avcc大小=" . count($meta['avcc']) . "\n";
        
        $this->_videoInitialMetadataDispatched = true;
        if ($this->_onTrackMetadata) {
            call_user_func($this->_onTrackMetadata, 'video', $meta);
        }
    }
    
    private function _parseSPS($sps) {
        $pos = 0;

        if (count($sps) < 4) {
            echo "[TagDemux] SPS数据太短: " . count($sps) . " bytes\n";
            return ['codec_size' => ['width' => 0, 'height' => 0], 'present_size' => ['width' => 0, 'height' => 0]];
        }

        $pos++;

        $profileIdc = $sps[$pos++];
        $constraintSet0 = ($sps[$pos] & 0x80) >> 7;
        $constraintSet1 = ($sps[$pos] & 0x40) >> 6;
        $constraintSet2 = ($sps[$pos] & 0x20) >> 5;
        $constraintSet3 = ($sps[$pos] & 0x10) >> 4;
        $levelIdc = $sps[$pos] & 0x0F;
        $pos++;
        
        $pos++;
        
        $log2MaxFrameNumMinus4 = $this->_ue($sps, $pos);
        $log2MaxPicOrderCntLsbMinus4 = $this->_ue($sps, $pos);
        
        $frameMbsOnlyFlag = $this->_ue($sps, $pos);
        
        $pos += ($frameMbsOnlyFlag == 0) ? 1 : 0;
        
        $gapsInFrameNumValueAllowedFlag = $this->_ue($sps, $pos);
        
        $picWidthInMbsMinus1 = $this->_ue($sps, $pos);
        $picHeightInMapUnitsMinus1 = $this->_ue($sps, $pos);

        echo "[TagDemux] SPS解析: picWidthInMbsMinus1=$picWidthInMbsMinus1, picHeightInMapUnitsMinus1=$picHeightInMapUnitsMinus1\n";

        $frameWidth = ($picWidthInMbsMinus1 + 1) * 16;
        $frameHeight = ($picHeightInMapUnitsMinus1 + 1) * 16;
        
        $frameCropLeftOffset = 0;
        $frameCropRightOffset = 0;
        $frameCropTopOffset = 0;
        $frameCropBottomOffset = 0;
        
        $frameCroppingFlag = $this->_ue($sps, $pos);
        if ($frameCroppingFlag) {
            $frameCropLeftOffset = $this->_ue($sps, $pos) << 1;
            $frameCropRightOffset = $this->_ue($sps, $pos) << 1;
            $frameCropTopOffset = $this->_ue($sps, $pos) << 1;
            $frameCropBottomOffset = $this->_ue($sps, $pos) << 1;
        }
        
        $width = $frameWidth - $frameCropLeftOffset - $frameCropRightOffset;
        $height = $frameHeight - $frameCropTopOffset - $frameCropBottomOffset;
        
        $sarRatio = ['width' => 1, 'height' => 1];
        $aspectRatioIdc = $this->_ue($sps, $pos);
        if ($aspectRatioIdc != 0) {
            if ($aspectRatioIdc == 1) {
                $sarRatio = ['width' => 1, 'height' => 1];
            } else if ($aspectRatioIdc == 2) {
                $sarRatio = ['width' => 12, 'height' => 11];
            } else if ($aspectRatioIdc == 3) {
                $sarRatio = ['width' => 10, 'height' => 11];
            } else if ($aspectRatioIdc == 4) {
                $sarRatio = ['width' => 16, 'height' => 11];
            } else if ($aspectRatioIdc == 5) {
                $sarRatio = ['width' => 40, 'height' => 33];
            } else if ($aspectRatioIdc == 6) {
                $sarRatio = ['width' => 24, 'height' => 11];
            } else if ($aspectRatioIdc == 7) {
                $sarRatio = ['width' => 20, 'height' => 11];
            } else if ($aspectRatioIdc == 8) {
                $sarRatio = ['width' => 32, 'height' => 11];
            } else if ($aspectRatioIdc == 9) {
                $sarRatio = ['width' => 18, 'height' => 11];
            } else if ($aspectRatioIdc == 10) {
                $sarRatio = ['width' => 15, 'height' => 11];
            } else if ($aspectRatioIdc == 11) {
                $sarRatio = ['width' => 64, 'height' => 33];
            } else if ($aspectRatioIdc == 12) {
                $sarRatio = ['width' => 160, 'height' => 99];
            } else if ($aspectRatioIdc == 13) {
                $sarRatio = ['width' => 4, 'height' => 3];
            } else if ($aspectRatioIdc == 14) {
                $sarRatio = ['width' => 3, 'height' => 2];
            } else if ($aspectRatioIdc == 15) {
                $sarRatio = ['width' => 2, 'height' => 1];
            } else if ($aspectRatioIdc == 255) {
                $sarRatio['width'] = ($sps[$pos] << 8) | $sps[$pos + 1];
                $sarRatio['height'] = ($sps[$pos + 2] << 8) | $sps[$pos + 3];
                $pos += 4;
            }
        }
        
        $sampleAspectRatioNum = $sarRatio['width'];
        $sampleAspectRatioDen = $sarRatio['height'];
        
        $displayWidth = $width;
        $displayHeight = $height;
        
        if ($sampleAspectRatioNum != 1 || $sampleAspectRatioDen != 1) {
            $displayWidth = $width * $sampleAspectRatioNum / $sampleAspectRatioDen;
            $displayHeight = $height;
        }
        
        $displayWidth = (int)($displayWidth + 0.5);
        $displayHeight = (int)($displayHeight + 0.5);
        
        return [
            'codec_size' => ['width' => $width, 'height' => $height],
            'present_size' => ['width' => $displayWidth, 'height' => $displayHeight],
            'profile_string' => 'Baseline',
            'level_string' => '3.1',
            'frame_rate' => ['fixed' => true, 'fps' => 25, 'fps_num' => 25000, 'fps_den' => 1000],
            'sar_ratio' => $sarRatio
        ];
    }
    
    private function _ue($data, &$pos) {
        $result = 0;
        $leadingZeroBits = 0;
        
        while ($pos < count($data) && $data[$pos] == 0) {
            $leadingZeroBits++;
            $pos++;
        }
        
        if ($pos >= count($data)) return 0;
        
        $pos++;
        
        $result = (1 << $leadingZeroBits) - 1;
        $result += $data[$pos - 1] & ((1 << (7 - $leadingZeroBits)) - 1);
        
        return $result;
    }
    
    private function _parseAVCVideoData($arrayBuffer, $dataOffset, $dataSize, $tagTimestamp, $tagPosition, $frameType, $cts) {
        $lengthSize = $this->_naluLengthSize;
        $dts = $this->_timestampBase + $tagTimestamp;
        $keyframe = ($frameType === 1);
        
        $offset = 0;
        $units = [];
        $length = 0;
        
        while ($offset < $dataSize) {
            if ($offset + 4 >= $dataSize) break;
            
            $naluSize = ($arrayBuffer[$dataOffset + $offset] << 24) |
                       ($arrayBuffer[$dataOffset + $offset + 1] << 16) |
                       ($arrayBuffer[$dataOffset + $offset + 2] << 8) |
                       $arrayBuffer[$dataOffset + $offset + 3];
            
            if ($lengthSize === 3) {
                $naluSize >>= 8;
            }
            
            if ($naluSize > $dataSize - $lengthSize) break;
            
            $unitType = $arrayBuffer[$dataOffset + $offset + $lengthSize] & 0x1F;
            
            if ($unitType === 5) {
                $keyframe = true;
            }
            
            $unitData = array_slice($arrayBuffer, $dataOffset + $offset, $lengthSize + $naluSize);
            $units[] = ['type' => $unitType, 'data' => $unitData];
            $length += count($unitData);
            
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
        }
    }
    
    private function _parseAudioData($arrayBuffer, $dataOffset, $dataSize, $tagTimestamp) {
        if ($dataSize <= 1) {
            echo "[TagDemux] 无效的音频数据包\n";
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
                echo "[TagDemux] 不支持的音频编码: $soundFormat\n";
                return;
            }
            
            $soundRateIndex = ($soundSpec & 12) >> 2;
            $soundRateTable = [5500, 11025, 22050, 44100, 48000];
            
            if ($soundRateIndex >= count($soundRateTable)) {
                echo "[TagDemux] 无效的音频采样率索引: $soundRateIndex\n";
                return;
            }
            
            $soundRate = $soundRateTable[$soundRateIndex];
            $soundType = $soundSpec & 1;
            
            $meta['audioSampleRate'] = $soundRate;
            $meta['channelCount'] = ($soundType === 0 ? 1 : 2);
            $meta['refSampleDuration'] = floor(1024 / $meta['audioSampleRate'] * $meta['timescale']);
            $meta['codec'] = 'mp4a.40.5';
        }
        
        $aacData = $this->_parseAACAudioData($arrayBuffer, $dataOffset + 1, $dataSize - 1);
        if (!$aacData) return;
        
        if ($aacData['packetType'] === 0) {
            $misc = $aacData['data'];
            $meta['audioSampleRate'] = $misc['samplingRate'];
            $meta['channelCount'] = $misc['channelCount'];
            $meta['codec'] = $misc['codec'];
            $meta['config'] = $misc['config'];
            $meta['refSampleDuration'] = floor(1024 / $meta['audioSampleRate'] * $meta['timescale']);
            
            echo "[TagDemux] 解析AudioSpecificConfig完成\n";
            
            $this->_audioInitialMetadataDispatched = true;
            if ($this->_onTrackMetadata) {
                call_user_func($this->_onTrackMetadata, 'audio', $meta);
            }
            
            $mi = $this->_mediaInfo;
            $mi['audioCodec'] = 'mp4a.40.' . $misc['originalAudioObjectType'];
            $mi['audioSampleRate'] = $meta['audioSampleRate'];
            $mi['audioChannelCount'] = $meta['channelCount'];
            
            return;
        } else if ($aacData['packetType'] === 1) {
            $dts = $this->_timestampBase + $tagTimestamp;
            $aacSample = ['unit' => $aacData['data'], 'dts' => $dts, 'pts' => $dts];
            $this->_audioTrack['samples'][] = $aacSample;
            $this->_audioTrack['length'] += count($aacData['data']);
        }
    }
    
    private function _parseAACAudioData($arrayBuffer, $dataOffset, $dataSize) {
        if ($dataSize <= 1) {
            return null;
        }
        
        $result = [];
        $result['packetType'] = $arrayBuffer[$dataOffset];
        
        if ($arrayBuffer[$dataOffset] === 0) {
            $result['data'] = $this->_parseAACAudioSpecificConfig($arrayBuffer, $dataOffset + 1, $dataSize - 1);
        } else {
            $result['data'] = array_slice($arrayBuffer, $dataOffset + 1);
        }
        
        return $result;
    }
    
    private function _parseAACAudioSpecificConfig($arrayBuffer, $dataOffset, $dataSize) {
        $mpegSamplingRates = [
            96000, 88200, 64000, 48000, 44100, 32000,
            24000, 22050, 16000, 12000, 11025, 8000, 7350
        ];
        
        $audioObjectType = 0;
        $originalAudioObjectType = 0;
        $samplingIndex = 0;
        
        $audioObjectType = $originalAudioObjectType = $arrayBuffer[$dataOffset] >> 3;
        $samplingIndex = (($arrayBuffer[$dataOffset] & 0x07) << 1) | ($arrayBuffer[$dataOffset + 1] >> 7);
        
        if ($samplingIndex < 0 || $samplingIndex >= count($mpegSamplingRates)) {
            return null;
        }
        
        $samplingFrequence = $mpegSamplingRates[$samplingIndex];
        $channelConfig = ($arrayBuffer[$dataOffset + 1] & 0x78) >> 3;
        
        if ($channelConfig < 0 || $channelConfig >= 8) {
            return null;
        }
        
        $audioObjectType = 2;
        $config = array_fill(0, 2, 0);
        
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
    
    function setOnTrackMetadata($callback) {
        $this->_onTrackMetadata = $callback;
    }
    
    function setOnMediaInfo($callback) {
        $this->_onMediaInfo = $callback;
    }
    
    function setOnDataAvailable($callback) {
        $this->_onDataAvailable = $callback;
    }
    
    function setTimestampBase($i) {
        $this->_timestampBase = $i;
    }
    
    function getAudioTrack() {
        return $this->_audioTrack;
    }
    
    function getVideoTrack() {
        return $this->_videoTrack;
    }
    
    function getMediaInfo() {
        return $this->_mediaInfo;
    }
}
