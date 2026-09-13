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

    /** Load quantity-discount tiers for a product id (cached per request). */
    public static function tiersForProduct(int $productId): array
    {
        static $cache = [];
        if ($productId <= 0) {
            return [];
        }
        if (array_key_exists($productId, $cache)) {
            return $cache[$productId];
        }
        try {
            $cache[$productId] = (new Models\PriceTierModel())->forProduct($productId);
        } catch (\Throwable $e) {
            $cache[$productId] = [];
        }
        return $cache[$productId];
    }

    /**
     * Pick the best unit price for a qty: tier break → wholesale/package
     * pricing for wholesale lines → retail/offer.
     */
    public static function unitPriceForQty(array $product, float $qty, string $saleType = 'retail', array $tiers = []): float
    {
        $qty = max(0, $qty);
        $best = null;
        $unitsPerPack = (float) ($product['units_per_pack'] ?? 1);
        foreach ($tiers as $tier) {
            if (!self::tierMatchesQty($tier, $qty, $unitsPerPack)) {
                continue;
            }
            // Skip amount-off-only tiers here; those apply in lineTotal().
            if (($tier['unit_price'] ?? '') === '' || (float) $tier['unit_price'] <= 0) {
                if (!empty($tier['discount_amount']) && (float) $tier['discount_amount'] > 0) {
                    continue;
                }
            }
            $price = (float) ($tier['unit_price'] ?? 0);
            if ($price < 0) {
                continue;
            }
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
     * Also applies quantity discount tiers (cheaper unit price and/or flat amount off).
     */
    public static function lineTotal(array $product, float $qty, string $saleType = 'retail', ?array $tiers = null): float
    {
        $qty = max(0, $qty);
        if ($qty <= 0) {
            return 0.0;
        }
        if ($tiers === null) {
            $tiers = self::tiersForProduct((int) ($product['id'] ?? 0));
        }

        $unitsPerPack = (float) ($product['units_per_pack'] ?? 1);
        $retailPack = $product['retail_pack_price'] ?? null;
        if ($saleType === 'retail_pack' && $retailPack !== null && $retailPack !== ''
            && (float) $retailPack > 0 && $unitsPerPack > 1) {
            $base = round(($qty / $unitsPerPack) * (float) $retailPack, 2);
        } else {
            $base = round(self::unitPriceForQty($product, $qty, $saleType, $tiers) * $qty, 2);
        }

        $amountOff = 0.0;
        foreach ($tiers as $tier) {
            if (!self::tierMatchesQty($tier, $qty, $unitsPerPack)) {
                continue;
            }
            $off = (float) ($tier['discount_amount'] ?? 0);
            if ($off > $amountOff) {
                $amountOff = $off;
            }
        }
        return round(max(0, $base - $amountOff), 2);
    }

    /**
     * Match tier against base qty (kg/piece) or pack count when units_per_pack > 1
     * so admin can set “≥ 10 kg” or “≥ 2 bales”.
     */
    private static function tierMatchesQty(array $tier, float $qty, float $unitsPerPack = 1.0): bool
    {
        if (self::qtyInTierRange($tier, $qty)) {
            return true;
        }
        if ($unitsPerPack > 1.0001) {
            $packs = $qty / $unitsPerPack;
            if (self::qtyInTierRange($tier, $packs)) {
                return true;
            }
        }
        return false;
    }

    private static function qtyInTierRange(array $tier, float $qty): bool
    {
        $min = (float) ($tier['min_qty'] ?? 0);
        $max = $tier['max_qty'] !== null && $tier['max_qty'] !== '' ? (float) $tier['max_qty'] : null;
        if ($qty + 0.0001 < $min) {
            return false;
        }
        if ($max !== null && $qty > $max + 0.0001) {
            return false;
        }
        return true;
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
