<?php

function decodeUTF8($uint8array) {
    $out = [];
    $input = $uint8array;
    $i = 0;
    $length = count($uint8array);

    while ($i < $length) {
        if ($input[$i] < 0x80) {
            $out[] = chr($input[$i]);
            ++$i;
            continue;
        } else if ($input[$i] < 0xC0) {
            // fallthrough
        } else if ($input[$i] < 0xE0) {
            if (checkContinuation($input, $i, 1)) {
                $ucs4 = (($input[$i] & 0x1F) << 6) | ($input[$i + 1] & 0x3F);
                if ($ucs4 >= 0x80) {
                    $out[] = chr($ucs4 & 0xFFFF);
                    $i += 2;
                    continue;
                }
            }
        } else if ($input[$i] < 0xF0) {
            if (checkContinuation($input, $i, 2)) {
                $ucs4 = (($input[$i] & 0xF) << 12) | (($input[$i + 1] & 0x3F) << 6) | ($input[$i + 2] & 0x3F);
                if ($ucs4 >= 0x800 && (($ucs4 & 0xF800) !== 0xD800)) {
                    $out[] = chr($ucs4 & 0xFFFF);
                    $i += 3;
                    continue;
                }
            }
        } else if ($input[$i] < 0xF8) {
            if (checkContinuation($input, $i, 3)) {
                $ucs4 = (($input[$i] & 0x7) << 18) | (($input[$i + 1] & 0x3F) << 12) |
                        (($input[$i + 2] & 0x3F) << 6) | ($input[$i + 3] & 0x3F);
                if ($ucs4 > 0x10000 && $ucs4 < 0x110000) {
                    $ucs4 -= 0x10000;
                    $out[] = chr(((($ucs4 >> 10)) | 0xD800));
                    $out[] = chr((($ucs4 & 0x3FF)) | 0xDC00);
                    $i += 4;
                    continue;
                }
            }
        }
        $out[] = chr(0xFFFD);
        ++$i;
    }

    return implode('', $out);
}

function checkContinuation($uint8array, $start, $checkLength) {
    $array = $uint8array;
    if ($start + $checkLength < count($array)) {
        while ($checkLength--) {
            if (($array[++$start] & 0xC0) !== 0x80)
                return false;
        }
        return true;
    } else {
        return false;
    }
}
?>