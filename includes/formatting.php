<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Price Formatting Utilities
 * * NOTE: This file is currently NOT IN USE. 
 * It is prepared for a future update to handle advanced currency formatting.
 * * @package WHMCS_Price
 * @subpackage Utilities
 * @since 2.2.0
 * @status Experimental / Not active
 */
function whmcs_format_price_sv_kr($rawPrice, $currencyLabel = 'kr') : string
{
    $value = whmcs_parse_price_to_float($rawPrice);

    // If parsing fails, return a safe fallback (do minimal cleanup).
    if ($value === null) {
        $fallback = trim((string) $rawPrice);
        $fallback = preg_replace('/\s*Kr\b/u', ' kr', $fallback);
        return $fallback;
    }

    // Show 0 decimals if the value is effectively an integer.
    $decimals = (abs($value - round($value)) < 0.00001) ? 0 : 2;

    // Swedish formatting: decimal "," and thousands " "
    $formatted = number_format($value, $decimals, ',', ' ');

    return $formatted . ' ' . $currencyLabel;
}

/**
 * Parse various price formats to float:
 * - 1499.00
 * - 959,00
 * - 1.499,00Kr
 * - 1,499.00 kr
 * - 1499
 *
 * Returns null if it cannot be interpreted.
 */
function whmcs_parse_price_to_float($rawPrice) : ?float
{
    if (is_int($rawPrice) || is_float($rawPrice)) {
        return (float) $rawPrice;
    }

    $s = trim((string) $rawPrice);
    if ($s === '') return null;

    // Strip currency and anything except digits, dot, comma, minus.
    $s = preg_replace('/[^\d\.,\-]/u', '', $s);
    if ($s === '' || $s === '-') return null;

    $hasDot   = (strpos($s, '.') !== false);
    $hasComma = (strpos($s, ',') !== false);

    // If both '.' and ',' exist: assume the last separator is the decimal separator.
    // Example: "1.499,00" => decimal=',' thousands='.'
    // Example: "1,499.00" => decimal='.' thousands=','
    if ($hasDot && $hasComma) {
        $lastDot   = strrpos($s, '.');
        $lastComma = strrpos($s, ',');

        $decimalSep  = ($lastComma > $lastDot) ? ',' : '.';
        $thousandSep = ($decimalSep === ',') ? '.' : ',';

        $s = str_replace($thousandSep, '', $s);
        $s = str_replace($decimalSep, '.', $s);

        return is_numeric($s) ? (float) $s : null;
    }

    // Only comma present: decide if comma is decimal (two digits after) or thousands.
    if ($hasComma && !$hasDot) {
        $parts = explode(',', $s);
        $last  = end($parts);

        // If exactly 2 digits after the last comma => decimal separator
        if (preg_match('/^\d{2}$/', $last)) {
            $s = str_replace('.', '', $s); // just in case
            $s = str_replace(',', '.', $s);
            return is_numeric($s) ? (float) $s : null;
        }

        // Otherwise treat comma as thousands separator
        $s = str_replace(',', '', $s);
        return is_numeric($s) ? (float) $s : null;
    }

    // Only dot present: decide if dot is decimal (two digits after) or thousands.
    if ($hasDot && !$hasComma) {
        $parts = explode('.', $s);
        $last  = end($parts);

        if (preg_match('/^\d{2}$/', $last)) {
            // dot as decimal separator
            return is_numeric($s) ? (float) $s : null;
        }

        // dot as thousands separator
        $s = str_replace('.', '', $s);
        return is_numeric($s) ? (float) $s : null;
    }

    // Digits only (possibly with minus)
    return is_numeric($s) ? (float) $s : null;
}
