<?php
// Shared inline SVG icons matching the owner sidebar (no emoji, no FA dependency for these).
class NavIcons
{
    /** @return array<string,string> name => svg path(s) inner content */
    private static function paths(): array
    {
        return [
            'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/>',
            'shop' => '<path d="M4 9h16l-1 11H5L4 9zm1-5h14l1 3H4l1-3zm3 8v5m5-5v5m5-5v5"/>',
            'sales' => '<path d="M7 7h10v13H7zM9 4h6v3H9zM9 11h6M9 15h4"/>',
            'invoices' => '<path d="M7 3h8l4 4v14H7V3zm8 0v4h4M10 12h6M10 16h6M10 8h3"/>',
            'credit' => '<path d="M4 12a8 8 0 1 0 8-8M12 8v4l3 2"/>',
            'payments' => '<path d="M3 7h18v10H3zM3 10h18M7 14h3"/>',
            'inventory' => '<path d="M4 8h16v11H4zM8 8V5h8v3M9 13h6"/>',
            'store' => '<path d="M4 9h16v11H4zM4 9l2-5h12l2 5M9 13h6"/>',
            'expenses' => '<path d="M12 3v18M7 8h8a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h8"/>',
            'finances' => '<path d="M12 3a9 9 0 1 0 9 9M12 7v5l3 2"/>',
            'stock' => '<path d="M4 8h7v7H4zM13 8h7v7h-7zM8.5 15v4M15.5 15v4M6 19h5M13 19h5"/>',
            'settings' => '<path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'logout' => '<path d="M10 4H5v16h5M14 8l4 4-4 4M9 12h9"/>',
            'overview' => '<path d="M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z"/>',
            'search' => '<circle cx="11" cy="11" r="6"/><path d="m20 20-4-4"/>',
            'bell' => '<path d="M6 16h12l-1.2-2V10a4.8 4.8 0 0 0-9.6 0v4L6 16zm4 2a2 2 0 0 0 4 0"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
            'cart' => '<path d="M3 5h2l2.2 10h10.3L20 8H7M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'chart' => '<path d="M4 19h16M7 16V9m5 7V5m5 11v-6"/>',
            'transfer' => '<path d="M7 8h12l-3-3M17 16H5l3 3"/>',
            'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
            'chevron' => '<path d="m8 10 4 4 4-4"/>',
            'invoice-dollar' => '<path d="M7 3h8l4 4v14H7V3zm8 0v4h4M10 13h2.5a1.5 1.5 0 0 0 0-3H11a1.5 1.5 0 0 0 0 3h1.5a1.5 1.5 0 0 1 0 3H10M12 8v1m0 8v1"/>',
        ];
    }

    public static function svg(string $name, int $size = 18, string $class = ''): string
    {
        $paths = self::paths();
        $inner = $paths[$name] ?? $paths['overview'];
        $cls = trim('nav-svg ' . $class);
        return '<svg class="' . htmlspecialchars($cls) . '" width="' . (int) $size . '" height="' . (int) $size
            . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
    }
}
