<?php

namespace xiaosongshu\flv;

/**
 * 翻译自 flv.js 的 sps-parser.js
 * 解析 H.264 序列参数集 (SPS)，提取视频编码信息。
 *
 * 依赖：ExpGolomb 类 (指数哥伦布解码器，需事先引入)
 */
class SPSParser
{
    /**
     * 将 EBSP (Encapsulated Byte Sequence Payload) 转换为 RBSP (Raw Byte Sequence Payload)
     * 移除 0x03 转义字节 (00 00 03 → 00 00)
     *
     * @param string $uint8array 原始字节字符串
     * @return string
     */
    public static function _ebsp2rbsp($uint8array)
    {
        $src = $uint8array;
        $src_length = strlen($src);
        $dst = '';

        for ($i = 0; $i < $src_length; $i++) {
            if ($i >= 2) {
                // 当出现 00 00 03 时，跳过 0x03
                if (ord($src[$i]) === 0x03 && ord($src[$i - 1]) === 0x00 && ord($src[$i - 2]) === 0x00) {
                    continue;
                }
            }
            $dst .= $src[$i];
        }

        return $dst;
    }

    /**
     * 解析 SPS NAL 单元，返回视频编码详细信息
     *
     * @param string $uint8array 包含 SPS 数据的字节字符串 (通常去除起始码)
     * @return array 关联数组，包含编码器配置等
     */
    public static function parseSPS($uint8array)
    {
        $rbsp = self::_ebsp2rbsp($uint8array);
        $gb = new ExpGolomb($rbsp);

        // 跳过 NAL unit type 等
        $gb->readByte();
        $profile_idc = $gb->readByte(); // profile_idc
        $gb->readByte();                // constraint_set_flags + reserved_zero
        $level_idc = $gb->readByte();   // level_idc
        $gb->readUEG();                 // seq_parameter_set_id

        $profile_string = self::getProfileString($profile_idc);
        $level_string = self::getLevelString($level_idc);

        $chroma_format_idc = 1;
        $chroma_format = 420;
        $chroma_format_table = [0, 420, 422, 444];
        $bit_depth = 8;

        // 某些 profile 会携带额外信息
        $extended_profiles = [100, 110, 122, 244, 44, 83, 86, 118, 128, 138, 144];
        if (in_array($profile_idc, $extended_profiles)) {
            $chroma_format_idc = $gb->readUEG();
            if ($chroma_format_idc === 3) {
                $gb->readBits(1); // separate_colour_plane_flag
            }
            if ($chroma_format_idc <= 3) {
                $chroma_format = $chroma_format_table[$chroma_format_idc];
            }

            $bit_depth = $gb->readUEG() + 8;   // bit_depth_luma_minus8
            $gb->readUEG();                    // bit_depth_chroma_minus8
            $gb->readBits(1);                  // qpprime_y_zero_transform_bypass_flag

            if ($gb->readBool()) {             // seq_scaling_matrix_present_flag
                $scaling_list_count = ($chroma_format_idc !== 3) ? 8 : 12;
                for ($i = 0; $i < $scaling_list_count; $i++) {
                    if ($gb->readBool()) {
                        if ($i < 6) {
                            self::_skipScalingList($gb, 16);
                        } else {
                            self::_skipScalingList($gb, 64);
                        }
                    }
                }
            }
        }

        $gb->readUEG();                      // log2_max_frame_num_minus4
        $pic_order_cnt_type = $gb->readUEG();
        if ($pic_order_cnt_type === 0) {
            $gb->readUEG();                  // log2_max_pic_order_cnt_lsb_minus4
        } elseif ($pic_order_cnt_type === 1) {
            $gb->readBits(1);                // delta_pic_order_always_zero_flag
            $gb->readSEG();                  // offset_for_non_ref_pic
            $gb->readSEG();                  // offset_for_top_to_bottom_field
            $num_ref_frames = $gb->readUEG();
            for ($i = 0; $i < $num_ref_frames; $i++) {
                $gb->readSEG();              // offset_for_ref_frame
            }
        }

        $gb->readUEG();                      // max_num_ref_frames
        $gb->readBits(1);                    // gaps_in_frame_num_value_allowed_flag

        $pic_width_in_mbs_minus1 = $gb->readUEG();
        $pic_height_in_map_units_minus1 = $gb->readUEG();

        $frame_mbs_only_flag = $gb->readBits(1);
        if ($frame_mbs_only_flag === 0) {
            $gb->readBits(1);                // mb_adaptive_frame_field_flag
        }
        $gb->readBits(1);                    // direct_8x8_inference_flag

        $frame_crop_left_offset = 0;
        $frame_crop_right_offset = 0;
        $frame_crop_top_offset = 0;
        $frame_crop_bottom_offset = 0;

        $frame_cropping_flag = $gb->readBool();
        if ($frame_cropping_flag) {
            $frame_crop_left_offset = $gb->readUEG();
            $frame_crop_right_offset = $gb->readUEG();
            $frame_crop_top_offset = $gb->readUEG();
            $frame_crop_bottom_offset = $gb->readUEG();
        }

        $sar_width = 1;
        $sar_height = 1;
        $fps = 0;
        $fps_fixed = true;
        $fps_num = 0;
        $fps_den = 0;

        $vui_parameters_present_flag = $gb->readBool();
        if ($vui_parameters_present_flag) {
            // 宽高比信息
            if ($gb->readBool()) {               // aspect_ratio_info_present_flag
                $aspect_ratio_idc = $gb->readByte();
                $sar_w_table = [1, 12, 10, 16, 40, 24, 20, 32, 80, 18, 15, 64, 160, 4, 3, 2];
                $sar_h_table = [1, 11, 11, 11, 33, 11, 11, 11, 33, 11, 11, 33, 99, 3, 2, 1];

                if ($aspect_ratio_idc > 0 && $aspect_ratio_idc < 16) {
                    $sar_width = $sar_w_table[$aspect_ratio_idc - 1];
                    $sar_height = $sar_h_table[$aspect_ratio_idc - 1];
                } elseif ($aspect_ratio_idc === 255) {
                    $sar_width = ($gb->readByte() << 8) | $gb->readByte();
                    $sar_height = ($gb->readByte() << 8) | $gb->readByte();
                }
            }

            if ($gb->readBool()) {               // overscan_info_present_flag
                $gb->readBool();                 // overscan_appropriate_flag
            }
            if ($gb->readBool()) {               // video_signal_type_present_flag
                $gb->readBits(4);
                if ($gb->readBool()) {
                    $gb->readBits(24);
                }
            }
            if ($gb->readBool()) {               // chroma_loc_info_present_flag
                $gb->readUEG();
                $gb->readUEG();
            }
            if ($gb->readBool()) {               // timing_info_present_flag
                $num_units_in_tick = $gb->readBits(32);
                $time_scale = $gb->readBits(32);
                $fps_fixed = $gb->readBool();    // fixed_frame_rate_flag

                $fps_num = $time_scale;
                $fps_den = $num_units_in_tick * 2;
                $fps = $fps_num / $fps_den;
            }
        }

        // 计算实际显示尺寸
        $sarScale = 1;
        if ($sar_width !== 1 || $sar_height !== 1) {
            $sarScale = $sar_width / $sar_height;
        }

        if ($chroma_format_idc === 0) {
            $crop_unit_x = 1;
            $crop_unit_y = 2 - $frame_mbs_only_flag;
        } else {
            $sub_wc = ($chroma_format_idc === 3) ? 1 : 2;
            $sub_hc = ($chroma_format_idc === 1) ? 2 : 1;
            $crop_unit_x = $sub_wc;
            $crop_unit_y = $sub_hc * (2 - $frame_mbs_only_flag);
        }

        $codec_width = ($pic_width_in_mbs_minus1 + 1) * 16;
        $codec_height = (2 - $frame_mbs_only_flag) * (($pic_height_in_map_units_minus1 + 1) * 16);

        $codec_width -= ($frame_crop_left_offset + $frame_crop_right_offset) * $crop_unit_x;
        $codec_height -= ($frame_crop_top_offset + $frame_crop_bottom_offset) * $crop_unit_y;

        $present_width = ceil($codec_width * $sarScale);

        $gb->destroy();

        return [
            'profile_string' => $profile_string,
            'level_string' => $level_string,
            'bit_depth' => $bit_depth,
            'chroma_format' => $chroma_format,
            'chroma_format_string' => self::getChromaFormatString($chroma_format),

            'frame_rate' => [
                'fixed' => $fps_fixed,
                'fps' => $fps,
                'fps_den' => $fps_den,
                'fps_num' => $fps_num
            ],

            'sar_ratio' => [
                'width' => $sar_width,
                'height' => $sar_height
            ],

            'codec_size' => [
                'width' => $codec_width,
                'height' => $codec_height
            ],

            'present_size' => [
                'width' => $present_width,
                'height' => $codec_height
            ]
        ];
    }

    /**
     * 跳过缩放列表数据
     */
    public static function _skipScalingList($gb, $count)
    {
        $last_scale = 8;
        $next_scale = 8;
        for ($i = 0; $i < $count; $i++) {
            if ($next_scale !== 0) {
                $delta_scale = $gb->readSEG();
                $next_scale = ($last_scale + $delta_scale + 256) % 256;
            }
            $last_scale = ($next_scale === 0) ? $last_scale : $next_scale;
        }
    }

    public static function getProfileString($profile_idc)
    {
        switch ($profile_idc) {
            case 66:
                return 'Baseline';
            case 77:
                return 'Main';
            case 88:
                return 'Extended';
            case 100:
                return 'High';
            case 110:
                return 'High10';
            case 122:
                return 'High422';
            case 244:
                return 'High444';
            default:
                return 'Unknown';
        }
    }

    public static function getLevelString($level_idc)
    {
        return number_format($level_idc / 10, 1, '.', '');
    }

    public static function getChromaFormatString($chroma)
    {
        switch ($chroma) {
            case 420:
                return '4:2:0';
            case 422:
                return '4:2:2';
            case 444:
                return '4:4:4';
            default:
                return 'Unknown';
        }
    }
}