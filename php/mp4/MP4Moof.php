<?php

require_once 'AACSilent.php';
require_once 'MediaSegmentInfo.php';
require_once 'MP4Remux.php';

class MP4Remuxer {
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
    private $_audioSegmentInfoList = null;
    private $_videoSegmentInfoList = null;
    private $_onInitSegment = null;
    private $_onMediaSegment = null;
    private $_forceFirstIDR = false;
    private $_fillSilentAfterSeek = false;

    public function __construct($config) {
        $this->_config = $config;
        $this->_isLive = isset($config['isLive']) && $config['isLive'] === true;
        $this->_audioSegmentInfoList = new MediaSegmentInfoList('audio');
        $this->_videoSegmentInfoList = new MediaSegmentInfoList('video');
    }

    public function destroy() {
        $this->_dtsBase = -1;
        $this->_dtsBaseInited = false;
        $this->_audioMeta = null;
        $this->_videoMeta = null;
        $this->_audioSegmentInfoList->clear();
        $this->_audioSegmentInfoList = null;
        $this->_videoSegmentInfoList->clear();
        $this->_videoSegmentInfoList = null;
        $this->_onInitSegment = null;
        $this->_onMediaSegment = null;
    }

    public function getOnInitSegment() {
        return $this->_onInitSegment;
    }

    public function setOnInitSegment($callback) {
        $this->_onInitSegment = $callback;
    }

    public function getOnMediaSegment() {
        return $this->_onMediaSegment;
    }

    public function setOnMediaSegment($callback) {
        $this->_onMediaSegment = $callback;
    }

    public function setVideoMeta($meta) {
        $this->_videoMeta = $meta;
    }

    public function setAudioMeta($meta) {
        $this->_audioMeta = $meta;
    }

    public function insertDiscontinuity() {
        $this->_audioNextDts = $this->_videoNextDts = null;
    }

    public function seek($originalDts) {
        $this->_videoSegmentInfoList->clear();
        $this->_audioSegmentInfoList->clear();
    }

    public function remux($audioTrack, $videoTrack) {
        if (!$this->_onMediaSegment) {
            throw new Exception('MP4Remuxer: onMediaSegment callback must be specified!');
        }
        if (!$this->_dtsBaseInited) {
            $this->_calculateDtsBase($audioTrack, $videoTrack);
        }
        $this->_remuxMixed($audioTrack, $videoTrack);
    }

    public function _onTrackMetadataReceived($type, $metadata) {
        $metabox = null;

        if ($type === 'audio') {
            $this->_audioMeta = $metadata;
            $metabox = MP4::generateInitSegment($metadata);
        } else if ($type === 'video') {
            $this->_videoMeta = $metadata;
            $metabox = MP4::generateInitSegment($metadata);
        } else {
            return;
        }

        if (!$this->_onInitSegment) {
            throw new Exception('MP4Remuxer: onInitSegment callback must be specified!');
        }
        ($this->_onInitSegment)($type, [
            'type' => $type,
            'data' => $metabox,
            'codec' => $metadata['codec'],
            'container' => $type . '/mp4'
        ]);
    }

    private function _calculateDtsBase($audioTrack, $videoTrack) {
        if ($this->_dtsBaseInited) {
            return;
        }

        if (isset($audioTrack['samples']) && count($audioTrack['samples']) > 0) {
            $this->_audioDtsBase = $audioTrack['samples'][0]['dts'];
        }
        if (isset($videoTrack['samples']) && count($videoTrack['samples']) > 0) {
            $this->_videoDtsBase = $videoTrack['samples'][0]['dts'];
        }

        $this->_dtsBase = min($this->_audioDtsBase, $this->_videoDtsBase);
        $this->_dtsBaseInited = true;
    }

    private function _remuxAudio($audioTrack) {
        if ($this->_audioMeta === null) {
            return;
        }

        $track = $audioTrack;
        $samples = $track['samples'];
        $dtsCorrection = null;
        $firstDts = -1;
        $lastDts = -1;
        $lastPts = -1;

        $remuxSilentFrame = false;
        $silentFrameDuration = -1;
        $refSampleDuration = $this->_audioMeta['refSampleDuration'];

        if (!$samples || count($samples) === 0) {
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

        while (count($samples) > 0) {
            $aacSample = array_shift($samples);
            $unit = $aacSample['unit'];
            $originalDts = $aacSample['dts'] - $this->_dtsBase;

            if ($dtsCorrection === null) {
                if ($this->_audioNextDts === null) {
                    if ($this->_audioSegmentInfoList->isEmpty()) {
                        $dtsCorrection = 0;
                        if ($this->_fillSilentAfterSeek && !$this->_videoSegmentInfoList->isEmpty()) {
                            $remuxSilentFrame = true;
                        }
                    } else {
                        $lastSample = $this->_audioSegmentInfoList->getLastSampleBefore($originalDts);
                        if ($lastSample != null) {
                            $distance = $originalDts - ($lastSample->originalDts + $lastSample->duration);
                            if ($distance <= 3) {
                                $distance = 0;
                            }
                            $expectedDts = $lastSample->dts + $lastSample->duration + $distance;
                            $dtsCorrection = $originalDts - $expectedDts;
                        } else {
                            $dtsCorrection = 0;
                        }
                    }
                } else {
                    $dtsCorrection = $originalDts - $this->_audioNextDts;
                }
            }

            $dts = $originalDts - $dtsCorrection;
            if ($remuxSilentFrame) {
                $videoSegment = $this->_videoSegmentInfoList->getLastSegmentBefore($originalDts);
                if ($videoSegment != null && $videoSegment->beginDts < $dts) {
                    $silentFrameDuration = $dts - $videoSegment->beginDts;
                    $dts = $videoSegment->beginDts;
                } else {
                    $remuxSilentFrame = false;
                }
            }
            if ($firstDts === -1) {
                $firstDts = $dts;
            }

            if ($remuxSilentFrame) {
                $remuxSilentFrame = false;
                array_unshift($samples, $aacSample);

                $frame = $this->_generateSilentAudio($dts, $silentFrameDuration);
                if ($frame == null) {
                    continue;
                }
                $mp4Sample = $frame['mp4Sample'];
                $unit = $frame['unit'];

                $mp4Samples[] = $mp4Sample;

                $bytes += count($unit);
                $mdatbox = array_fill(0, $bytes, 0);
                $mdatbox[0] = ($bytes >> 24) & 0xFF;
                $mdatbox[1] = ($bytes >> 16) & 0xFF;
                $mdatbox[2] = ($bytes >> 8) & 0xFF;
                $mdatbox[3] = $bytes & 0xFF;
                $mdatbox[4] = 0x6D;
                $mdatbox[5] = 0x64;
                $mdatbox[6] = 0x61;
                $mdatbox[7] = 0x74;

                foreach ($unit as $byte) {
                    $mdatbox[$offset++] = $byte;
                }
                continue;
            }

            $sampleDuration = 0;

            if (count($samples) >= 1) {
                $nextDts = $samples[0]['dts'] - $this->_dtsBase - $dtsCorrection;
                $sampleDuration = $nextDts - $dts;
            } else {
                if (count($mp4Samples) >= 1) {
                    $sampleDuration = $mp4Samples[count($mp4Samples) - 1]['duration'];
                } else {
                    $sampleDuration = $this->_audioMeta['refSampleDuration'];
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

        $latest = $mp4Samples[count($mp4Samples) - 1];
        $lastDts = $latest['dts'] + $latest['duration'];
        $this->_audioNextDts = $lastDts;

        $info = new MediaSegmentInfo();
        $info->beginDts = $firstDts;
        $info->endDts = $lastDts;
        $info->beginPts = $firstDts;
        $info->endPts = $lastDts;
        $info->originalBeginDts = $mp4Samples[0]['originalDts'];
        $info->originalEndDts = $latest['originalDts'] + $latest['duration'];
        $info->firstSample = new SampleInfo($mp4Samples[0]['dts'], $mp4Samples[0]['pts'], $mp4Samples[0]['duration'], $mp4Samples[0]['originalDts'], false);
        $info->lastSample = new SampleInfo($latest['dts'], $latest['pts'], $latest['duration'], $latest['originalDts'], false);
        
        if (!$this->_isLive) {
            $this->_audioSegmentInfoList->append($info);
        }

        $track['samples'] = $mp4Samples;
        $track['sequenceNumber'] += $track['addcoefficient'];

        $moofbox = MP4::moof($track, $firstDts);
        $track['samples'] = [];
        $track['length'] = 0;

        ($this->_onMediaSegment)('audio', [
            'type' => 'audio',
            'data' => array_merge($moofbox, $mdatbox),
            'sampleCount' => count($mp4Samples),
            'info' => $info
        ]);
    }

    private function _generateSilentAudio($dts, $frameDuration) {
        $unit = AACSilent::getSilentFrame($this->_audioMeta['channelCount']);
        if ($unit == null) {
            return null;
        }

        $mp4Sample = [
            'dts' => $dts,
            'pts' => $dts,
            'cts' => 0,
            'size' => count($unit),
            'duration' => $frameDuration,
            'originalDts' => $dts,
            'flags' => [
                'isLeading' => 0,
                'dependsOn' => 1,
                'isDependedOn' => 0,
                'hasRedundancy' => 0,
                'isNonSync' => 0
            ]
        ];

        return ['unit' => $unit, 'mp4Sample' => $mp4Sample];
    }

    private function _remuxVideo($videoTrack) {
        $track = $videoTrack;
        $samples = $track['samples'];
        $dtsCorrection = null;
        $firstDts = -1;
        $lastDts = -1;
        $firstPts = -1;
        $lastPts = -1;

        if (!$samples || count($samples) === 0) {
            return;
        }

        $bytes = 8 + $videoTrack['length'];
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
        $info = new MediaSegmentInfo();

        while (count($samples) > 0) {
            $avcSample = array_shift($samples);
            $keyframe = $avcSample['isKeyframe'];
            $originalDts = $avcSample['dts'] - $this->_dtsBase;

            if ($dtsCorrection === null) {
                if ($this->_videoNextDts === null) {
                    if ($this->_videoSegmentInfoList->isEmpty()) {
                        $dtsCorrection = 0;
                    } else {
                        $lastSample = $this->_videoSegmentInfoList->getLastSampleBefore($originalDts);
                        if ($lastSample != null) {
                            $distance = $originalDts - ($lastSample->originalDts + $lastSample->duration);
                            if ($distance <= 3) {
                                $distance = 0;
                            }
                            $expectedDts = $lastSample->dts + $lastSample->duration + $distance;
                            $dtsCorrection = $originalDts - $expectedDts;
                        } else {
                            $dtsCorrection = 0;
                        }
                    }
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
                foreach ($data as $byte) {
                    $mdatbox[$offset++] = $byte;
                }
                $sampleSize += count($data);
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

            if ($keyframe) {
                $syncPoint = new SampleInfo($dts, $pts, $sampleDuration, $avcSample['dts'], true);
                $syncPoint->fileposition = $avcSample['fileposition'];
                $info->appendSyncPoint($syncPoint);
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

        $latest = $mp4Samples[count($mp4Samples) - 1];
        $lastDts = $latest['dts'] + $latest['duration'];
        $lastPts = $latest['pts'] + $latest['duration'];
        $this->_videoNextDts = $lastDts;

        $info->beginDts = $firstDts;
        $info->endDts = $lastDts;
        $info->beginPts = $firstPts;
        $info->endPts = $lastPts;
        $info->originalBeginDts = $mp4Samples[0]['originalDts'];
        $info->originalEndDts = $latest['originalDts'] + $latest['duration'];
        $info->firstSample = new SampleInfo($mp4Samples[0]['dts'], $mp4Samples[0]['pts'], $mp4Samples[0]['duration'], $mp4Samples[0]['originalDts'], $mp4Samples[0]['isKeyframe']);
        $info->lastSample = new SampleInfo($latest['dts'], $latest['pts'], $latest['duration'], $latest['originalDts'], $latest['isKeyframe']);
        
        if (!$this->_isLive) {
            $this->_videoSegmentInfoList->append($info);
        }

        $track['samples'] = $mp4Samples;
        $track['sequenceNumber'] += $track['addcoefficient'];

        if ($this->_forceFirstIDR) {
            $flags = $mp4Samples[0]['flags'];
            $flags['dependsOn'] = 2;
            $flags['isNonSync'] = 0;
        }

        $moofbox = MP4::moof($track, $firstDts);
        $track['samples'] = [];
        $track['length'] = 0;

        ($this->_onMediaSegment)('video', [
            'type' => 'video',
            'data' => array_merge($moofbox, $mdatbox),
            'sampleCount' => count($mp4Samples),
            'info' => $info
        ]);
    }

    private function _remuxMixed($audioTrack, $videoTrack) {
        if ($this->_audioMeta === null || $this->_videoMeta === null) {
            return;
        }

        $audioSamples = $audioTrack['samples'];
        $videoSamples = $videoTrack['samples'];

        if (!$audioSamples || count($audioSamples) === 0 || !$videoSamples || count($videoSamples) === 0) {
            return;
        }

        $audioOffset = 0;
        $videoOffset = 0;
        $mixedSamples = [];
        $dataOffset = 8;

        while ($audioOffset < count($audioSamples) || $videoOffset < count($videoSamples)) {
            $audioSample = $audioOffset < count($audioSamples) ? $audioSamples[$audioOffset] : null;
            $videoSample = $videoOffset < count($videoSamples) ? $videoSamples[$videoOffset] : null;

            if ($audioSample !== null && ($videoSample === null || $audioSample['dts'] <= $videoSample['dts'])) {
                $unit = $audioSample['unit'];
                $mixedSamples[] = ['type' => 'audio', 'sample' => $audioSample, 'offset' => $dataOffset, 'unit' => $unit];
                $dataOffset += count($unit);
                $audioOffset++;
            } else if ($videoSample !== null) {
                // 视频样本需要添加 NALU 长度前缀
                $unit = [];
                foreach ($videoSample['units'] as $u) {
                    $naluData = $u['data'];
                    $naluSize = count($naluData);
                    // 添加 4 字节长度前缀
                    $unit[] = ($naluSize >> 24) & 0xFF;
                    $unit[] = ($naluSize >> 16) & 0xFF;
                    $unit[] = ($naluSize >> 8) & 0xFF;
                    $unit[] = $naluSize & 0xFF;
                    // 添加 NALU 数据
                    $unit = array_merge($unit, $naluData);
                }
                $mixedSamples[] = ['type' => 'video', 'sample' => $videoSample, 'offset' => $dataOffset, 'unit' => $unit];
                $dataOffset += count($unit);
                $videoOffset++;
            }
        }

        $bytes = $dataOffset;
        $mdatbox = array_fill(0, $bytes, 0);
        $mdatbox[0] = ($bytes >> 24) & 0xFF;
        $mdatbox[1] = ($bytes >> 16) & 0xFF;
        $mdatbox[2] = ($bytes >> 8) & 0xFF;
        $mdatbox[3] = $bytes & 0xFF;
        $mdatbox[4] = 0x6D;
        $mdatbox[5] = 0x64;
        $mdatbox[6] = 0x61;
        $mdatbox[7] = 0x74;

        foreach ($mixedSamples as $item) {
            $unit = $item['unit'];
            $offset = $item['offset'];
            foreach ($unit as $byte) {
                $mdatbox[$offset++] = $byte;
            }
        }

        $audioMp4Samples = [];
        $videoMp4Samples = [];
        $firstDts = null;

        foreach ($mixedSamples as $item) {
            $sample = $item['sample'];
            $unit = $item['unit'];
            $originalDts = $sample['dts'] - $this->_dtsBase;
            
            if ($firstDts === null) {
                $firstDts = $originalDts;
            }

            $mp4Sample = [
                'dts' => $originalDts,
                'pts' => $sample['pts'],
                'cts' => isset($sample['cts']) ? $sample['cts'] : 0,
                'size' => count($unit),
                'duration' => isset($sample['duration']) ? $sample['duration'] : 0,
                'originalDts' => $sample['dts'],
                'flags' => isset($sample['flags']) ? $sample['flags'] : [
                    'isLeading' => 0,
                    'dependsOn' => 1,
                    'isDependedOn' => 0,
                    'hasRedundancy' => 0,
                    'isNonSync' => 0
                ]
            ];

            if ($item['type'] === 'audio') {
                $audioMp4Samples[] = $mp4Sample;
            } else {
                $videoMp4Samples[] = $mp4Sample;
            }
        }

        $videoTrackData = [
            'id' => $videoTrack['id'],
            'samples' => $videoMp4Samples,
            'sequenceNumber' => $videoTrack['sequenceNumber']
        ];

        $audioTrackData = [
            'id' => $audioTrack['id'],
            'samples' => $audioMp4Samples,
            'sequenceNumber' => $audioTrack['sequenceNumber']
        ];

        $moofbox = $this->_generateMixedMoof($videoTrackData, $audioTrackData, $firstDts);

        $videoTrack['samples'] = [];
        $videoTrack['length'] = 0;
        $audioTrack['samples'] = [];
        $audioTrack['length'] = 0;

        ($this->_onMediaSegment)('mixed', [
            'type' => 'mixed',
            'data' => array_merge($moofbox, $mdatbox),
            'sampleCount' => count($mixedSamples),
            'info' => null
        ]);
    }

    private function _generateMixedMoof($videoTrack, $audioTrack, $baseMediaDecodeTime) {
        $videoTraf = MP4::traf($videoTrack, $baseMediaDecodeTime);
        $audioTraf = MP4::traf($audioTrack, $baseMediaDecodeTime);

        $sequenceNumber = min($videoTrack['sequenceNumber'], $audioTrack['sequenceNumber']);
        $mfhd = MP4::mfhd($sequenceNumber);

        return MP4::box(MP4::$types['moof'], $mfhd, $videoTraf, $audioTraf);
    }
}
?>