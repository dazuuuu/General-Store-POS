<?php
// app/helpers/currency.php
// Single place for money formatting. Whole app shows Kenyan Shillings.

if (!defined('APP_CURRENCY')) {
    define('APP_CURRENCY', 'KES');
    define('APP_CURRENCY_SYMBOL', 'KSh');
}

if (!function_exists('format_price')) {
    /** 1234.5 -> "KSh 1,234.50" */
    function format_price($amount, bool $withSymbol = true): string {
        $n = number_format((float) $amount, 2);
        return $withSymbol ? APP_CURRENCY_SYMBOL . ' ' . $n : $n;
    }
}

if (!function_exists('ksh')) {
    /** Shorthand alias. */
    function ksh($amount): string {
        return format_price($amount);
    }
}