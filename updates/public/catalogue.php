<?php
// public/catalogue.php
// Public-facing product catalogue. No login required.
// URL: /2in1/public/catalogue.php?shop=<slug>

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
require_once ROOT_PATH . '/app/helpers/PathConfig.php';
if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}
spl_autoload_register(function ($class) {
    foreach (['Models\\' => '/app/models/', 'Controllers\\' => '/app/controllers/'] as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = ROOT_PATH . $dir . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
    foreach (['/app/helpers/', '/app/services/'] as $dir) {
        $file = ROOT_PATH . $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});
if (session_status() === PHP_SESSION_NONE) { session_start(); }
TenantContext::boot();

require_once ROOT_PATH . '/app/helpers/Branding.php';

$pdo = Database::pdo();

// ---- Resolve tenant by slug ----
$shopSlug = trim($_GET['shop'] ?? '');
if ($shopSlug === '') {
    http_response_code(404);
    die('<h1>No shop specified.</h1>');
}

$stmtT = $pdo->prepare("SELECT * FROM tenants WHERE slug = ? AND status = 'active' LIMIT 1");
$stmtT->execute([$shopSlug]);
$tenant = $stmtT->fetch(PDO::FETCH_ASSOC);
if (!$tenant) {
    http_response_code(404);
    die('<h1>Shop not found.</h1>');
}
$tenantId = (int) $tenant['id'];

// ---- Products (all active, no quantity/cost shown) ----
$P = new Models\ProductModel($pdo);
$products = $P->catalogueForTenant($tenantId);

// ---- Branding ----
$shopName   = $tenant['name'] ?? 'Our Shop';
$shopPhone  = $tenant['phone'] ?? '';
$shopAddr   = $tenant['address'] ?? '';
$currency   = $tenant['currency'] ?? 'KES';
$logoUrl    = Branding::tenantLogo($tenant);

$baseUrl    = public_url('catalogue.php?shop=' . urlencode($shopSlug));
$shareUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . $baseUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shopName); ?> — Product Catalogue</title>
    <meta name="description" content="Browse the full product catalogue for <?php echo htmlspecialchars($shopName); ?>.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        :root {
            --brand: #2563eb;
            --brand-dark: #1d4ed8;
            --brand-light: #eff6ff;
            --accent: #f59e0b;
            --text: #0f172a;
            --muted: #64748b;
            --bg: #f8fafc;
            --card-bg: #fff;
            --radius: 16px;
            --shadow: 0 4px 24px rgba(0,0,0,.07);
            --shadow-hover: 0 8px 40px rgba(37,99,235,.15);
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; min-height: 100vh; }

        /* ---- Hero Header ---- */
        .cat-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1d4ed8 100%);
            color: #fff;
            padding: 48px 24px 40px;
            position: relative;
            overflow: hidden;
        }
        .cat-hero::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse 60% 80% at 90% 10%, rgba(251,191,36,.18) 0%, transparent 55%),
                        radial-gradient(ellipse 40% 60% at 5% 80%, rgba(99,102,241,.15) 0%, transparent 55%);
            pointer-events: none;
        }
        .cat-hero-inner { position: relative; max-width: 1140px; margin: 0 auto; display: flex; align-items: center; gap: 24px; }
        .cat-logo {
            width: 72px; height: 72px; border-radius: 14px; background: rgba(255,255,255,.12);
            backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,.2);
            object-fit: contain; padding: 8px; flex-shrink: 0;
        }
        .cat-shop-name { font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; line-height: 1.1; }
        .cat-shop-meta { font-size: .85rem; color: rgba(255,255,255,.7); margin-top: 4px; display: flex; gap: 16px; flex-wrap: wrap; }
        .cat-shop-meta i { margin-right: 4px; }
        .cat-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.12); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,.2); border-radius: 999px; padding: 4px 14px; font-size: .8rem; color: rgba(255,255,255,.9); margin-top: 10px; }


        /* ---- Main content ---- */
        .cat-main { max-width: 1140px; margin: 0 auto; padding: 28px 20px 60px; }

        /* ---- Search ---- */
        .cat-search-wrap { margin-bottom: 28px; position: relative; }
        .cat-search { width: 100%; padding: 14px 20px 14px 48px; border: 2px solid #e2e8f0; border-radius: 14px; font-size: .95rem; outline: none; transition: border-color .2s, box-shadow .2s; background: #fff; }
        .cat-search:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(37,99,235,.1); }
        .cat-search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1rem; pointer-events: none; }
        .cat-count { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); font-size: .8rem; color: var(--muted); font-weight: 500; }

        /* ---- Product Grid ---- */
        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        @media (max-width:576px) { .cat-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; } }

        .cat-card {
            background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow);
            overflow: hidden; transition: transform .2s, box-shadow .2s; display: flex; flex-direction: column;
        }
        .cat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }

        .cat-card-img {
            aspect-ratio: 1; background: #f1f5f9; position: relative; overflow: hidden;
        }
        .cat-card-img img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s; }
        .cat-card:hover .cat-card-img img { transform: scale(1.06); }
        .cat-card-img-placeholder {
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
        }
        .cat-card-img-placeholder i { font-size: 2.5rem; color: #94a3b8; }

        .cat-card-badge {
            position: absolute; top: 8px; left: 8px; background: var(--brand); color: #fff;
            font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
            padding: 3px 9px; border-radius: 999px;
        }

        .cat-card-body { padding: 14px 16px 16px; flex: 1; display: flex; flex-direction: column; }
        .cat-card-category { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--brand); margin-bottom: 4px; }
        .cat-card-name { font-size: .92rem; font-weight: 700; line-height: 1.3; margin-bottom: 8px; flex: 1; }
        .cat-card-price { font-size: 1.05rem; font-weight: 800; color: var(--brand-dark); }
        .cat-card-price .cur { font-size: .7rem; font-weight: 600; color: var(--muted); margin-right: 2px; vertical-align: super; }

        /* ---- Empty / no-match state ---- */
        .cat-empty { text-align: center; padding: 60px 20px; }
        .cat-empty i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        .cat-empty h3 { font-size: 1.1rem; font-weight: 700; color: var(--text); }
        .cat-empty p { color: var(--muted); font-size: .9rem; }

        /* ---- Footer ---- */
        .cat-footer { text-align: center; padding: 24px; font-size: .78rem; color: var(--muted); border-top: 1px solid #e2e8f0; }
        .cat-footer strong { color: var(--text); }
    </style>
</head>
<body>

<!-- Hero -->
<div class="cat-hero">
    <div class="cat-hero-inner">
        <img class="cat-logo" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($shopName); ?>">
        <div>
            <div class="cat-shop-name"><?php echo htmlspecialchars($shopName); ?></div>
            <div class="cat-shop-meta">
                <?php if ($shopPhone): ?><span><i class="fas fa-phone"></i><?php echo htmlspecialchars($shopPhone); ?></span><?php endif; ?>
                <?php if ($shopAddr):  ?><span><i class="fas fa-location-dot"></i><?php echo htmlspecialchars($shopAddr); ?></span><?php endif; ?>
            </div>
            <div class="cat-badge"><i class="fas fa-store"></i> <?php echo count($products); ?> product<?php echo count($products) !== 1 ? 's' : ''; ?> available</div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="cat-main">
    <!-- Search -->
    <div class="cat-search-wrap">
        <i class="fas fa-search cat-search-icon"></i>
        <input type="text" id="catSearch" class="cat-search" placeholder="Search products by name or category…" autocomplete="off">
        <span class="cat-count" id="catCount"><?php echo count($products); ?> products</span>
    </div>

    <?php if (!$products): ?>
    <div class="cat-empty">
        <i class="fas fa-box-open"></i>
        <h3>No products yet</h3>
        <p>This shop hasn't added any products to their catalogue yet.</p>
    </div>
    <?php else: ?>
    <div class="cat-grid" id="catGrid">
        <?php foreach ($products as $p):
            $cat = $p['category_name'] ? htmlspecialchars($p['category_name']) : '';
            if ($p['subcategory_name']) $cat .= ($cat ? ' · ' : '') . htmlspecialchars($p['subcategory_name']);
        ?>
        <div class="cat-card" data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>" data-cat="<?php echo strtolower(strip_tags($cat)); ?>">
            <div class="cat-card-img">
                <?php if (!empty($p['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                <?php else: ?>
                    <div class="cat-card-img-placeholder"><i class="fas fa-box"></i></div>
                <?php endif; ?>
                <?php if ($cat): ?><span class="cat-card-badge"><?php echo $cat; ?></span><?php endif; ?>
            </div>
            <div class="cat-card-body">
                <?php if ($cat): ?><div class="cat-card-category"><?php echo $cat; ?></div><?php endif; ?>
                <div class="cat-card-name"><?php echo htmlspecialchars($p['name']); ?><?php $sz = Models\ProductModel::sizeLabel($p); ?><?php echo $sz ? ' <span class="text-muted" style="font-weight:400;">— ' . htmlspecialchars($sz) . '</span>' : ''; ?></div>
                <div class="cat-card-price">
                    <span class="cur"><?php echo htmlspecialchars($currency); ?></span><?php echo number_format((float)$p['selling_price'], 0); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div id="catNoMatch" class="cat-empty" style="display:none;">
        <i class="fas fa-search"></i>
        <h3>No products match</h3>
        <p>Try a different search term.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<footer class="cat-footer">
    Powered by <strong>Modern POS</strong> &mdash; <?php echo htmlspecialchars($shopName); ?>
</footer>

<script>
(function () {
    var search  = document.getElementById('catSearch');
    var cards   = document.querySelectorAll('.cat-card');
    var noMatch = document.getElementById('catNoMatch');
    var countEl = document.getElementById('catCount');
    var total   = cards.length;

    if (!search) return;
    search.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        var visible = 0;
        cards.forEach(function (c) {
            var show = !q || c.dataset.name.indexOf(q) !== -1 || c.dataset.cat.indexOf(q) !== -1;
            c.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noMatch) noMatch.style.display = visible === 0 ? 'block' : 'none';
        if (countEl) countEl.textContent = visible + ' product' + (visible !== 1 ? 's' : '');
    });
})();
</script>
</body>
</html>
