<?php
// app/helpers/Billing.php
// Subscription period arithmetic. Intervals are real calendar periods
// (monthly = +1 calendar month, not a flat 30 days).

class Billing
{
    const INTERVALS = ['weekly', 'biweekly', 'monthly'];

    /** Compute the end of a billing period given the interval and a start time. */
    public static function periodEnd(string $interval, ?string $from = null): string
    {
        $base = $from ? strtotime($from) : time();
        $add = [
            'weekly'   => '+1 week',
            'biweekly' => '+2 weeks',
            'monthly'  => '+1 month',
        ][$interval] ?? '+1 month';

        return date('Y-m-d H:i:s', strtotime($add, $base));
    }

    /** The price column on a plan that matches the chosen interval. */
    public static function planAmount(array $plan, string $interval): ?float
    {
        $col = [
            'weekly'   => 'price_weekly',
            'biweekly' => 'price_biweekly',
            'monthly'  => 'price_monthly',
        ][$interval] ?? null;

        if ($col === null || !array_key_exists($col, $plan) || $plan[$col] === null) {
            return null; // interval not offered for this plan
        }
        return (float) $plan[$col];
    }

    public static function isValidInterval(string $interval): bool
    {
        return in_array($interval, self::INTERVALS, true);
    }
}