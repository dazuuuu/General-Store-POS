<?php

use Models\OrderModel;
use Models\ProductModel;
use Models\SaleModel;

class TenantDataExportService
{
    private PDO $db;
    private string $currency;

    public function __construct(?PDO $db = null, string $currency = 'KES')
    {
        $this->db = $db ?? Database::pdo();
        $this->currency = $currency;
    }

    public function workbook(string $type, string $period = 'all'): array
    {
        $type = in_array($type, ['products', 'sales', 'profit', 'all'], true) ? $type : 'all';
        $period = in_array($period, ['today', 'week', 'month', 'all'], true) ? $period : 'all';

        $sheets = [];
        if ($type === 'products' || $type === 'all') {
            $sheets[] = ['name' => 'Products', 'rows' => $this->productRows()];
        }
        if ($type === 'sales' || $type === 'all') {
            $sheets[] = ['name' => 'Sales', 'rows' => $this->salesRows($period)];
        }
        if ($type === 'profit' || $type === 'all') {
            $sheets[] = ['name' => 'Profit Margins', 'rows' => $this->profitRows($period)];
        }

        $filename = 'shop-' . $type . '-' . $period . '-' . date('Ymd-His') . '.xls';
        return ['filename' => $filename, 'content' => $this->spreadsheetXml($sheets)];
    }

    private function productRows(): array
    {
        $rows = [[
            'Product ID', 'Name', 'Category', 'Brand', 'Supplier', 'Barcode', 'Unit',
            'Stock Qty', 'Faulty Qty', 'Buying Price', 'Wholesale Price', 'Retail Price',
            'Pack Unit', 'Units Per Pack', 'Pack Price', 'Status', 'Stock Value', 'Retail Margin %',
        ]];

        foreach ((new ProductModel($this->db))->listWithMeta(true) as $p) {
            $buying = (float) ($p['buying_price'] ?? 0);
            $retail = (float) ($p['retail_price'] ?? $p['selling_price'] ?? 0);
            $qty = (float) ($p['quantity'] ?? 0);
            $profit = ProductModel::profit($buying, $retail);
            $rows[] = [
                (int) $p['id'],
                $p['name'] ?? '',
                $p['category_name'] ?? '',
                $p['brand_name'] ?? $p['publisher_name'] ?? '',
                $p['supplier_name'] ?? '',
                $p['barcode'] ?? '',
                $p['unit'] ?? '',
                $qty,
                (float) ($p['faulty_quantity'] ?? 0),
                $buying,
                (float) ($p['wholesale_price'] ?? 0),
                $retail,
                $p['pack_unit'] ?? '',
                (float) ($p['units_per_pack'] ?? 1),
                (float) ($p['pack_price'] ?? 0),
                $p['status'] ?? '',
                ProductModel::stockValue($buying, $qty),
                $profit['margin_pct'] ?? '',
            ];
        }
        return $rows;
    }

    private function salesRows(string $period): array
    {
        $SA = new SaleModel($this->db);
        $OR = new OrderModel($this->db);
        $sales = $SA->forTenant(10000, $period);
        foreach ($sales as &$s) {
            $s['source'] = 'sale';
        }
        unset($s);
        $orders = $OR->forTenant(10000, $period);
        $rows = array_merge($sales, $orders);
        usort($rows, fn($a, $b) => strtotime($b['created_at'] ?? 'now') <=> strtotime($a['created_at'] ?? 'now'));

        $out = [[
            'Date', 'Source', 'Receipt / Invoice', 'Customer', 'Staff', 'Sale Type',
            'Payment Method', 'Payment Status', 'Subtotal', 'Discount', 'VAT',
            'Total', 'Amount Paid', 'Amount Due',
        ]];
        foreach ($rows as $r) {
            $out[] = [
                $r['created_at'] ?? '',
                $r['source'] ?? 'order',
                $r['receipt_number'] ?? '',
                $r['customer_name'] ?? $r['table_name'] ?? '',
                $r['staff_name'] ?? '',
                $r['sale_type'] ?? 'retail',
                $r['payment_method'] ?? '',
                $r['payment_status'] ?? '',
                (float) ($r['subtotal'] ?? $r['total'] ?? 0),
                (float) ($r['discount_amount'] ?? 0),
                (float) ($r['vat_amount'] ?? 0),
                (float) ($r['total'] ?? 0),
                (float) ($r['amount_paid'] ?? $r['total'] ?? 0),
                (float) ($r['amount_due'] ?? 0),
            ];
        }
        return $out;
    }

    private function profitRows(string $period): array
    {
        $rows = [[
            'Product ID', 'Product', 'Unit', 'Quantity Sold', 'Revenue', 'Cost',
            'Profit', 'Profit Margin %', 'Currency',
        ]];

        $byProduct = [];
        $SA = new SaleModel($this->db);
        $OR = new OrderModel($this->db);
        foreach ($SA->productProfit($period) as $pp) {
            $byProduct[(int) $pp['product_id']] = $pp;
        }
        foreach ($OR->productProfit($period) as $op) {
            $pid = (int) $op['product_id'];
            if (!isset($byProduct[$pid])) {
                $byProduct[$pid] = $op;
                continue;
            }
            foreach (['qty', 'revenue', 'cost', 'profit'] as $key) {
                $byProduct[$pid][$key] = (float) ($byProduct[$pid][$key] ?? 0) + (float) ($op[$key] ?? 0);
            }
            $revenue = (float) ($byProduct[$pid]['revenue'] ?? 0);
            $profit = (float) ($byProduct[$pid]['profit'] ?? 0);
            $byProduct[$pid]['margin'] = $revenue > 0 ? round($profit / $revenue * 100, 1) : 0;
        }
        usort($byProduct, fn($a, $b) => (float) ($b['profit'] ?? 0) <=> (float) ($a['profit'] ?? 0));

        foreach ($byProduct as $pp) {
            $rows[] = [
                (int) ($pp['product_id'] ?? 0),
                $pp['product_name'] ?? '',
                $pp['unit'] ?? '',
                (float) ($pp['qty'] ?? 0),
                (float) ($pp['revenue'] ?? 0),
                (float) ($pp['cost'] ?? 0),
                (float) ($pp['profit'] ?? 0),
                (float) ($pp['margin'] ?? 0),
                $this->currency,
            ];
        }
        return $rows;
    }

    private function spreadsheetXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        foreach ($sheets as $sheet) {
            $xml .= '<Worksheet ss:Name="' . $this->xml($this->sheetName($sheet['name'])) . '"><Table>';
            foreach ($sheet['rows'] as $row) {
                $xml .= '<Row>';
                foreach ($row as $cell) {
                    $isNumber = is_int($cell) || is_float($cell);
                    $xml .= '<Cell><Data ss:Type="' . ($isNumber ? 'Number' : 'String') . '">' . $this->xml((string) $cell) . '</Data></Cell>';
                }
                $xml .= '</Row>';
            }
            $xml .= '</Table></Worksheet>';
        }
        $xml .= '</Workbook>';
        return $xml;
    }

    private function sheetName(string $name): string
    {
        return substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]+/', ' ', $name) ?: 'Sheet', 0, 31);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
