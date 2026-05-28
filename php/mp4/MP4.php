<?php
/**
 * MP4 Remuxer - 对应 JavaScript 的 mp4remux.js
 * 严格按照 JavaScript 代码翻译，不使用 array_merge
 */
class MP4 {
    static $types = [];
    static $constants = [];
    
    static function init() {
        self::debugLog("MP4::init() 开始初始化");
        self::$types = [
            'avc1' => [0x61, 0x76, 0x63, 0x31], // 'a','v','c','1'
            'avcC' => [0x61, 0x76, 0x63, 0x43], // 'a','v','c','C'
            'btrt' => [0x62, 0x74, 0x72, 0x74], // 'b','t','r','t'
            'dinf' => [0x64, 0x69, 0x6E, 0x66], // 'd','i','n','f'
            'dref' => [0x64, 0x72, 0x65, 0x66], // 'd','r','e','f'
            'esds' => [0x65, 0x73, 0x64, 0x73], // 'e','s','d','s'
            'ftyp' => [0x66, 0x74, 0x79, 0x70], // 'f','t','y','p'
            'hdlr' => [0x68, 0x64, 0x6C, 0x72], // 'h','d','l','r'
            'mdat' => [0x6D, 0x64, 0x61, 0x74], // 'm','d','a','t'
            'mdhd' => [0x6D, 0x64, 0x68, 0x64], // 'm','d','h','d'
            'mdia' => [0x6D, 0x64, 0x69, 0x61], // 'm','d','i','a'
            'mfhd' => [0x6D, 0x66, 0x68, 0x64], // 'm','f','h','d'
            'minf' => [0x6D, 0x69, 0x6E, 0x66], // 'm','i','n','f'
            'moof' => [0x6D, 0x6F, 0x6F, 0x66], // 'm','o','o','f'
            'moov' => [0x6D, 0x6F, 0x6F, 0x76], // 'm','o','o','v'
            'mp4a' => [0x6D, 0x70, 0x34, 0x61], // 'm','p','4','a'
            'mvex' => [0x6D, 0x76, 0x65, 0x78], // 'm','v','e','x'
            'mvhd' => [0x6D, 0x76, 0x68, 0x64], // 'm','v','h','d'
            'sdtp' => [0x73, 0x64, 0x74, 0x70], // 's','d','t','p'
            'stbl' => [0x73, 0x74, 0x62, 0x6C], // 's','t','b','l'
            'stco' => [0x73, 0x74, 0x63, 0x6F], // 's','t','c','o'
            'stsc' => [0x73, 0x74, 0x73, 0x63], // 's','t','s','c'
            'stsd' => [0x73, 0x74, 0x73, 0x64], // 's','t','s','d'
            'stsz' => [0x73, 0x74, 0x73, 0x7A], // 's','t','s','z'
            'stts' => [0x73, 0x74, 0x74, 0x73], // 's','t','t','s'
            'tfdt' => [0x74, 0x66, 0x64, 0x74], // 't','f','d','t'
            'tfhd' => [0x74, 0x66, 0x68, 0x64], // 't','f','h','d'
            'traf' => [0x74, 0x72, 0x61, 0x66], // 't','r','a','f'
            'trak' => [0x74, 0x72, 0x61, 0x6B], // 't','r','a','k'
            'trun' => [0x74, 0x72, 0x75, 0x6E], // 't','r','u','n'
            'trex' => [0x74, 0x72, 0x65, 0x78], // 't','r','e','x'
            'tkhd' => [0x74, 0x6B, 0x68, 0x64], // 't','k','h','d'
            'vmhd' => [0x76, 0x6D, 0x68, 0x64], // 'v','m','h','d'
            'smhd' => [0x73, 0x6D, 0x68, 0x64]  // 's','m','h','d'
        ];
        
        foreach (self::$types as $name => $_) {
            self::$types[$name] = [
                ord($name[0]), ord($name[1]), ord($name[2]), ord($name[3])
            ];
        }
        
        self::$constants['FTYP'] = [
            0x69, 0x73, 0x6F, 0x6D, // major_brand: isom
            0x00, 0x00, 0x00, 0x01, // minor_version
            0x69, 0x73, 0x6F, 0x6D, // isom
            0x61, 0x76, 0x63, 0x31  // avc1
        ];
        
        self::$constants['STSD_PREFIX'] = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x01  // entry_count
        ];
        
        self::$constants['STTS'] = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00  // entry_count
        ];
        
        self::$constants['STSC'] = self::$constants['STCO'] = self::$constants['STTS'];
        
        self::$constants['STSZ'] = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00, // sample_size
            0x00, 0x00, 0x00, 0x00  // sample_count
        ];
        
        self::$constants['HDLR_VIDEO'] = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00, // pre_defined
            0x76, 0x69, 0x64, 0x65, // handler_type: 'vide'
            0x00, 0x00, 0x00, 0x00, // reserved
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x56, 0x69, 0x64, 0x65,
            0x6F, 0x48, 0x61, 0x6E,
            0x64, 0x6C, 0x65, 0x72, 0x00 // VideoHandler
        ];
        
        self::$constants['HDLR_AUDIO'] = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00, // pre_defined
            0x73, 0x6F, 0x75, 0x6E, // handler_type: 'soun'
            0x00, 0x00, 0x00, 0x00, // reserved
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x53, 0x6F, 0x75, 0x6E,
            0x64, 0x48, 0x61, 0x6E,
            0x64, 0x6C, 0x65, 0x72, 0x00 // SoundHandler
        ];
        
        self::$constants['DREF'] = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x01, // entry_count
            // 条目1: DataEntryUrlBox
            0x00, 0x00, 0x00, 0x0C, // entry_size = 12
            0x75, 0x72, 0x6C, 0x20, // type = 'url ' (注意空格在最后)
            0x00, 0x00, 0x00, 0x00  // version + flags = 0 (本地文件引用)
        ];
        
        self::$constants['SMHD'] = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00  // balance + reserved
        ];
        
        self::$constants['VMHD'] = [
            0x00, 0x00, 0x00, 0x01, // version + flags
            0x00, 0x00,             // graphicsmode
            0x00, 0x00, 0x00, 0x00, // opcolor
            0x00, 0x00
        ];
        self::debugLog("MP4::init() 初始化完成");
    }
    
    /**
     * 封装box
     * @param array $type - box类型
     * @param array ...$datas - box数据
     * @return array
     */
    static function box($type) {
        $args = func_get_args();
        $datas = array_slice($args, 1);
        
        $size = 8;
        foreach ($datas as $data) {
            $size += count($data);
        }
        
        self::debugLog("MP4::box() 创建box, type=" . chr($type[0]) . chr($type[1]) . chr($type[2]) . chr($type[3]) . ", size=$size");
        
        $result = array_fill(0, $size, 0);
        
        // 写入大小
        $result[0] = ($size >> 24) & 0xFF;
        $result[1] = ($size >> 16) & 0xFF;
        $result[2] = ($size >> 8) & 0xFF;
        $result[3] = $size & 0xFF;
        
        // 写入类型
        $result[4] = $type[0];
        $result[5] = $type[1];
        $result[6] = $type[2];
        $result[7] = $type[3];
        
        $offset = 8;
        foreach ($datas as $data) {
            foreach ($data as $byte) {
                $result[$offset++] = $byte;
            }
        }
        
        return $result;
    }
    
    /**
     * 创建ftyp&moov
     */
    static function generateInitSegment($meta) {
        self::debugLog("MP4::generateInitSegment() 开始生成初始化段");
        if (!is_array($meta)) {
            $meta = [$meta];
        }
        
        $ftyp = self::box(self::$types['ftyp'], self::$constants['FTYP']);
        $moov = self::moov($meta);
        
        // 不使用 array_merge，手动合并
        $result = [];
        foreach ($ftyp as $byte) {
            $result[] = $byte;
        }
        foreach ($moov as $byte) {
            $result[] = $byte;
        }
        
        self::debugLog("MP4::generateInitSegment() 完成，总大小=" . count($result));
        return $result;
    }
    
    /**
     * Movie metadata box
     */
    static function moov($meta) {
        self::debugLog("MP4::moov() 创建moov box");
        $mvhd = self::mvhd($meta[0]['timescale'], $meta[0]['duration']);
        $vtrak = self::trak($meta[0]);
        $atrak = null;
        
        if (count($meta) > 1) {
            $atrak = self::trak($meta[1]);
        }
        
        $mvex = self::mvex($meta);
        
        if (count($meta) > 1) {
            return self::box(self::$types['moov'], $mvhd, $vtrak, $atrak, $mvex);
        } else {
            return self::box(self::$types['moov'], $mvhd, $vtrak, $mvex);
        }
    }
    
    /**
     * Movie header box
     */
    static function mvhd($timescale, $duration) {
        self::debugLog("MP4::mvhd() 创建mvhd box, timescale=$timescale, duration=$duration");
        $data = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00, // creation_time
            0x00, 0x00, 0x00, 0x00, // modification_time
            ($timescale >> 24) & 0xFF,
            ($timescale >> 16) & 0xFF,
            ($timescale >> 8) & 0xFF,
            $timescale & 0xFF,
            ($duration >> 24) & 0xFF,
            ($duration >> 16) & 0xFF,
            ($duration >> 8) & 0xFF,
            $duration & 0xFF,
            0x00, 0x01, 0x00, 0x00, // preferred rate
            0x01, 0x00, 0x00, 0x00, // preferred volume
            0x00, 0x00, 0x00, 0x00, // reserved
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01, 0x00, 0x00, // matrix
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x40, 0x00, 0x00, 0x00, // end matrix
            0x00, 0x00, 0x00, 0x00, // pre_defined
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0xFF, 0xFF, 0xFF, 0xFF  // next_track_ID
        ];
        return self::box(self::$types['mvhd'], $data);
    }
    
    /**
     * Track box
     */
    static function trak($meta) {
        return self::box(self::$types['trak'], self::tkhd($meta), self::mdia($meta));
    }
    
    /**
     * Track header box
     */
    static function tkhd($meta) {
        $trackId = $meta['id'];
        $duration = $meta['duration'];
        $width = isset($meta['presentWidth']) ? $meta['presentWidth'] : 0;
        $height = isset($meta['presentHeight']) ? $meta['presentHeight'] : 0;
        
        self::debugLog("MP4::tkhd() 创建tkhd box, trackId=$trackId, duration=$duration, width=$width, height=$height");
        
        $data = [
            0x00, 0x00, 0x00, 0x07, // version + flags
            0x00, 0x00, 0x00, 0x00, // creation_time
            0x00, 0x00, 0x00, 0x00, // modification_time
            ($trackId >> 24) & 0xFF,
            ($trackId >> 16) & 0xFF,
            ($trackId >> 8) & 0xFF,
            $trackId & 0xFF,
            0x00, 0x00, 0x00, 0x00, // reserved
            ($duration >> 24) & 0xFF,
            ($duration >> 16) & 0xFF,
            ($duration >> 8) & 0xFF,
            $duration & 0xFF,
            0x00, 0x00, 0x00, 0x00, // reserved
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00, // layer + alternate_group
            0x00, 0x00, 0x00, 0x00, // volume + reserved
            0x00, 0x01, 0x00, 0x00, // matrix
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x01, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x40, 0x00, 0x00, 0x00, // end matrix
            ($width >> 8) & 0xFF,
            $width & 0xFF,
            0x00, 0x00,
            ($height >> 8) & 0xFF,
            $height & 0xFF,
            0x00, 0x00
        ];
        
        return self::box(self::$types['tkhd'], $data);
    }
    
    /**
     * Media box
     */
    static function mdia($meta) {
        return self::box(self::$types['mdia'], self::mdhd($meta), self::hdlr($meta), self::minf($meta));
    }
    
    /**
     * Media header box
     */
    static function mdhd($meta) {
        $timescale = $meta['timescale'];
        $duration = $meta['duration'];
        
        $data = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00, // creation_time
            0x00, 0x00, 0x00, 0x00, // modification_time
            ($timescale >> 24) & 0xFF,
            ($timescale >> 16) & 0xFF,
            ($timescale >> 8) & 0xFF,
            $timescale & 0xFF,
            ($duration >> 24) & 0xFF,
            ($duration >> 16) & 0xFF,
            ($duration >> 8) & 0xFF,
            $duration & 0xFF,
            0x55, 0xC4,             // language
            0x00, 0x00              // pre_defined
        ];
        
        return self::box(self::$types['mdhd'], $data);
    }
    
    /**
     * Media handler reference box
     */
    static function hdlr($meta) {
        if ($meta['type'] === 'audio') {
            $data = self::$constants['HDLR_AUDIO'];
        } else {
            $data = self::$constants['HDLR_VIDEO'];
        }
        return self::box(self::$types['hdlr'], $data);
    }
    
    /**
     * Media information box
     */
    static function minf($meta) {
        if ($meta['type'] === 'audio') {
            $xmhd = self::box(self::$types['smhd'], self::$constants['SMHD']);
        } else {
            $xmhd = self::box(self::$types['vmhd'], self::$constants['VMHD']);
        }
        return self::box(self::$types['minf'], $xmhd, self::dinf(), self::stbl($meta));
    }
    
    /**
     * Data Information Box
     */
    static function dinf() {
        return self::box(self::$types['dinf'], self::box(self::$types['dref'], self::$constants['DREF']));
    }
    
    /**
     * Sample Table Box
     */
    static function stbl($meta) {
        return self::box(self::$types['stbl'],
            self::stsd($meta),
            self::box(self::$types['stts'], self::$constants['STTS']),
            self::box(self::$types['stsc'], self::$constants['STSC']),
            self::box(self::$types['stsz'], self::$constants['STSZ']),
            self::box(self::$types['stco'], self::$constants['STCO'])
        );
    }
    
    /**
     * Sample Description Box
     */
    static function stsd($meta) {
        if ($meta['type'] === 'audio') {
            return self::box(self::$types['stsd'], self::$constants['STSD_PREFIX'], self::mp4a($meta));
        } else {
            return self::box(self::$types['stsd'], self::$constants['STSD_PREFIX'], self::avc1($meta));
        }
    }
    
    /**
     * Audio sample entry
     */
    static function mp4a($meta) {
        $channelCount = $meta['channelCount'];
        $sampleRate = $meta['audioSampleRate'];
        
        $data = [
            0x00, 0x00, 0x00, 0x00, // reserved
            0x00, 0x00, 0x00, 0x01, // reserved + data_reference_index
            0x00, 0x00, 0x00, 0x00, // reserved
            0x00, 0x00, 0x00, 0x00,
            0x00, $channelCount,     // channelCount
            0x00, 0x10,             // sampleSize
            0x00, 0x00, 0x00, 0x00, // reserved
            ($sampleRate >> 8) & 0xFF,
            $sampleRate & 0xFF,
            0x00, 0x00
        ];
        
        return self::box(self::$types['mp4a'], $data, self::esds($meta));
    }
    
    /**
     * ES descriptor box
     */
    static function esds($meta) {
        $config = $meta['config'];
        $configSize = count($config);
        
        $data = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x03,                    // descriptor_type
            0x17 + $configSize,      // length
            0x00, 0x01,             // es_id
            0x00,                    // stream_priority
            0x04,                    // descriptor_type
            0x0F + $configSize,      // length
            0x40,                    // codec
            0x15,                    // stream_type
            0x00, 0x00, 0x00,       // buffer_size
            0x00, 0x00, 0x00, 0x00, // maxBitrate
            0x00, 0x00, 0x00, 0x00, // avgBitrate
            0x05,                    // descriptor_type
            $configSize
        ];
        
        // 添加 config
        foreach ($config as $byte) {
            $data[] = $byte;
        }
        
        // 添加 GASpecificConfig
        $data[] = 0x06;
        $data[] = 0x01;
        $data[] = 0x02;
        
        return self::box(self::$types['esds'], $data);
    }
    
    /**
     * Video sample entry (avc1)
     */
    static function avc1($meta) {
        $avcc = $meta['avcc'];
        $width = $meta['codecWidth'];
        $height = $meta['codecHeight'];
        
        $data = [
            0x00, 0x00, 0x00, 0x00, // reserved
            0x00, 0x00, 0x00, 0x01, // reserved + data_reference_index
            0x00, 0x00, 0x00, 0x00, // pre_defined
            0x00, 0x00, 0x00, 0x00, // pre_defined
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            ($width >> 8) & 0xFF,
            $width & 0xFF,
            ($height >> 8) & 0xFF,
            $height & 0xFF,
            0x00, 0x48, 0x00, 0x00, // horizresolution
            0x00, 0x48, 0x00, 0x00, // vertresolution
            0x00, 0x00, 0x00, 0x00, // reserved
            0x00, 0x01,             // frame_count
            0x04,                   // strlen compressorname
            0x67, 0x31, 0x31, 0x31, // compressorname
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00,
            0x00, 0x18,             // depth
            0xFF, 0xFF              // pre_defined = -1
        ];
        
        return self::box(self::$types['avc1'], $data, self::box(self::$types['avcC'], $avcc));
    }
    
    /**
     * Movie Extends box
     */
    static function mvex($meta) {
        if (count($meta) > 1) {
            return self::box(self::$types['mvex'], self::trex($meta[0]), self::trex($meta[1]));
        } else {
            return self::box(self::$types['mvex'], self::trex($meta[0]));
        }
    }
    
    /**
     * Track Extends box
     */
    static function trex($meta) {
        $trackId = $meta['id'];
        
        $data = [
            0x00, 0x00, 0x00, 0x00, // version + flags
            ($trackId >> 24) & 0xFF,
            ($trackId >> 16) & 0xFF,
            ($trackId >> 8) & 0xFF,
            $trackId & 0xFF,
            0x00, 0x00, 0x00, 0x01, // default_sample_description_index
            0x00, 0x00, 0x00, 0x00, // default_sample_duration
            0x00, 0x00, 0x00, 0x00, // default_sample_size
            0x00, 0x01, 0x00, 0x01  // default_sample_flags
        ];
        
        return self::box(self::$types['trex'], $data);
    }
    
    /**
     * Movie fragment box
     */
    static function moof($track, $baseMediaDecodeTime) {
        return self::box(self::$types['moof'], self::mfhd($track['sequenceNumber']), self::traf($track, $baseMediaDecodeTime));
    }
    
    /**
     * Movie fragment header box
     */
    static function mfhd($sequenceNumber) {
        $data = [
            0x00, 0x00, 0x00, 0x00,
            ($sequenceNumber >> 24) & 0xFF,
            ($sequenceNumber >> 16) & 0xFF,
            ($sequenceNumber >> 8) & 0xFF,
            $sequenceNumber & 0xFF
        ];
        
        return self::box(self::$types['mfhd'], $data);
    }
    
    /**
     * Track fragment box
     */
    static function traf($track, $baseMediaDecodeTime) {
        $trackId = $track['id'];

        // tfhd flags: 默认不设置 base-data-offset-present
        // 因为我们使用 trun 的 data_offset 来指定数据偏移
        $tfhdFlags = 0x000000;

        // 构建 tfhd 数据
        $tfhdData = [
            0x00, ($tfhdFlags >> 16) & 0xFF, ($tfhdFlags >> 8) & 0xFF, $tfhdFlags & 0xFF, // version & flags
            ($trackId >> 24) & 0xFF,
            ($trackId >> 16) & 0xFF,
            ($trackId >> 8) & 0xFF,
            $trackId & 0xFF
        ];

        $tfhd = self::box(self::$types['tfhd'], $tfhdData);

        $tfdt = self::box(self::$types['tfdt'], [
            0x00, 0x00, 0x00, 0x00, // version & flags
            ($baseMediaDecodeTime >> 24) & 0xFF,
            ($baseMediaDecodeTime >> 16) & 0xFF,
            ($baseMediaDecodeTime >> 8) & 0xFF,
            $baseMediaDecodeTime & 0xFF
        ]);

        $sdtp = self::sdtp($track);

        // trun 的 offset 参数：第一个样本数据相对于 moof 开始处的偏移
        $moofHeaderSize = 8;
        $mfhdSize = 16;
        $trafHeaderSize = 8;
        $tfhdSize = count($tfhd);
        $tfdtSize = 16;
        $trunHeaderSize = 16;
        $sdtpSize = count($sdtp);
        $firstSampleDataOffset = $moofHeaderSize + $mfhdSize + $trafHeaderSize + $tfhdSize + $tfdtSize + $trunHeaderSize + $sdtpSize;

        $trun = self::trun($track, $firstSampleDataOffset);

        return self::box(self::$types['traf'], $tfhd, $tfdt, $trun, $sdtp);
    }
    
    /**
     * Sample Dependency Type box
     */
    static function sdtp($track) {
        $samples = isset($track['samples']) ? $track['samples'] : [];
        $sampleCount = count($samples);
        $data = array_fill(0, 4 + $sampleCount, 0);
        
        for ($i = 0; $i < $sampleCount; $i++) {
            $flags = $samples[$i]['flags'];
            $data[$i + 4] = ($flags['isLeading'] << 6) | 
                           ($flags['dependsOn'] << 4) | 
                           ($flags['isDependedOn'] << 2) | 
                           $flags['hasRedundancy'];
        }
        
        return self::box(self::$types['sdtp'], $data);
    }
    
    /**
     * Track fragment run box
     */
    static function trun($track, $offset) {
        $samples = isset($track['samples']) ? $track['samples'] : [];
        $sampleCount = count($samples);
        $dataSize = 12 + 16 * $sampleCount;
        $data = array_fill(0, $dataSize, 0);

        // trun flags: sample duration + sample size + sample flags + sample composition time offset + data-offset
        $data[0] = 0x00;
        $data[1] = 0x00;
        $data[2] = 0x01;  // 0x0100 = 256 = data-offset flag
        $data[3] = 0x0F;  // 0x000F = 15 = duration + size + flags + cts

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
            
            $idx = 12 + 16 * $i;
            $data[$idx] = ($duration >> 24) & 0xFF;
            $data[$idx + 1] = ($duration >> 16) & 0xFF;
            $data[$idx + 2] = ($duration >> 8) & 0xFF;
            $data[$idx + 3] = $duration & 0xFF;
            
            $data[$idx + 4] = ($size >> 24) & 0xFF;
            $data[$idx + 5] = ($size >> 16) & 0xFF;
            $data[$idx + 6] = ($size >> 8) & 0xFF;
            $data[$idx + 7] = $size & 0xFF;
            
            $data[$idx + 8] = ($flags['isLeading'] << 2) | $flags['dependsOn'];
            $data[$idx + 9] = ($flags['isDependedOn'] << 6) | ($flags['hasRedundancy'] << 4) | $flags['isNonSync'];
            $data[$idx + 10] = 0x00;
            $data[$idx + 11] = 0x00;
            
            $data[$idx + 12] = ($cts >> 24) & 0xFF;
            $data[$idx + 13] = ($cts >> 16) & 0xFF;
            $data[$idx + 14] = ($cts >> 8) & 0xFF;
            $data[$idx + 15] = $cts & 0xFF;
        }
        
        return self::box(self::$types['trun'], $data);
    }
    
    /**
     * Media data box
     */
    static function mdat($data) {
        return self::box(self::$types['mdat'], $data);
    }
    
    /**
     * 调试日志
     */
    static function debugLog($message) {
        static $debug = true;
        if ($debug) {
            echo "[MP4] $message\n";
        }
    }
}

// 初始化
MP4::init();
