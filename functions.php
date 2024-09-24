<?php
function sendResponse($statusCode, $data) {
    if (ob_get_length()) ob_clean(); // Clear the output buffer if it's not empty
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    if (ob_get_length()) ob_end_flush(); // Flush the output buffer if it's not empty
    exit;
}

function triggerResponse($responseTriggers, $shouldExit = true) {
    if (ob_get_length()) ob_clean(); // Clear the output buffer if it's not empty
    header('Content-Type: application/json');
    header('HX-Trigger: ' . json_encode($responseTriggers));
    if (ob_get_length()) ob_end_flush(); // Flush the output buffer if it's not empty
    
    if ($shouldExit) {
        exit;
    }
}


// Mainly being used for task cards, 
// Percentage complated (checklist)
function taskCompletedPercentage($number) {
    if ($number > 99) {
        return 'perc100';
    }
    else if ($number > 79) {
        return 'perc80';
    }
    else if ($number > 49) {
        return 'perc50';
    }
    else if ($number > 29) {
        return 'perc30';
    }
    else if ($number > 9) {
        return 'perc10';
    }
    else if ($number > 0) {
        return '';
    }
}


// Generate color range for task labels
function generateGradient($colors, $total) {
    $gradient = array();
    $total--; // Adjust for the inclusion of the final color at the end
    $parts = count($colors) - 1;

    for ($part = 0; $part < $parts; $part++) {
        // Calculate how many steps are needed for this segment
        $sectionLength = ceil(($total / $parts) * ($part + 1)) - count($gradient);

        // Check to prevent division by zero
        if ($sectionLength > 1) {
            for ($i = 0; $i < $sectionLength; $i++) {
                $factor = $i / ($sectionLength - 1); // Adjust calculation for factor
                $gradient[] = interpolate($colors[$part], $colors[$part + 1], $factor);
            }
        } else {
            // When sectionLength is 1, just add the starting color since no interpolation is needed
            $gradient[] = $colors[$part];
        }
    }

    // Ensure the last color is added
    $gradient[] = end($colors);
    
    // Remove duplicate colors
    $gradient = array_values(array_unique($gradient));

    return $gradient;
}
    
    
function interpolate($color1, $color2, $factor) {
    $result = "#";
    for ($i = 0; $i < 3; $i++) {
        $color1Val = hexdec(substr($color1, 1 + $i * 2, 2));
        $color2Val = hexdec(substr($color2, 1 + $i * 2, 2));
        $val = dechex(round($color1Val + ($color2Val - $color1Val) * $factor));
        $result .= str_pad($val, 2, "0", STR_PAD_LEFT); // Ensure two digits for each color component
    }
    return $result;
}
    
    

function alter_vibrance($hexColor, $saturationBoost, $lightnessAdjustment = 0) {
    // Convert HEX to RGB
    $r = hexdec(substr($hexColor, 1, 2));
    $g = hexdec(substr($hexColor, 3, 2));
    $b = hexdec(substr($hexColor, 5, 2));

    // Convert RGB to HSL
    list($h, $s, $l) = rgbToHsl($r, $g, $b);

    // Increase the saturation and cap it at 1
    $s = min(1, $s + $saturationBoost / 100);

    // Adjust lightness and ensure it remains within the range 0 to 1
    $l = max(0, min(1, $l + $lightnessAdjustment / 100));

    // Convert HSL back to RGB
    list($r, $g, $b) = hslToRgb($h, $s, $l);

    // Convert RGB back to HEX
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
    
function rgbToHsl($r, $g, $b) {
    $r /= 255; $g /= 255; $b /= 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $h = $s = $l = ($max + $min) / 2;

    if ($max == $min) {
        $h = $s = 0;
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        if ($max == $r) {
            $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
        } elseif ($max == $g) {
            $h = ($b - $r) / $d + 2;
        } else {
            $h = ($r - $g) / $d + 4;
        }
        $h /= 6;
    }
    return array($h, $s, $l);
}
    
function hslToRgb($h, $s, $l){
    if ($s == 0) {
        $r = $g = $b = $l;
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $r = hue2rgb($p, $q, $h + 1/3);
        $g = hue2rgb($p, $q, $h);
        $b = hue2rgb($p, $q, $h - 1/3);
    }
    return array(round($r * 255), round($g * 255), round($b * 255));
}
    
function hue2rgb($p, $q, $t){
    if ($t < 0) $t += 1;
    if ($t > 1) $t -= 1;
    if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
    if ($t < 1/2) return $q;
    if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
    return $p;
}


function adjustHexColorBrightness($hex, $steps) {
    // Steps should be between -255 and 255. Negative = darker, positive = lighter
    $steps = max(-255, min(255, $steps));

    // Normalize into a six character long hex string
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
    }

    // Split into three parts: R, G and B
    $color_parts = str_split($hex, 2);
    $return = '#';

    foreach ($color_parts as $color) {
        $color   = hexdec($color); // Convert to decimal
        $color   = max(0,min(255,$color + $steps)); // Adjust color
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT); // Make two char hex code
    }

    return $return;
}
?>