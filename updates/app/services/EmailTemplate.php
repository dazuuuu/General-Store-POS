<?php
// app/services/EmailTemplate.php
// Builds [subject, html] for each order email. Logo is referenced as cid:ismanlogo
// (embedded by Mailer). All money is KSh.

require_once __DIR__ . '/../helpers/currency.php';

class EmailTemplate {

    /**
     * @param string $event 'purchased' | 'picked_up' | 'delivered' | 'arrived'
     * @param array  $order  order row (+ optional 'items')
     * @param array|null $item  specific parcel for status events
     * @return array [subject, html]
     */
    public static function render(string $event, array $order, ?array $item = null): array {
        switch ($event) {
            case 'purchased':
                return [
                    'Order ' . $order['order_number'] . ' confirmed — payment received',
                    self::layout(
                        'Payment received 🎉',
                        '<p>Hi ' . self::e($order['customer_name']) . ',</p>'
                        . '<p>We\'ve received your payment and your order <strong>' . self::e($order['order_number']) . '</strong> is confirmed. '
                        . 'Each item below has its own parcel ID — quote it when collecting or tracking.</p>'
                        . self::itemsTable($order)
                        . self::paymentBlock($order)
                        . self::methodBlock($order)
                    ),
                ];

            case 'picked_up':
                return [
                    'Parcel ' . ($item['parcel_id'] ?? '') . ' picked up',
                    self::layout(
                        'Picked up ✅',
                        '<p>Hi ' . self::e($order['customer_name']) . ',</p>'
                        . '<p>Your parcel <strong>' . self::e($item['parcel_id'] ?? '') . '</strong> '
                        . '(' . self::e($item['product_name'] ?? '') . ') from order <strong>' . self::e($order['order_number']) . '</strong> '
                        . 'has been picked up at the shop. Thank you for shopping with us!</p>'
                    ),
                ];

            case 'delivered':
                return [
                    'Parcel ' . ($item['parcel_id'] ?? '') . ' delivered',
                    self::layout(
                        'Out the door 🚚',
                        '<p>Hi ' . self::e($order['customer_name']) . ',</p>'
                        . '<p>Your parcel <strong>' . self::e($item['parcel_id'] ?? '') . '</strong> '
                        . '(' . self::e($item['product_name'] ?? '') . ') from order <strong>' . self::e($order['order_number']) . '</strong> '
                        . 'has been handed to the courier / marked delivered. You\'ll get one more note once it reaches you.</p>'
                    ),
                ];

            case 'arrived':
            default:
                return [
                    'Parcel ' . ($item['parcel_id'] ?? '') . ' arrived',
                    self::layout(
                        'Arrived 📦',
                        '<p>Hi ' . self::e($order['customer_name']) . ',</p>'
                        . '<p>Your parcel <strong>' . self::e($item['parcel_id'] ?? '') . '</strong> '
                        . '(' . self::e($item['product_name'] ?? '') . ') from order <strong>' . self::e($order['order_number']) . '</strong> '
                        . 'has arrived. We hope you love it — thank you for choosing ISMAN!</p>'
                    ),
                ];
        }
    }

    private static function itemsTable(array $order): string {
        $rows = '';
        foreach (($order['items'] ?? []) as $it) {
            $rows .= '<tr>'
                . '<td style="padding:8px 0;border-bottom:1px solid #eee;">' . self::e($it['product_name'])
                . '<br><span style="color:#888;font-size:12px;">Parcel ' . self::e($it['parcel_id']) . '</span></td>'
                . '<td style="padding:8px 0;border-bottom:1px solid #eee;text-align:center;">' . (int) $it['quantity'] . '</td>'
                . '<td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">' . format_price($it['line_total']) . '</td>'
                . '</tr>';
        }
        return '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">'
            . '<tr><th style="text-align:left;padding:8px 0;border-bottom:2px solid #0D9488;">Item</th>'
            . '<th style="text-align:center;padding:8px 0;border-bottom:2px solid #0D9488;">Qty</th>'
            . '<th style="text-align:right;padding:8px 0;border-bottom:2px solid #0D9488;">Total</th></tr>'
            . $rows
            . '<tr><td colspan="2" style="padding:10px 0;text-align:right;font-weight:bold;">Total paid</td>'
            . '<td style="padding:10px 0;text-align:right;font-weight:bold;color:#0D9488;">' . format_price($order['total']) . '</td></tr>'
            . '</table>';
    }

    private static function paymentBlock(array $order): string {
        if (empty($order['mpesa_receipt'])) return '';
        return '<div style="background:#f1f6f5;border-radius:8px;padding:12px 16px;margin:12px 0;font-size:13px;color:#44524f;">'
            . 'M-Pesa receipt: <strong>' . self::e($order['mpesa_receipt']) . '</strong>'
            . (!empty($order['mpesa_phone']) ? ' &middot; Paid from ' . self::e($order['mpesa_phone']) : '')
            . '</div>';
    }

    private static function methodBlock(array $order): string {
        if (($order['fulfillment_method'] ?? '') === 'delivery') {
            return '<p style="font-size:13px;color:#44524f;">Delivery to: <strong>' . self::e($order['pickup_location'] ?? '') . '</strong>. '
                . 'Delivery fees are arranged directly with the courier.</p>';
        }
        return '<p style="font-size:13px;color:#44524f;">Pickup at: <strong>' . self::e($order['pickup_location'] ?? '') . '</strong>.</p>';
    }

    private static function layout(string $heading, string $bodyHtml): string {
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#1a1a1a;">'
            . '<div style="text-align:center;padding:24px 0;border-bottom:1px solid #eee;">'
            . '<img src="cid:ismanlogo" alt="ISMAN" style="max-height:56px;">'
            . '</div>'
            . '<div style="padding:24px;">'
            . '<h2 style="color:#0D9488;margin:0 0 8px;">' . self::e($heading) . '</h2>'
            . $bodyHtml
            . '</div>'
            . '<div style="padding:16px 24px;border-top:1px solid #eee;color:#888;font-size:12px;text-align:center;">'
            . 'ISMAN Company &middot; Engineering Services<br>Need help? Reply to this email.'
            . '</div>'
            . '</div>';
    }

    private static function e($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}