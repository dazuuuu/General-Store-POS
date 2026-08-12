<?php
// Shared quantity display: show halves clearly on carts and receipts.

class QtyFormat
{
    /** Human-friendly qty: 0.5 → ½, 1.5 → 1½, otherwise trimmed decimal. */
    public static function display(float $qty): string
    {
        $qty = round($qty, 2);
        if (abs($qty) < 0.0001) {
            return '0';
        }
        $sign = $qty < 0 ? '-' : '';
        $qty = abs($qty);
        $whole = (int) floor($qty + 0.0001);
        $frac = round($qty - $whole, 2);

        if (abs($frac - 0.5) < 0.001) {
            return $sign . ($whole > 0 ? (string) $whole : '') . '½';
        }
        if (abs($frac) < 0.001) {
            return $sign . (string) $whole;
        }
        return $sign . rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }

    /** Extra receipt note when the line includes a half unit. */
    public static function halfNote(float $qty): string
    {
        $qty = abs(round($qty, 2));
        $whole = (int) floor($qty + 0.0001);
        $frac = round($qty - $whole, 2);
        if (abs($frac - 0.5) < 0.001) {
            $halves = (int) round($qty * 2);
            return $halves === 1 ? 'half' : ($halves . ' halves');
        }
        return '';
    }
}
