<?php
// app/services/SalesReport.php
// Builds the daily sales report for one shop + date, and renders it as a PDF
// (FPDF) or as HTML for an email body. Used by the report page, the PDF
// download endpoint, and the daily 6pm cron. CLI-safe: takes an explicit
// tenant id, never touches TenantContext or the session.

class SalesReport
{
    /** Gather everything the report needs for one tenant on one Y-m-d date. */
    public static function data(\PDO $db, int $tenantId, string $date): array
    {
        $date  = preg_replace('/[^0-9-]/', '', $date) ?: date('Y-m-d');
        $model = new \Models\SaleModel($db);
        $order = new \Models\OrderModel($db);
        // Paid tabs are now the main way staff record a sale; legacy direct
        // sales stay in the mix for continuity. Both are CLI-safe (explicit
        // tenant id, no TenantContext) since this feeds the daily cron too.
        $sales = array_merge($model->forTenantId($tenantId, $date), $order->forTenantOnDate($tenantId, $date));
        usort($sales, fn($a, $b) => strtotime($a['created_at']) <=> strtotime($b['created_at']));
        $sum   = \Models\SaleModel::summarize($sales);
        $staff = \Models\SaleModel::staffBreakdown($sales);

        return [
            'tenant_id' => $tenantId,
            'date'      => $date,
            'shop'      => self::shop($db, $tenantId),
            'sales'     => $sales,
            'sum'       => $sum,
            'staff'     => $staff,
            'products'  => self::productBreakdown($db, $tenantId, $date),
        ];
    }

    private static function shop(\PDO $db, int $tenantId): array
    {
        $st = $db->prepare(
            "SELECT t.name, t.currency, t.phone, t.address, t.receipt_footer,
                    u.email AS owner_email, u.username AS owner_name
               FROM tenants t
          LEFT JOIN users u ON u.id = t.owner_user_id
              WHERE t.id = ? LIMIT 1"
        );
        $st->execute([$tenantId]);
        $r = $st->fetch();
        return $r ?: ['name' => 'Shop', 'currency' => 'KES', 'phone' => null, 'address' => null, 'receipt_footer' => null, 'owner_email' => null, 'owner_name' => null];
    }

    private static function productBreakdown(\PDO $db, int $tenantId, string $date): array
    {
        $st = $db->prepare(
            "SELECT si.product_name, SUM(GREATEST(si.quantity - COALESCE(ret.returned_quantity,0), 0)) AS qty, SUM(si.line_total) AS revenue
               FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
          LEFT JOIN (
                    SELECT tenant_id, source_item_id, SUM(returned_quantity) AS returned_quantity
                      FROM product_returns
                     WHERE source_type = 'sale'
                  GROUP BY tenant_id, source_item_id
               ) ret ON ret.tenant_id = si.tenant_id AND ret.source_item_id = si.id
              WHERE si.tenant_id = ? AND s.status = 'completed' AND DATE(s.created_at) = ?
           GROUP BY si.product_name"
        );
        $st->execute([$tenantId, $date]);
        $legacy = $st->fetchAll();

        $fromOrders = (new \Models\OrderModel($db))->productBreakdownOnDate($tenantId, $date);

        $byName = [];
        foreach (array_merge($legacy, $fromOrders) as $r) {
            $name = $r['product_name'];
            if (!isset($byName[$name])) { $byName[$name] = ['product_name' => $name, 'qty' => 0.0, 'revenue' => 0.0]; }
            $byName[$name]['qty']     += (float) $r['qty'];
            $byName[$name]['revenue'] += (float) $r['revenue'];
        }
        $out = array_values($byName);
        usort($out, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $out;
    }

    private static function cur(array $data): string
    {
        return $data['shop']['currency'] ?: 'KES';
    }

    private static function money(string $cur, $v): string
    {
        return $cur . ' ' . number_format((float) $v, 0);
    }

    // ===== PDF (FPDF) ====================================================

    /** Latin-1 encode for FPDF core fonts. */
    private static function t($s): string
    {
        $s = (string) $s;
        $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
        return $out !== false ? $out : $s;
    }

    /** Render the report as a PDF and return the raw bytes. */
    public static function pdf(array $data): string
    {
        if (!class_exists('FPDF')) {
            require_once ROOT_PATH . '/vendor/fpdf/fpdf.php';
        }
        $cur   = self::cur($data);
        $shop  = $data['shop'];
        $sum   = $data['sum'];
        $dateLabel = date('l, j F Y', strtotime($data['date']));

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();
        $W = 180; // usable width

        // ----- header -----
        $pdf->SetFont('Helvetica', 'B', 17);
        $pdf->Cell(0, 9, self::t($shop['name'] ?: 'Shop'), 0, 1);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 6, self::t('Daily Sales Report  -  ' . $dateLabel), 0, 1);
        $meta = trim((string) ($shop['phone'] ?? '') . (($shop['phone'] && $shop['address']) ? '  |  ' : '') . (string) ($shop['address'] ?? ''));
        if ($meta !== '') { $pdf->SetFont('Helvetica', '', 9); $pdf->Cell(0, 5, self::t($meta), 0, 1); }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);
        $y = $pdf->GetY();
        $pdf->SetDrawColor(210, 210, 210);
        $pdf->Line(15, $y, 195, $y);
        $pdf->Ln(4);

        // ----- summary boxes -----
        $boxes = [
            ['Sales', (string) $sum['count']],
            ['Revenue', self::money($cur, $sum['revenue'])],
            ['Cash', self::money($cur, $sum['cash'])],
            ['M-Pesa', self::money($cur, $sum['mpesa'])],
            ['Card', self::money($cur, $sum['card'] ?? 0)],
            ['Bank', self::money($cur, $sum['bank'] ?? 0)],
            ['SACCO', self::money($cur, $sum['sacco'] ?? 0)],
        ];
        $bw = $W / count($boxes);
        $startX = $pdf->GetX();
        $top = $pdf->GetY();
        foreach ($boxes as $i => $b) {
            $x = 15 + $i * $bw;
            $pdf->SetXY($x, $top);
            $pdf->SetFillColor(247, 248, 250);
            $pdf->SetDrawColor(225, 228, 232);
            $pdf->Cell($bw - 3, 16, '', 1, 0, 'L', true);
            $pdf->SetXY($x + 3, $top + 3);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell($bw - 6, 4, self::t(strtoupper($b[0])), 0, 2);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->Cell($bw - 6, 7, self::t($b[1]), 0, 0);
        }
        $pdf->SetXY(15, $top + 16 + 6);
        $pdf->SetTextColor(0, 0, 0);

        // ----- sales table -----
        self::sectionTitle($pdf, 'Sales (' . $sum['count'] . ')');
        if (!$data['sales']) {
            $pdf->SetFont('Helvetica', 'I', 10);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell(0, 8, self::t('No sales recorded on this day.'), 0, 1);
            $pdf->SetTextColor(0, 0, 0);
        } else {
            $cols = [['Receipt', 30, 'L'], ['Time', 22, 'L'], ['Staff', 33, 'L'], ['Customer', 45, 'L'], ['Pay', 20, 'L'], ['Total', 30, 'R']];
            self::tableHead($pdf, $cols);
            $pdf->SetFont('Helvetica', '', 9);
            $fill = false;
            foreach ($data['sales'] as $s) {
                $row = [
                    $s['receipt_number'],
                    date('g:i a', strtotime($s['created_at'])),
                    $s['staff_name'] ?: '-',
                    $s['customer_name'] ?: '-',
                    \PaymentOptions::label($s),
                    self::money($cur, $s['total']),
                ];
                self::tableRow($pdf, $cols, $row, $fill);
                $fill = !$fill;
            }
        }
        $pdf->Ln(4);

        // ----- product breakdown -----
        if ($data['products']) {
            self::sectionTitle($pdf, 'Products sold');
            $cols = [['Product', 110, 'L'], ['Qty', 30, 'R'], ['Revenue', 40, 'R']];
            self::tableHead($pdf, $cols);
            $pdf->SetFont('Helvetica', '', 9);
            $fill = false;
            foreach ($data['products'] as $p) {
                $qty = rtrim(rtrim(number_format((float) $p['qty'], 2), '0'), '.');
                self::tableRow($pdf, $cols, [$p['product_name'], $qty, self::money($cur, $p['revenue'])], $fill);
                $fill = !$fill;
            }
            $pdf->Ln(4);
        }

        // ----- staff breakdown -----
        if ($data['staff']) {
            self::sectionTitle($pdf, 'By staff member');
            $cols = [['Staff', 110, 'L'], ['Sales', 30, 'R'], ['Revenue', 40, 'R']];
            self::tableHead($pdf, $cols);
            $pdf->SetFont('Helvetica', '', 9);
            $fill = false;
            foreach ($data['staff'] as $name => $d) {
                self::tableRow($pdf, $cols, [$name, (string) $d['count'], self::money($cur, $d['revenue'])], $fill);
                $fill = !$fill;
            }
            $pdf->Ln(4);
        }

        // ----- footer -----
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(140, 140, 140);
        $foot = 'Generated ' . date('j M Y, g:i a');
        if (!empty($shop['receipt_footer'])) { $foot .= '  -  ' . $shop['receipt_footer']; }
        $pdf->Cell(0, 5, self::t($foot), 0, 1, 'C');

        return $pdf->Output('S');
    }

    private static function sectionTitle(\FPDF $pdf, string $title): void
    {
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->Cell(0, 7, self::t($title), 0, 1);
    }

    private static function tableHead(\FPDF $pdf, array $cols): void
    {
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(33, 43, 54);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(33, 43, 54);
        foreach ($cols as $c) {
            $pdf->Cell($c[1], 7, self::t(strtoupper($c[0])), 1, 0, $c[2], true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetDrawColor(228, 230, 233);
    }

    private static function tableRow(\FPDF $pdf, array $cols, array $row, bool $fill): void
    {
        $pdf->SetFillColor(248, 249, 250);
        foreach ($cols as $i => $c) {
            $val = self::t($row[$i] ?? '');
            // clip overly long text to the column width
            while ($pdf->GetStringWidth($val) > $c[1] - 3 && strlen($val) > 1) {
                $val = substr($val, 0, -1);
            }
            $pdf->Cell($c[1], 6.5, $val, 'LR', 0, $c[2], $fill);
        }
        $pdf->Ln();
    }

    // ===== Email body (HTML) ============================================

    public static function emailHtml(array $data): string
    {
        $cur  = self::cur($data);
        $shop = $data['shop'];
        $sum  = $data['sum'];
        $dateLabel = date('l, j F Y', strtotime($data['date']));
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);

        $rows = '';
        foreach ($data['sales'] as $s) {
            $pay = \PaymentOptions::label($s);
            $rows .= '<tr>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;font-size:13px;">' . $h($s['receipt_number']) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;font-size:13px;">' . $h(date('g:i a', strtotime($s['created_at']))) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;font-size:13px;">' . $h($s['staff_name'] ?: '-') . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;font-size:13px;">' . $h($pay) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;font-size:13px;text-align:right;">' . $h(self::money($cur, $s['total'])) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="padding:14px;text-align:center;color:#888;font-size:13px;">No sales recorded on this day.</td></tr>';
        }

        $card = fn($label, $val) => '<td style="padding:12px 14px;background:#f7f8fa;border:1px solid #e6e8ec;border-radius:8px;">'
            . '<div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.04em;">' . $h($label) . '</div>'
            . '<div style="font-size:18px;font-weight:700;color:#1a1a1a;margin-top:2px;">' . $h($val) . '</div></td>';

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;color:#222;">'
            . '<h2 style="margin:0 0 2px;font-size:20px;">' . $h($shop['name'] ?: 'Shop') . '</h2>'
            . '<p style="margin:0 0 16px;color:#666;font-size:14px;">Daily Sales Report &middot; ' . $h($dateLabel) . '</p>'
            . '<table cellpadding="0" cellspacing="6" style="width:100%;border-collapse:separate;margin-bottom:18px;"><tr>'
            . $card('Sales', (string) $sum['count']) . $card('Revenue', self::money($cur, $sum['revenue']))
            . $card('Cash', self::money($cur, $sum['cash'])) . $card('M-Pesa', self::money($cur, $sum['mpesa']))
            . $card('Card', self::money($cur, $sum['card'] ?? 0)) . $card('Bank', self::money($cur, $sum['bank'] ?? 0))
            . $card('SACCO', self::money($cur, $sum['sacco'] ?? 0))
            . '</tr></table>'
            . '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border:1px solid #eee;">'
            . '<thead><tr style="background:#212b36;color:#fff;">'
            . '<th style="padding:8px;text-align:left;font-size:12px;">Receipt</th>'
            . '<th style="padding:8px;text-align:left;font-size:12px;">Time</th>'
            . '<th style="padding:8px;text-align:left;font-size:12px;">Staff</th>'
            . '<th style="padding:8px;text-align:left;font-size:12px;">Pay</th>'
            . '<th style="padding:8px;text-align:right;font-size:12px;">Total</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p style="color:#999;font-size:12px;margin-top:16px;">The full report with product and staff breakdowns is attached as a PDF.</p>'
            . '</div>';
    }
}
