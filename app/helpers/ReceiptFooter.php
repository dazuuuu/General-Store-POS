<?php
// Default + formatted receipt closing block for printed/emailed receipts.

class ReceiptFooter
{
    public const DEFAULT_LINES = [
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
        if ($invoiceNumber) {
            $parts[] = '<div style="font-size:12px;font-weight:700;margin-bottom:6px;">Invoice: '
                . $h($invoiceNumber) . '</div>';
        }
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
