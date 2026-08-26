<?php
// Shared pricing helpers: VAT, tier breaks, bulk packs, credit limits.

class Pricing
{
    /**
     * Apply discount then VAT, then optional additional charges (delivery, packing, etc.).
     * Additional charges are pure profit (no COGS) and are always added on top of the product total.
     */
    public static function totals(float $subtotal, float $discount, float $vatRate, bool $vatInclusive = true, float $additionalCharges = 0.0): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $discount = round(min(max(0, $discount), $subtotal), 2);
        $additionalCharges = round(max(0, $additionalCharges), 2);
        $net = round($subtotal - $discount, 2);
        $vatRate = max(0, $vatRate);
        if ($vatRate <= 0) {
            return [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'vat_rate' => 0.0,
                'vat_amount' => 0.0,
                'additional_charges' => $additionalCharges,
                'total' => round($net + $additionalCharges, 2),
            ];
        }
        if ($vatInclusive) {
            $vatAmount = round($net - ($net / (1 + $vatRate / 100)), 2);
            $total = $net;
        } else {
            $vatAmount = round($net * ($vatRate / 100), 2);
            $total = round($net + $vatAmount, 2);
        }
        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'additional_charges' => $additionalCharges,
            'total' => round($total + $additionalCharges, 2),
        ];
    }

    /**
     * Pick the best unit price for a qty: tier break → wholesale/package
     * pricing for wholesale lines → retail/offer.
     */
    public static function unitPriceForQty(array $product, float $qty, string $saleType = 'retail', array $tiers = []): float
    {
        $qty = max(0, $qty);
        $best = null;
        foreach ($tiers as $tier) {
            $min = (float) ($tier['min_qty'] ?? 0);
            $max = $tier['max_qty'] !== null && $tier['max_qty'] !== '' ? (float) $tier['max_qty'] : null;
            if ($qty + 0.0001 < $min) {
                continue;
            }
            if ($max !== null && $qty > $max + 0.0001) {
                continue;
            }
            $price = (float) $tier['unit_price'];
            if ($best === null || $price < $best) {
                $best = $price;
            }
        }
        if ($best !== null) {
            return round($best, 2);
        }

        if ($saleType === 'wholesale') {
            $unitsPerPack = (float) ($product['units_per_pack'] ?? 1);
            $packPrice = $product['pack_price'] ?? null;
            if ($packPrice !== null && $packPrice !== '' && $unitsPerPack > 1) {
                return round(((float) $packPrice) / $unitsPerPack, 2);
            }

            $w = (float) ($product['wholesale_price'] ?? 0);
            if ($w > 0) {
                return round($w, 2);
            }
        }

        $unitsPerPack = (float) ($product['units_per_pack'] ?? 1);
        $retailPack = $product['retail_pack_price'] ?? null;
        if ($saleType === 'retail_pack' && $retailPack !== null && $retailPack !== ''
            && (float) $retailPack > 0 && $unitsPerPack > 1) {
            return round(((float) $retailPack) / $unitsPerPack, 2);
        }

        return Models\ProductModel::effectivePrice($product)['price'];
    }

    /**
     * Line total that applies carton retail when selling whole packages at retail
     * plus leftover inner items at the single-item retail/offer price.
     */
    public static function lineTotal(array $product, float $qty, string $saleType = 'retail'): float
    {
        $qty = max(0, $qty);
        if ($qty <= 0) {
            return 0.0;
        }
        $unitsPerPack = (float) ($product['units_per_pack'] ?? 1);
        $retailPack = $product['retail_pack_price'] ?? null;
        if ($saleType === 'retail_pack' && $retailPack !== null && $retailPack !== ''
            && (float) $retailPack > 0 && $unitsPerPack > 1) {
            return round(($qty / $unitsPerPack) * (float) $retailPack, 2);
        }
        return round(self::unitPriceForQty($product, $qty, $saleType) * $qty, 2);
    }

    /** Whether selling $qty of this product would exceed its credit limit. */
    public static function withinCreditLimit(array $product, float $lineTotal): bool
    {
        if (!isset($product['credit_limit']) || $product['credit_limit'] === null || $product['credit_limit'] === '') {
            return true;
        }
        return $lineTotal <= (float) $product['credit_limit'] + 0.0001;
    }
}
