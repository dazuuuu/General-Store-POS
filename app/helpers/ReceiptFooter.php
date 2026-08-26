<?php
// Default + formatted receipt closing block for printed/emailed receipts.

class ReceiptFooter
{
    public const SHOP_NAME = 'DUAQABE GENERAL STORE LIMITED';
    public const SHOP_BOX = 'P.O.BOX 631-00610, NAIROBI';
    public const SHOP_LOCATION = 'Haji adar plaza WAJIR';
    public const SHOP_EMAIL = 'duaqabegeneralstore@gmail.com';
    public const SHOP_PHONE = '0721713350,0711257332';

    public const DEFAULT_LINES = [
        'For questions and enquiries:',
        self::SHOP_EMAIL,
        self::SHOP_PHONE,
        'GOODS ONCE SOLD ARE NOT REACCEPTED',
        '!THANK YOU FOR VISITING OUR BUSINESS!',
        '!!WELCOME!!',
    ];

    /** Resolve footer text from tenant settings (or the shop default). */
    public static function text(?array $tenant): string
    {
        $custom = trim((string) ($tenant['receipt_footer'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        return implode("\n", self::DEFAULT_LINES);
    }

    /** HTML block for thermal / email receipts. */
    public static function html(?array $tenant, ?string $invoiceNumber = null): string
    {
        $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $lines = preg_split("/\r\n|\n|\r/", self::text($tenant)) ?: [];
        $parts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts[] = '<div style="font-size:11px;letter-spacing:.02em;margin:2px 0;">' . $h($line) . '</div>';
        }
        return '<div style="text-align:center;color:#64748b;margin-top:14px;border-top:1px dashed #cbd5e1;padding-top:10px;">'
            . implode('', $parts) . '</div>';
    }
}
