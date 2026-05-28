<?php
/**
 * fMP4 片段化重封装器
 */
class MP4Moof {
    private $_config = [];
    private $_isLive = false;
    
    private $_dtsBase = -1;
    private $_dtsBaseInited = false;
    private $_audioDtsBase = INF;
    private $_videoDtsBase = INF;
    private $_audioNextDts = null;
    private $_videoNextDts = null;
    
    private $_audioMeta = null;
    private $_videoMeta = null;
    private $_naluLengthSize = 4;  // 默认4字节，会从AVCDecoderConfigurationRecord更新
    
    private $_onInitSegment = null;
    private $_onMediaSegment = null;
    
    function __construct($config = []) {
        $this->_config = $config;
        $this->_isLive = isset($config['isLive']) && $config['isLive'];
    }
    
    function seek($baseTime) {
        // 清空状态
        $this->_videoNextDts = null;
        $this->_audioNextDts = null;
    }
    
    function insertDiscontinuity() {
        $this->_audioNextDts = null;
        $this->_videoNextDts = null;
    }
    
    function remux($audioTrack, $videoTrack) {
        if (!$this->_onMediaSegment) {
            throw new Exception('MP4Remuxer: onMediaSegment callback must be specified!');
        }
        
        if (!$this->_dtsBaseInited) {
            $this->_calculateDtsBase($audioTrack, $videoTrack);
        }
        
        $this->_remuxVideo($videoTrack);
        $this->_remuxAudio($audioTrack);
    }
    
    private function _calculateDtsBase($audioTrack, $videoTrack) {
        if ($this->_dtsBaseInited) return;
        
        if (isset($audioTrack['samples']) && count($audioTrack['samples']) > 0) {
            $this->_audioDtsBase = $audioTrack['samples'][0]['dts'];
        }
        if (isset($videoTrack['samples']) && count($videoTrack['samples']) > 0) {
            $this->_videoDtsBase = $videoTrack['samples'][0]['dts'];
        }
        
        $this->_dtsBase = min($this->_audioDtsBase, $this->_videoDtsBase);
        $this->_dtsBaseInited = true;
        
        echo "[MP4Moof] DTS基准时间: " . $this->_dtsBase . "\n";
    }
    
    private function _remuxAudio($audioTrack) {
        if ($this->_audioMeta == null) return;
        
        $track = $audioTrack;
        $samples = $track['samples'];
        
        if (!isset($samples) || count($samples) === 0) {
            return;
        }
        
        $bytes = 8 + $track['length'];
        $mdatbox = array_fill(0, $bytes, 0);
        
        $mdatbox[0] = ($bytes >> 24) & 0xFF;
        $mdatbox[1] = ($bytes >> 16) & 0xFF;
        $mdatbox[2] = ($bytes >> 8) & 0xFF;
        $mdatbox[3] = $bytes & 0xFF;
        
        $mdatbox[4] = 0x6D; // 'm'
        $mdatbox[5] = 0x64; // 'd'
        $mdatbox[6] = 0x61; // 'a'
        $mdatbox[7] = 0x74; // 't'
        
        $offset = 8;
        $mp4Samples = [];
        $firstDts = -1;
        $dtsCorrection = null;
        $refSampleDuration = $this->_audioMeta['refSampleDuration'];
        
        while (count($samples) > 0) {
            $aacSample = array_shift($samples);
            $unit = $aacSample['unit'];
            $originalDts = $aacSample['dts'] - $this->_dtsBase;
            
            if ($dtsCorrection === null) {
                if ($this->_audioNextDts === null) {
                    // 首次处理，将第一个样本的DTS对齐到0
                    $dtsCorrection = $originalDts;
                } else {
                    $dtsCorrection = $originalDts - $this->_audioNextDts;
                }
            }
            
            $dts = $originalDts - $dtsCorrection;
            
            if ($firstDts === -1) {
                $firstDts = $dts;
            }
            
            $sampleDuration = 0;
            if (count($samples) >= 1) {
                $nextDts = $samples[0]['dts'] - $this->_dtsBase - $dtsCorrection;
                $sampleDuration = $nextDts - $dts;
            } else {
                if (count($mp4Samples) >= 1) {
                    $sampleDuration = $mp4Samples[count($mp4Samples) - 1]['duration'];
                } else {
                    $sampleDuration = $refSampleDuration;
                }
            }
            
            $mp4Sample = [
                'dts' => $dts,
                'pts' => $dts,
                'cts' => 0,
                'size' => count($unit),
                'duration' => $sampleDuration,
                'originalDts' => $originalDts,
                'flags' => [
                    'isLeading' => 0,
                    'dependsOn' => 1,
                    'isDependedOn' => 0,
                    'hasRedundancy' => 0,
                    'isNonSync' => 0
                ]
            ];
            
            $mp4Samples[] = $mp4Sample;
            
            foreach ($unit as $byte) {
                $mdatbox[$offset++] = $byte;
            }
        }
        
        if (count($mp4Samples) > 0) {
            $latest = $mp4Samples[count($mp4Samples) - 1];
            $lastDts = $latest['dts'] + $latest['duration'];
            $this->_audioNextDts = $lastDts;
            
            $track['samples'] = $mp4Samples;
            $track['sequenceNumber'] += $track['addcoefficient'];
            
            echo "[MP4Moof] 生成音频moof, 样本数=" . count($mp4Samples) . ", 首DTS=" . $firstDts . "\n";
            
            $moofbox = MP4::moof($track, $firstDts);
            
            $track['samples'] = [];
            $track['length'] = 0;
            
            $result = $this->_mergeBoxes($moofbox, $mdatbox);
            
            if ($this->_onMediaSegment) {
                call_user_func($this->_onMediaSegment, 'audio', ['type' => 'audio', 'data' => $result, 'sampleCount' => count($mp4Samples)]);
            }
        }
    }
    
    private function _remuxVideo($videoTrack) {
        $track = $videoTrack;
        $samples = $track['samples'];
        
        if (!isset($samples) || count($samples) === 0) {
            return;
        }
        
        $bytes = 8 + $track['length'];
        $mdatbox = array_fill(0, $bytes, 0);
        
        $mdatbox[0] = ($bytes >> 24) & 0xFF;
        $mdatbox[1] = ($bytes >> 16) & 0xFF;
        $mdatbox[2] = ($bytes >> 8) & 0xFF;
        $mdatbox[3] = $bytes & 0xFF;
        
        $mdatbox[4] = 0x6D;
        $mdatbox[5] = 0x64;
        $mdatbox[6] = 0x61;
        $mdatbox[7] = 0x74;
        
        $offset = 8;
        $mp4Samples = [];
        $firstDts = -1;
        $firstPts = -1;
        $dtsCorrection = null;
        
        while (count($samples) > 0) {
            $avcSample = array_shift($samples);
            $keyframe = $avcSample['isKeyframe'];
            $originalDts = $avcSample['dts'] - $this->_dtsBase;
            
            if ($dtsCorrection === null) {
                if ($this->_videoNextDts === null) {
                    // 首次处理，将第一个样本的DTS对齐到0
                    $dtsCorrection = $originalDts;
                } else {
                    $dtsCorrection = $originalDts - $this->_videoNextDts;
                }
            }
            
            $dts = $originalDts - $dtsCorrection;
            $cts = $avcSample['cts'];
            $pts = $dts + $cts;
            
            if ($firstDts === -1) {
                $firstDts = $dts;
                $firstPts = $pts;
            }
            
            $sampleSize = 0;
            while (count($avcSample['units']) > 0) {
                $unit = array_shift($avcSample['units']);
                $data = $unit['data'];
                
                // fMP4需要4字节长度前缀，而FLV中可能是3字节或4字节
                // 获取NAL数据（去掉原始长度前缀）
                $naluData = array_slice($data, $this->_naluLengthSize);
                $naluSize = count($naluData);
                
                // 写入4字节长度前缀
                $mdatbox[$offset++] = ($naluSize >> 24) & 0xFF;
                $mdatbox[$offset++] = ($naluSize >> 16) & 0xFF;
                $mdatbox[$offset++] = ($naluSize >> 8) & 0xFF;
                $mdatbox[$offset++] = $naluSize & 0xFF;
                
                // 写入NAL数据
                foreach ($naluData as $byte) {
                    $mdatbox[$offset++] = $byte;
                }
                $sampleSize += 4 + $naluSize;
            }
            
            $sampleDuration = 0;
            if (count($samples) >= 1) {
                $nextDts = $samples[0]['dts'] - $this->_dtsBase - $dtsCorrection;
                $sampleDuration = $nextDts - $dts;
            } else {
                if (count($mp4Samples) >= 1) {
                    $sampleDuration = $mp4Samples[count($mp4Samples) - 1]['duration'];
                } else {
                    $sampleDuration = $this->_videoMeta['refSampleDuration'];
                }
            }
            
            $mp4Sample = [
                'dts' => $dts,
                'pts' => $pts,
                'cts' => $cts,
                'size' => $sampleSize,
                'isKeyframe' => $keyframe,
                'duration' => $sampleDuration,
                'originalDts' => $originalDts,
                'flags' => [
                    'isLeading' => 0,
                    'dependsOn' => $keyframe ? 2 : 1,
                    'isDependedOn' => $keyframe ? 1 : 0,
                    'hasRedundancy' => 0,
                    'isNonSync' => $keyframe ? 0 : 1
                ]
            ];
            
            $mp4Samples[] = $mp4Sample;
        }
        
        if (count($mp4Samples) > 0) {
            $latest = $mp4Samples[count($mp4Samples) - 1];
            $lastDts = $latest['dts'] + $latest['duration'];
            $lastPts = $latest['pts'] + $latest['duration'];
            $this->_videoNextDts = $lastDts;
            
            $track['samples'] = $mp4Samples;
            $track['sequenceNumber'] += $track['addcoefficient'];
            
            echo "[MP4Moof] 生成视频moof, 样本数=" . count($mp4Samples) . ", 首DTS=" . $firstDts . "\n";
            
            $moofbox = MP4::moof($track, $firstDts);
            
            $track['samples'] = [];
            $track['length'] = 0;
            
            $result = $this->_mergeBoxes($moofbox, $mdatbox);
            
            if ($this->_onMediaSegment) {
                call_user_func($this->_onMediaSegment, 'video', ['type' => 'video', 'data' => $result, 'sampleCount' => count($mp4Samples)]);
            }
        }
    }
    
    private function _mergeBoxes($moof, $mdat) {
        $result = [];
        foreach ($moof as $byte) {
            $result[] = $byte;
        }
        foreach ($mdat as $byte) {
            $result[] = $byte;
        }
        return $result;
    }
    
    function setOnMediaSegment($callback) {
        $this->_onMediaSegment = $callback;
    }
    
    function setAudioMeta($meta) {
        $this->_audioMeta = $meta;
    }
    
    function setVideoMeta($meta) {
        $this->_videoMeta = $meta;
        // 从AVCDecoderConfigurationRecord中提取NAL长度大小
        if (isset($meta['avcc']) && count($meta['avcc']) > 4) {
            $this->_naluLengthSize = ($meta['avcc'][4] & 3) + 1;
            echo "[MP4Moof] NAL长度大小: " . $this->_naluLengthSize . "字节\n";
        }
    }
}
