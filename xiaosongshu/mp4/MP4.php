<?php

namespace xiaosongshu\mp4;

/**
 * mp4remux.php
 *
 * Ported from mp4remux.js (Bilibili Flv.js)
 * Generates MP4 boxes (ftyp, moov, moof, mdat, etc.) for fragmented MP4 muxing.
 */

class MP4
{
    public static $types = [];
    public static $constants = [];

    /**
     * Initialize static properties (equivalent to MP4.init() in JS).
     */
    public static function init()
    {
        // Define box types as arrays of character codes (or as strings, stored as string for convenience)
        $typeNames = [
            'avc1', 'avcC', 'btrt', 'dinf', 'dref', 'esds', 'ftyp', 'hdlr',
            'mdat', 'mdhd', 'mdia', 'mfhd', 'minf', 'moof', 'moov', 'mp4a',
            'mvex', 'mvhd', 'sdtp', 'stbl', 'stco', 'stsc', 'stsd', 'stsz',
            'stts', 'tfdt', 'tfhd', 'traf', 'trak', 'trun', 'trex', 'tkhd',
            'vmhd', 'smhd'
        ];
        foreach ($typeNames as $name) {
            // Store as 4-character string (which will be packed into box header)
            self::$types[$name] = $name;
        }

        // Constants: all Uint8Array in JS become binary strings here
        self::$constants['FTYP'] = pack('C*',
            0x69, 0x73, 0x6F, 0x6D, // major_brand: isom
            0x00, 0x00, 0x00, 0x01, // minor_version: 0x01
            0x69, 0x73, 0x6F, 0x6D, // isom
            0x61, 0x76, 0x63, 0x31  // avc1
        );

        self::$constants['STSD_PREFIX'] = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version(0) + flags
            0x00, 0x00, 0x00, 0x01  // entry_count = 1
        );

        self::$constants['STTS'] = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00  // entry_count = 0
        );

        self::$constants['STSC'] = self::$constants['STCO'] = self::$constants['STTS'];

        self::$constants['STSZ'] = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00, // sample_size = 0
            0x00, 0x00, 0x00, 0x00  // sample_count = 0
        );

        self::$constants['HDLR_VIDEO'] = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00, // pre_defined
            0x76, 0x69, 0x64, 0x65, // handler_type: 'vide'
            0x00, 0x00, 0x00, 0x00, // reserved *3
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x56, 0x69, 0x64, 0x65,
            0x6F, 0x48, 0x61, 0x6E,
            0x64, 0x6C, 0x65, 0x72, 0x00
        );

        self::$constants['HDLR_AUDIO'] = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00, // pre_defined
            0x73, 0x6F, 0x75, 0x6E, // handler_type: 'soun'
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x53, 0x6F, 0x75, 0x6E,
            0x64, 0x48, 0x61, 0x6E,
            0x64, 0x6C, 0x65, 0x72, 0x00
        );

        self::$constants['DREF'] = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x01, // entry_count = 1
            0x00, 0x00, 0x00, 0x0C, // entry_size
            0x75, 0x72, 0x6C, 0x20, // type 'url '
            0x00, 0x00, 0x00, 0x01  // version + flags
        );

        self::$constants['SMHD'] = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version + flags
            0x00, 0x00, 0x00, 0x00  // balance + reserved
        );

        self::$constants['VMHD'] = pack('C*',
            0x00, 0x00, 0x00, 0x01, // version + flags
            0x00, 0x00,             // graphicsmode
            0x00, 0x00, 0x00, 0x00, // opcolor
            0x00, 0x00              // reserved
        );
    }

    /**
     * Build a generic MP4 box.
     * @param string|array $type Box type (4-character string or array of 4 integers)
     * @param string ...$datas Binary strings of nested boxes or data
     * @return string Binary string of the complete box
     */
    public static function box($type, ...$datas)
    {
        // Convert type to 4-character string if array given
        if (is_array($type)) {
            $typeStr = '';
            foreach ($type as $c) {
                $typeStr .= chr($c);
            }
        } else {
            $typeStr = substr($type, 0, 4);
        }

        $size = 8; // box header size
        foreach ($datas as $data) {
            $size += strlen($data);
        }

        $result = pack('N', $size);          // size (big-endian)
        $result .= $typeStr;                 // type
        foreach ($datas as $data) {
            $result .= $data;
        }
        return $result;
    }

    /**
     * Generate initialization segment (ftyp + moov)
     * @param array $meta Array of track metadata (audio and/or video)
     * @return string Binary string of init segment
     */
    public static function generateInitSegment($meta)
    {
        if (!is_array($meta) || !isset($meta[0])) {
            $meta = [$meta];
        }
        $ftyp = self::box(self::$types['ftyp'], self::$constants['FTYP']);
        $moov = self::moov($meta);
        return $ftyp . $moov;
    }

    /**
     * Build Movie Box (moov)
     * @param array $meta Array of track metadata
     * @return string
     */
    public static function moov($meta)
    {
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
     * Movie Header Box (mvhd)
     * @param int $timescale
     * @param int $duration
     * @return string
     */
    public static function mvhd($timescale, $duration)
    {
        $data = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version+flags
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
            0x00, 0x01, 0x00, 0x00, // rate 1.0
            0x01, 0x00, 0x00, 0x00, // volume 1.0 + reserved
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
            0xFF, 0xFF, 0xFF, 0xFF  // next_track_ID
        );
        return self::box(self::$types['mvhd'], $data);
    }

    /**
     * Track Box (trak)
     * @param array $meta Track metadata
     * @return string
     */
    public static function trak($meta)
    {
        return self::box(self::$types['trak'], self::tkhd($meta), self::mdia($meta));
    }

    /**
     * Track Header Box (tkhd)
     * @param array $meta Track metadata
     * @return string
     */
    public static function tkhd($meta)
    {
        $trackId = $meta['id'];
        $duration = $meta['duration'];
        $width = $meta['presentWidth'];
        $height = $meta['presentHeight'];

        $data = pack('C*',
            0x00, 0x00, 0x00, 0x07, // version+flags (track enabled, in movie, in preview)
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
        );
        return self::box(self::$types['tkhd'], $data);
    }

    /**
     * Media Box (mdia)
     * @param array $meta Track metadata
     * @return string
     */
    public static function mdia($meta)
    {
        return self::box(self::$types['mdia'], self::mdhd($meta), self::hdlr($meta), self::minf($meta));
    }

    /**
     * Media Header Box (mdhd)
     * @param array $meta
     * @return string
     */
    public static function mdhd($meta)
    {
        $timescale = $meta['timescale'];
        $duration = $meta['duration'];
        $data = pack('C*',
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
            0x55, 0xC4, // language: und
            0x00, 0x00
        );
        return self::box(self::$types['mdhd'], $data);
    }

    /**
     * Handler Reference Box (hdlr)
     * @param array $meta
     * @return string
     */
    public static function hdlr($meta)
    {
        if ($meta['type'] === 'audio') {
            $data = self::$constants['HDLR_AUDIO'];
        } else {
            $data = self::$constants['HDLR_VIDEO'];
        }
        return self::box(self::$types['hdlr'], $data);
    }

    /**
     * Media Information Box (minf)
     * @param array $meta
     * @return string
     */
    public static function minf($meta)
    {
        if ($meta['type'] === 'audio') {
            $xmhd = self::box(self::$types['smhd'], self::$constants['SMHD']);
        } else {
            $xmhd = self::box(self::$types['vmhd'], self::$constants['VMHD']);
        }
        return self::box(self::$types['minf'], $xmhd, self::dinf(), self::stbl($meta));
    }

    /**
     * Data Information Box (dinf)
     * @return string
     */
    public static function dinf()
    {
        return self::box(self::$types['dinf'], self::box(self::$types['dref'], self::$constants['DREF']));
    }

    /**
     * Sample Table Box (stbl)
     * @param array $meta
     * @return string
     */
    public static function stbl($meta)
    {
        return self::box(self::$types['stbl'],
            self::stsd($meta),
            self::box(self::$types['stts'], self::$constants['STTS']),
            self::box(self::$types['stsc'], self::$constants['STSC']),
            self::box(self::$types['stsz'], self::$constants['STSZ']),
            self::box(self::$types['stco'], self::$constants['STCO'])
        );
    }

    /**
     * Sample Description Box (stsd)
     * @param array $meta
     * @return string
     */
    public static function stsd($meta)
    {
        if ($meta['type'] === 'audio') {
            return self::box(self::$types['stsd'], self::$constants['STSD_PREFIX'], self::mp4a($meta));
        } else {
            return self::box(self::$types['stsd'], self::$constants['STSD_PREFIX'], self::avc1($meta));
        }
    }

    /**
     * MP4 Audio Sample Entry (mp4a)
     * @param array $meta
     * @return string
     */
    public static function mp4a($meta)
    {
        $channelCount = $meta['channelCount'];
        $sampleRate = $meta['audioSampleRate'];

        $data = pack('C*',
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
        );
        return self::box(self::$types['mp4a'], $data, self::esds($meta));
    }

    /**
     * Elementary Stream Descriptor Box (esds)
     * @param array $meta
     * @return string
     */
    public static function esds($meta)
    {
        $config = $meta['config']; // binary string of AudioSpecificConfig
        $configSize = strlen($config);

        $data = pack('C*',
            0x00, 0x00, 0x00, 0x00, // version+flags
            0x03,                   // descriptor_type: MP4ESDescrTag
            0x17 + $configSize,     // length
            0x00, 0x01,             // es_id
            0x00,                   // stream_priority
            0x04,                   // descriptor_type: MP4DecConfigDescrTag
            0x0F + $configSize,     // length
            0x40,                   // codec: mpeg4_audio
            0x15,                   // stream_type: Audio
            0x00, 0x00, 0x00,       // buffer_size
            0x00, 0x00, 0x00, 0x00, // maxBitrate
            0x00, 0x00, 0x00, 0x00, // avgBitrate
            0x05                    // descriptor_type: MP4DecSpecificDescrTag
        );
        $data .= pack('C', $configSize);
        $data .= $config;
        $data .= pack('C*', 0x06, 0x01, 0x02); // GASpecificConfig

        return self::box(self::$types['esds'], $data);
    }

    /**
     * AVC Video Sample Entry (avc1)
     * @param array $meta
     * @return string
     */
    public static function avc1($meta)
    {
        $avcc = $meta['avcc']; // binary string of AVCDecoderConfigurationRecord
        $width = $meta['codecWidth'];
        $height = $meta['codecHeight'];

        $data = pack('C*',
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x01,
            0x00, 0x00, 0x00, 0x00,
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
            0x00, 0x01,             // frame_count = 1
            0x04,                   // compressorname length
            0x67, 0x31, 0x31, 0x31, // 'g111'
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00,
            0x00, 0x18,             // depth
            0xFF, 0xFF              // pre_defined = -1
        );

        $avcCBox = self::box(self::$types['avcC'], $avcc);
        return self::box(self::$types['avc1'], $data, $avcCBox);
    }

    /**
     * Movie Extends Box (mvex)
     * @param array $meta Array of track metadata
     * @return string
     */
    public static function mvex($meta)
    {
        if (count($meta) > 1) {
            return self::box(self::$types['mvex'], self::trex($meta[0]), self::trex($meta[1]));
        } else {
            return self::box(self::$types['mvex'], self::trex($meta[0]));
        }
    }

    /**
     * Track Extends Box (trex)
     * @param array $meta Track metadata
     * @return string
     */
    public static function trex($meta)
    {
        $trackId = $meta['id'];
        $data = pack('C*',
            0x00, 0x00, 0x00, 0x00,
            ($trackId >> 24) & 0xFF,
            ($trackId >> 16) & 0xFF,
            ($trackId >> 8) & 0xFF,
            $trackId & 0xFF,
            0x00, 0x00, 0x00, 0x01, // default_sample_description_index
            0x00, 0x00, 0x00, 0x00, // default_sample_duration
            0x00, 0x00, 0x00, 0x00, // default_sample_size
            0x00, 0x01, 0x00, 0x01  // default_sample_flags
        );
        // For audio, the last flag might be different; original JS had commented code, we keep as is.
        return self::box(self::$types['trex'], $data);
    }

    /**
     * Movie Fragment Box (moof)
     * @param array $track Track data with samples, sequenceNumber, etc.
     * @param int $baseMediaDecodeTime
     * @return string
     */
    public static function moof($track, $baseMediaDecodeTime)
    {
        return self::box(self::$types['moof'],
            self::mfhd($track['sequenceNumber']),
            self::traf($track, $baseMediaDecodeTime)
        );
    }

    /**
     * Movie Fragment Header Box (mfhd)
     * @param int $sequenceNumber
     * @return string
     */
    public static function mfhd($sequenceNumber)
    {
        $data = pack('C*',
            0x00, 0x00, 0x00, 0x00,
            ($sequenceNumber >> 24) & 0xFF,
            ($sequenceNumber >> 16) & 0xFF,
            ($sequenceNumber >> 8) & 0xFF,
            $sequenceNumber & 0xFF
        );
        return self::box(self::$types['mfhd'], $data);
    }

    /**
     * Track Fragment Box (traf)
     * @param array $track
     * @param int $baseMediaDecodeTime
     * @return string
     */
    public static function traf($track, $baseMediaDecodeTime)
    {
        $trackId = $track['id'];
        $tfhd = self::box(self::$types['tfhd'], pack('C*',
            0x00, 0x00, 0x00, 0x00,
            ($trackId >> 24) & 0xFF,
            ($trackId >> 16) & 0xFF,
            ($trackId >> 8) & 0xFF,
            $trackId & 0xFF
        ));
        $tfdt = self::box(self::$types['tfdt'], pack('C*',
            0x00, 0x00, 0x00, 0x00,
            ($baseMediaDecodeTime >> 24) & 0xFF,
            ($baseMediaDecodeTime >> 16) & 0xFF,
            ($baseMediaDecodeTime >> 8) & 0xFF,
            $baseMediaDecodeTime & 0xFF
        ));
        $sdtp = self::sdtp($track);
        $trun = self::trun($track, strlen($sdtp) + 16 + 16 + 8 + 16 + 8 + 8);
        return self::box(self::$types['traf'], $tfhd, $tfdt, $trun, $sdtp);
    }

    /**
     * Sample Dependency Type Box (sdtp)
     * @param array $track
     * @return string
     */
    public static function sdtp($track)
    {
        $samples = isset($track['samples']) ? $track['samples'] : [];
        $sampleCount = count($samples);
        $data = pack('C*', 0x00, 0x00, 0x00, 0x00); // version+flags
        for ($i = 0; $i < $sampleCount; $i++) {
            $flags = $samples[$i]['flags'];
            $byte = (($flags['isLeading'] << 6) |
                ($flags['dependsOn'] << 4) |
                ($flags['isDependedOn'] << 2) |
                ($flags['hasRedundancy']));
            $data .= pack('C', $byte);
        }
        return self::box(self::$types['sdtp'], $data);
    }

    /**
     * Track Fragment Run Box (trun)
     * @param array $track
     * @param int $offset Data offset (relative to beginning of moof)
     * @return string
     */
    public static function trun($track, $offset)
    {
        $samples = isset($track['samples']) ? $track['samples'] : [];
        $sampleCount = count($samples);
        $dataSize = 12 + 16 * $sampleCount;
        $data = pack('C*',
            0x00, 0x00, 0x0F, 0x01, // version+flags
            ($sampleCount >> 24) & 0xFF,
            ($sampleCount >> 16) & 0xFF,
            ($sampleCount >> 8) & 0xFF,
            $sampleCount & 0xFF,
            ($offset >> 24) & 0xFF,
            ($offset >> 16) & 0xFF,
            ($offset >> 8) & 0xFF,
            $offset & 0xFF
        );
        for ($i = 0; $i < $sampleCount; $i++) {
            $sample = $samples[$i];
            $duration = $sample['duration'];
            $size = $sample['size'];
            $flags = $sample['flags'];
            $cts = $sample['cts'];

            $data .= pack('N', $duration); // sample_duration
            $data .= pack('N', $size);    // sample_size
            // sample_flags (2 bytes)
            $flagsHigh = (($flags['isLeading'] << 2) | $flags['dependsOn']);
            $flagsLow = (($flags['isDependedOn'] << 6) | ($flags['hasRedundancy'] << 4) | $flags['isNonSync']);
            $data .= pack('C*', $flagsHigh, $flagsLow);
            $data .= pack('n', 0); // sample_degradation_priority
            $data .= pack('N', $cts); // sample_composition_time_offset
        }
        return self::box(self::$types['trun'], $data);
    }

    /**
     * Media Data Box (mdat) - simply box with type 'mdat' and data
     * @param string $data
     * @return string
     */
    public static function mdat($data)
    {
        return self::box(self::$types['mdat'], $data);
    }
}
