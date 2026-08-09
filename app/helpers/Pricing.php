<?php
// Shared pricing helpers: VAT, tier breaks, bulk packs, credit limits.

class Pricing
{
    /** Apply discount then VAT. Returns subtotal, discount, vat, total. */
    public static function totals(float $subtotal, float $discount, float $vatRate, bool $vatInclusive = true): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $discount = round(min(max(0, $discount), $subtotal), 2);
        $net = round($subtotal - $discount, 2);
        $vatRate = max(0, $vatRate);
        if ($vatRate <= 0) {
            return [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'vat_rate' => 0.0,
                'vat_amount' => 0.0,
                'total' => $net,
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
            'total' => $total,
        ];
    }

    /**
     * Pick the best unit price for a qty: tier break → wholesale (if wholesale
     * sale) → pack price when buying full packs → retail/offer.
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

        $unitsPerPack = (float) ($product['units_per_pack'] ?? 1);
        $packPrice = $product['pack_price'] ?? null;
        if ($packPrice !== null && $packPrice !== '' && $unitsPerPack > 1
            && abs(fmod($qty, $unitsPerPack)) < 0.0001) {
            return round(((float) $packPrice) / $unitsPerPack, 2);
        }

        if ($saleType === 'wholesale') {
            $w = (float) ($product['wholesale_price'] ?? 0);
            if ($w > 0) {
                return round($w, 2);
            }
        }

        return Models\ProductModel::effectivePrice($product)['price'];
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
