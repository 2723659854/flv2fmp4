<?php

class MP4 {
    public static $types = [];
    private static $constants = [];

    public static function init() {
        if (!empty(self::$types['avc1'])) {
            // Already initialized
            return;
        }
        
        self::$types = [
            'avc1' => [], 'avcC' => [], 'btrt' => [], 'dinf' => [], 'dref' => [],
            'esds' => [], 'ftyp' => [], 'hdlr' => [], 'mdat' => [], 'mdhd' => [],
            'mdia' => [], 'mfhd' => [], 'minf' => [], 'moof' => [], 'moov' => [],
            'mp4a' => [], 'mvex' => [], 'mvhd' => [], 'sdtp' => [], 'stbl' => [],
            'stco' => [], 'stsc' => [], 'stsd' => [], 'stsz' => [], 'stts' => [],
            'tfdt' => [], 'tfhd' => [], 'traf' => [], 'trak' => [], 'trun' => [],
            'trex' => [], 'tkhd' => [], 'vmhd' => [], 'smhd' => []
        ];

        foreach (self::$types as $name => $value) {
            self::$types[$name] = [
                ord($name[0]), ord($name[1]), ord($name[2]), ord($name[3])
            ];
        }

        self::$constants['FTYP'] = [
            0x69, 0x73, 0x6F, 0x6D,
            0x0, 0x0, 0x0, 0x1,
            0x69, 0x73, 0x6F, 0x6D,
            0x61, 0x76, 0x63, 0x31
        ];

        self::$constants['STSD_PREFIX'] = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x01
        ];

        self::$constants['STTS'] = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00
        ];

        self::$constants['STSC'] = self::$constants['STCO'] = self::$constants['STTS'];

        self::$constants['STSZ'] = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00
        ];

        self::$constants['HDLR_VIDEO'] = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x76, 0x69, 0x64, 0x65,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x56, 0x69, 0x64, 0x65,
            0x6F, 0x48, 0x61, 0x6E,
            0x64, 0x6C, 0x65, 0x72, 0x00
        ];

        self::$constants['HDLR_AUDIO'] = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x73, 0x6F, 0x75, 0x6E,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x53, 0x6F, 0x75, 0x6E,
            0x64, 0x48, 0x61, 0x6E,
            0x64, 0x6C, 0x65, 0x72, 0x00
        ];

        self::$constants['DREF'] = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x01,
            0x00, 0x00, 0x00, 0x0C,
            0x75, 0x72, 0x6C, 0x20,
            0x00, 0x00, 0x00, 0x01
        ];

        self::$constants['SMHD'] = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00
        ];

        self::$constants['VMHD'] = [
            0x00, 0x00, 0x00, 0x01,
            0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00
        ];
    }

    public static function box($type) {
        $size = 8;
        $result = [];
        $datas = func_get_args();
        array_shift($datas);
        
        $arrayCount = count($datas);
        for ($i = 0; $i < $arrayCount; $i++) {
            $size += count($datas[$i]);
        }

        $result = array_fill(0, $size, 0);
        $result[0] = ($size >> 24) & 0xFF;
        $result[1] = ($size >> 16) & 0xFF;
        $result[2] = ($size >> 8) & 0xFF;
        $result[3] = $size & 0xFF;

        for ($i = 0; $i < 4; $i++) {
            $result[4 + $i] = $type[$i];
        }

        $offset = 8;
        for ($i = 0; $i < $arrayCount; $i++) {
            foreach ($datas[$i] as $byte) {
                $result[$offset++] = $byte;
            }
        }

        return $result;
    }

    public static function generateInitSegment($meta) {
        if (!is_array($meta)) {
            $meta = [$meta];
        }
        $ftyp = self::box(self::$types['ftyp'], self::$constants['FTYP']);
        $moov = self::moov($meta);

        $result = array_merge($ftyp, $moov);
        return $result;
    }

    public static function moov($meta) {
        // Find video and audio metadata
        $videoMeta = null;
        $audioMeta = null;
        
        foreach ($meta as $m) {
            if (isset($m['codecWidth'])) {
                $videoMeta = $m;
            } else if (isset($m['sampleRate'])) {
                $audioMeta = $m;
            }
        }
        
        // Use video meta for mvhd if available, otherwise use audio
        $primaryMeta = $videoMeta ?: $audioMeta;
        $mvhd = self::mvhd($primaryMeta['timescale'], $primaryMeta['duration']);
        
        // Create tracks
        $vtrak = null;
        $atrak = null;
        
        if ($videoMeta) {
            $vtrak = self::trak($videoMeta);
        }
        if ($audioMeta) {
            $atrak = self::trak($audioMeta);
        }

        $mvex = self::mvex($meta);
        if ($vtrak && $atrak) {
            return self::box(self::$types['moov'], $mvhd, $vtrak, $atrak, $mvex);
        } else if ($vtrak) {
            return self::box(self::$types['moov'], $mvhd, $vtrak, $mvex);
        } else if ($atrak) {
            return self::box(self::$types['moov'], $mvhd, $atrak, $mvex);
        } else {
            return self::box(self::$types['moov'], $mvhd, $mvex);
        }
    }

    public static function mvhd($timescale, $duration) {
        return self::box(self::$types['mvhd'], [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            ($timescale >> 24) & 0xFF,
            ($timescale >> 16) & 0xFF,
            ($timescale >> 8) & 0xFF,
            $timescale & 0xFF,
            ($duration >> 24) & 0xFF,
            ($duration >> 16) & 0xFF,
            ($duration >> 8) & 0xFF,
            $duration & 0xFF,
            0x00, 0x01, 0x00, 0x00,
            0x01, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x40, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0xFF, 0xFF, 0xFF, 0xFF
        ]);
    }

    public static function trak($meta) {
        return self::box(self::$types['trak'], self::tkhd($meta), self::mdia($meta));
    }

    public static function tkhd($meta) {
        $trackId = $meta['id'];
        $duration = $meta['duration'];
        $width = isset($meta['presentWidth']) ? $meta['presentWidth'] : 0;
        $height = isset($meta['presentHeight']) ? $meta['presentHeight'] : 0;

        return self::box(self::$types['tkhd'], [
            0x00, 0x00, 0x00, 0x07,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            ($trackId >> 24) & 0xFF,
            ($trackId >> 16) & 0xFF,
            ($trackId >> 8) & 0xFF,
            $trackId & 0xFF,
            0x00, 0x00, 0x00, 0x00,
            ($duration >> 24) & 0xFF,
            ($duration >> 16) & 0xFF,
            ($duration >> 8) & 0xFF,
            $duration & 0xFF,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x40, 0x00, 0x00, 0x00,
            ($width >> 8) & 0xFF,
            $width & 0xFF,
            0x00, 0x00,
            ($height >> 8) & 0xFF,
            $height & 0xFF,
            0x00, 0x00
        ]);
    }

    public static function mdia($meta) {
        return self::box(self::$types['mdia'], self::mdhd($meta), self::hdlr($meta), self::minf($meta));
    }

    public static function mdhd($meta) {
        $timescale = $meta['timescale'];
        $duration = $meta['duration'];
        return self::box(self::$types['mdhd'], [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            ($timescale >> 24) & 0xFF,
            ($timescale >> 16) & 0xFF,
            ($timescale >> 8) & 0xFF,
            $timescale & 0xFF,
            ($duration >> 24) & 0xFF,
            ($duration >> 16) & 0xFF,
            ($duration >> 8) & 0xFF,
            $duration & 0xFF,
            0x55, 0xC4,
            0x00, 0x00
        ]);
    }

    public static function hdlr($meta) {
        if ($meta['type'] === 'audio') {
            $data = self::$constants['HDLR_AUDIO'];
        } else {
            $data = self::$constants['HDLR_VIDEO'];
        }
        return self::box(self::$types['hdlr'], $data);
    }

    public static function minf($meta) {
        if ($meta['type'] === 'audio') {
            $xmhd = self::box(self::$types['smhd'], self::$constants['SMHD']);
        } else {
            $xmhd = self::box(self::$types['vmhd'], self::$constants['VMHD']);
        }
        return self::box(self::$types['minf'], $xmhd, self::dinf(), self::stbl($meta));
    }

    public static function dinf() {
        return self::box(self::$types['dinf'],
            self::box(self::$types['dref'], self::$constants['DREF'])
        );
    }

    public static function stbl($meta) {
        return self::box(self::$types['stbl'],
            self::stsd($meta),
            self::box(self::$types['stts'], self::$constants['STTS']),
            self::box(self::$types['stsc'], self::$constants['STSC']),
            self::box(self::$types['stsz'], self::$constants['STSZ']),
            self::box(self::$types['stco'], self::$constants['STCO'])
        );
    }

    public static function stsd($meta) {
        if ($meta['type'] === 'audio') {
            return self::box(self::$types['stsd'], self::$constants['STSD_PREFIX'], self::mp4a($meta));
        } else {
            return self::box(self::$types['stsd'], self::$constants['STSD_PREFIX'], self::avc1($meta));
        }
    }

    public static function mp4a($meta) {
        $channelCount = $meta['channelCount'];
        $sampleRate = $meta['audioSampleRate'];

        $data = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x01,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, $channelCount,
            0x00, 0x10,
            0x00, 0x00, 0x00, 0x00,
            ($sampleRate >> 8) & 0xFF,
            $sampleRate & 0xFF,
            0x00, 0x00
        ];

        return self::box(self::$types['mp4a'], $data, self::esds($meta));
    }

    public static function esds($meta) {
        $config = $meta['config'];
        $configSize = count($config);
        $data = array_merge(
            [
                0x00, 0x00, 0x00, 0x00,
                0x03,
                0x17 + $configSize,
                0x00, 0x01,
                0x00,
                0x04,
                0x0F + $configSize,
                0x40,
                0x15,
                0x00, 0x00, 0x00,
                0x00, 0x00, 0x00, 0x00,
                0x00, 0x00, 0x00, 0x00,
                0x05
            ],
            [$configSize],
            $config,
            [0x06, 0x01, 0x02]
        );
        return self::box(self::$types['esds'], $data);
    }

    public static function avc1($meta) {
        $avcc = $meta['avcc'];
        $width = $meta['codecWidth'];
        $height = $meta['codecHeight'];

        $data = [
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x01,
            0x00, 0x00,  // pre_defined (2 bytes)
            0x00, 0x00,  // reserved (2 bytes)
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            ($width >> 8) & 0xFF,
            $width & 0xFF,
            ($height >> 8) & 0xFF,
            $height & 0xFF,
            0x00, 0x48, 0x00, 0x00,
            0x00, 0x48, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01,
            0x04,
            0x67, 0x31, 0x31, 0x31,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00,
            0x00, 0x18,
            0xFF, 0xFF
        ];

        return self::box(self::$types['avc1'], $data, self::box(self::$types['avcC'], $avcc));
    }

    public static function mvex($meta) {
        if (count($meta) > 1) {
            return self::box(self::$types['mvex'], self::trex($meta[0]), self::trex($meta[1]));
        } else {
            return self::box(self::$types['mvex'], self::trex($meta[0]));
        }
    }

    public static function trex($meta) {
        $trackId = $meta['id'];
        $data = [
            0x00, 0x00, 0x00, 0x00,
            ($trackId >> 24) & 0xFF,
            ($trackId >> 16) & 0xFF,
            ($trackId >> 8) & 0xFF,
            $trackId & 0xFF,
            0x00, 0x00, 0x00, 0x01,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01, 0x00, 0x01
        ];
        return self::box(self::$types['trex'], $data);
    }

    public static function moof($track, $baseMediaDecodeTime) {
        return self::box(self::$types['moof'], self::mfhd($track['sequenceNumber']), self::traf($track, $baseMediaDecodeTime));
    }

    public static function mfhd($sequenceNumber) {
        $data = [
            0x00, 0x00, 0x00, 0x00,
            ($sequenceNumber >> 24) & 0xFF,
            ($sequenceNumber >> 16) & 0xFF,
            ($sequenceNumber >> 8) & 0xFF,
            $sequenceNumber & 0xFF
        ];
        return self::box(self::$types['mfhd'], $data);
    }

    public static function traf($track, $baseMediaDecodeTime) {
        $trackId = $track['id'];

        $tfhd = self::box(self::$types['tfhd'], [
            0x00,  // version (1 byte)
            0x00, 0x00, 0x00,  // flags (3 bytes)
            ($trackId >> 24) & 0xFF,
            ($trackId >> 16) & 0xFF,
            ($trackId >> 8) & 0xFF,
            $trackId & 0xFF
        ]);

        $tfdt = self::box(self::$types['tfdt'], [
            0x00, 0x00, 0x00, 0x00,
            ($baseMediaDecodeTime >> 24) & 0xFF,
            ($baseMediaDecodeTime >> 16) & 0xFF,
            ($baseMediaDecodeTime >> 8) & 0xFF,
            $baseMediaDecodeTime & 0xFF
        ]);
        
        $sdtp = self::sdtp($track);
        $trun = self::trun($track, count($sdtp) + 20 + 16 + 8 + 16 + 8 + 8);

        return self::box(self::$types['traf'], $tfhd, $tfdt, $trun, $sdtp);
    }

    public static function sdtp($track) {
        $samples = isset($track['samples']) ? $track['samples'] : [];
        $sampleCount = count($samples);
        $data = array_fill(0, 4 + $sampleCount, 0);

        for ($i = 0; $i < $sampleCount; $i++) {
            $flags = $samples[$i]['flags'];
            $data[$i + 4] = ($flags['isLeading'] << 6) |
                           ($flags['dependsOn'] << 4) |
                           ($flags['isDependedOn'] << 2) |
                           ($flags['hasRedundancy']);
        }
        return self::box(self::$types['sdtp'], $data);
    }

    public static function trun($track, $offset) {
        $samples = isset($track['samples']) ? $track['samples'] : [];
        $sampleCount = count($samples);
        $dataSize = 12 + 16 * $sampleCount;
        $data = array_fill(0, $dataSize, 0);
        $offset += 8 + $dataSize;

        $data[0] = 0x00;
        $data[1] = 0x00;
        $data[2] = 0x0F;
        $data[3] = 0x01;
        $data[4] = ($sampleCount >> 24) & 0xFF;
        $data[5] = ($sampleCount >> 16) & 0xFF;
        $data[6] = ($sampleCount >> 8) & 0xFF;
        $data[7] = $sampleCount & 0xFF;
        $data[8] = ($offset >> 24) & 0xFF;
        $data[9] = ($offset >> 16) & 0xFF;
        $data[10] = ($offset >> 8) & 0xFF;
        $data[11] = $offset & 0xFF;

        for ($i = 0; $i < $sampleCount; $i++) {
            $duration = $samples[$i]['duration'];
            $size = $samples[$i]['size'];
            $flags = $samples[$i]['flags'];
            $cts = $samples[$i]['cts'];
            
            $base = 12 + 16 * $i;
            $data[$base] = ($duration >> 24) & 0xFF;
            $data[$base + 1] = ($duration >> 16) & 0xFF;
            $data[$base + 2] = ($duration >> 8) & 0xFF;
            $data[$base + 3] = $duration & 0xFF;
            $data[$base + 4] = ($size >> 24) & 0xFF;
            $data[$base + 5] = ($size >> 16) & 0xFF;
            $data[$base + 6] = ($size >> 8) & 0xFF;
            $data[$base + 7] = $size & 0xFF;
            $data[$base + 8] = ($flags['isLeading'] << 2) | $flags['dependsOn'];
            $data[$base + 9] = ($flags['isDependedOn'] << 6) | ($flags['hasRedundancy'] << 4) | $flags['isNonSync'];
            $data[$base + 10] = 0x00;
            $data[$base + 11] = 0x00;
            $data[$base + 12] = ($cts >> 24) & 0xFF;
            $data[$base + 13] = ($cts >> 16) & 0xFF;
            $data[$base + 14] = ($cts >> 8) & 0xFF;
            $data[$base + 15] = $cts & 0xFF;
        }
        return self::box(self::$types['trun'], $data);
    }

    public static function mdat($data) {
        return self::box(self::$types['mdat'], $data);
    }
}

MP4::init();
?>