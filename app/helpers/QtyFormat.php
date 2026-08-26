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

    /**
     * Split a stored inner-unit qty into POS cart buckets so held sales resume
     * with retail items, retail boxes, and wholesale packs as separate rows.
     *
     * @return list<array{quantity:float,price_type:string}>
     */
    public static function splitCartBuckets(array $product, float $quantity, string $priceType): array
    {
        $quantity = max(0, $quantity);
        if ($quantity <= 0) {
            return [];
        }
        $unitsPerPack = max(1.0, (float) ($product['units_per_pack'] ?? 1));
        $packUnit = trim((string) ($product['pack_unit'] ?? ''));
        $packPrice = ($product['pack_price'] ?? '') !== '' && $product['pack_price'] !== null
            ? (float) $product['pack_price'] : 0.0;
        $retailPack = ($product['retail_pack_price'] ?? '') !== '' && $product['retail_pack_price'] !== null
            ? (float) $product['retail_pack_price'] : 0.0;

        if ($priceType === 'wholesale' && $packUnit !== '' && $unitsPerPack > 1 && $packPrice > 0) {
            return [['quantity' => round($quantity / $unitsPerPack, 2), 'price_type' => 'wholesale']];
        }

        if ($priceType === 'retail_pack' && $packUnit !== '' && $unitsPerPack > 1 && $retailPack > 0) {
            return [['quantity' => round($quantity / $unitsPerPack, 2), 'price_type' => 'retail_pack']];
        }

        if ($priceType !== 'wholesale' && $packUnit !== '' && $unitsPerPack > 1 && $retailPack > 0) {
            $packs = (int) floor(($quantity + 0.0001) / $unitsPerPack);
            $remainder = round($quantity - ($packs * $unitsPerPack), 2);
            $out = [];
            if ($packs > 0) {
                $out[] = ['quantity' => (float) $packs, 'price_type' => 'retail_pack'];
            }
            if ($remainder > 0.0001) {
                $out[] = ['quantity' => $remainder, 'price_type' => 'retail'];
            }
            return $out ?: [['quantity' => $quantity, 'price_type' => 'retail']];
        }

        return [['quantity' => $quantity, 'price_type' => $priceType === 'wholesale' ? 'wholesale' : 'retail']];
    }

    /**
     * How a sold line should appear on receipts, invoices, and sales records.
     * Stored qty is always inner units; whole packs are shown as boxes.
     *
     * @return array{qty_label:string,unit_label:string,price_note:string,unit_price:float,summary_qty:string}
     */
    public static function saleLine(array $item): array
    {
        $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 0);
        $priceType = (string) ($item['price_type'] ?? 'retail');
        $unitsPerPack = (float) ($item['units_per_pack'] ?? 1);
        $packUnit = trim((string) ($item['pack_unit'] ?? ''));
        $packPrice = ($item['pack_price'] ?? '') !== '' && $item['pack_price'] !== null
            ? (float) $item['pack_price'] : 0.0;
        $retailPack = ($item['retail_pack_price'] ?? '') !== '' && $item['retail_pack_price'] !== null
            ? (float) $item['retail_pack_price'] : 0.0;
        $innerUnit = trim((string) ($item['unit'] ?? $item['product_unit'] ?? ''));
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $hasPack = $packUnit !== '' && $unitsPerPack > 1;

        if ($priceType === 'wholesale' && $hasPack && abs(fmod($qty + 1e-9, $unitsPerPack)) < 0.0001) {
            $packs = round($qty / $unitsPerPack, 2);
            $boxPrice = $packPrice > 0 ? $packPrice : round($unitPrice * $unitsPerPack, 2);
            return [
                'qty_label' => self::display($packs),
                'unit_label' => $packUnit,
                'price_note' => 'wholesale box',
                'unit_price' => $boxPrice,
                'summary_qty' => self::display($packs) . ' ' . $packUnit,
            ];
        }

        if ($priceType === 'retail_pack' && $hasPack && $retailPack > 0) {
            $packs = round($qty / $unitsPerPack, 2);
            return [
                'qty_label' => self::display($packs),
                'unit_label' => $packUnit,
                'price_note' => 'retail box',
                'unit_price' => $retailPack,
                'summary_qty' => self::display($packs) . ' ' . $packUnit,
            ];
        }

        return [
            'qty_label' => self::display($qty),
            'unit_label' => $innerUnit,
            'price_note' => $priceType === 'wholesale' ? 'wholesale' : 'retail',
            'unit_price' => $unitPrice,
            'summary_qty' => self::display($qty) . ($innerUnit !== '' ? ' ' . $innerUnit : ''),
        ];
    }
}
