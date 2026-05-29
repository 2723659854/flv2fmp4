<?php


/**
 * Decode a UTF-8 encoded binary string into a UTF-8 string.
 * Equivalent to the JavaScript decodeUTF8 function.
 *
 * @param string $uint8array Binary string (each byte as character)
 * @return string Decoded UTF-8 string
 */
function decodeUTF8($uint8array)
{
    $out = [];
    $input = $uint8array;
    $i = 0;
    $length = strlen($uint8array);

    while ($i < $length) {
        $byte = ord($input[$i]);
        if ($byte < 0x80) {
            // ASCII
            $out[] = chr($byte);
            ++$i;
            continue;
        } elseif ($byte < 0xC0) {
            // invalid continuation, fall through
        } elseif ($byte < 0xE0) {
            // 2-byte sequence
            if (checkContinuation($input, $i, 1)) {
                $ucs4 = (($byte & 0x1F) << 6) | (ord($input[$i + 1]) & 0x3F);
                if ($ucs4 >= 0x80) {
                    $out[] = mb_chr($ucs4 & 0xFFFF, 'UTF-8');
                    $i += 2;
                    continue;
                }
            }
        } elseif ($byte < 0xF0) {
            // 3-byte sequence
            if (checkContinuation($input, $i, 2)) {
                $ucs4 = (($byte & 0x0F) << 12) |
                    ((ord($input[$i + 1]) & 0x3F) << 6) |
                    (ord($input[$i + 2]) & 0x3F);
                if ($ucs4 >= 0x800 && ($ucs4 & 0xF800) !== 0xD800) {
                    $out[] = mb_chr($ucs4 & 0xFFFF, 'UTF-8');
                    $i += 3;
                    continue;
                }
            }
        } elseif ($byte < 0xF8) {
            // 4-byte sequence
            if (checkContinuation($input, $i, 3)) {
                $ucs4 = (($byte & 0x07) << 18) |
                    ((ord($input[$i + 1]) & 0x3F) << 12) |
                    ((ord($input[$i + 2]) & 0x3F) << 6) |
                    (ord($input[$i + 3]) & 0x3F);
                if ($ucs4 > 0x10000 && $ucs4 < 0x110000) {
                    // Surrogate pair in JS, but in PHP we output the actual Unicode character
                    $out[] = mb_chr($ucs4, 'UTF-8');
                    $i += 4;
                    continue;
                }
            }
        }
        // Invalid byte sequence, output Unicode replacement character
        $out[] = "\u{FFFD}";
        ++$i;
    }

    return implode('', $out);
}

/**
 * Check if the following bytes are valid UTF-8 continuation bytes.
 *
 * @param string $uint8array Binary string
 * @param int $start Start index (position of first byte of the sequence)
 * @param int $checkLength Number of continuation bytes to check
 * @return bool True if all continuation bytes are valid, false otherwise
 */
function checkContinuation($uint8array, $start, $checkLength)
{
    $len = strlen($uint8array);
    if ($start + $checkLength < $len) {
        for ($i = 1; $i <= $checkLength; $i++) {
            $byte = ord($uint8array[$start + $i]);
            if (($byte & 0xC0) !== 0x80) {
                return false;
            }
        }
        return true;
    }
    return false;
}

// If mb_chr is not available (mbstring extension missing), provide a fallback
if (!function_exists('mb_chr')) {
    function mb_chr($code, $encoding = 'UTF-8')
    {
        return html_entity_decode("&#{$code};", ENT_QUOTES, 'UTF-8');
    }
}