<?php
// public/templates/tenants/layout.php
// Tenant (owner) chrome. Branding is dynamic per tenant. Pages set $page_title
// and $content, then include this. The page is responsible for calling
// PageGuard::tenant() before building $content.

$__tenant = $__tenant ?? (TenantContext::tenantId()
    ? (new Models\TenantModel(Database::pdo()))->find(TenantContext::tenantId())
    : null);
$shopName = $__tenant['name'] ?? 'My Shop';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?> — <?php echo htmlspecialchars($shopName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{ --pos-violet:#4b006e; --pos-violet-dark:#32004b; --pos-violet-light:#f5ecff; --pos-green:var(--pos-violet); --pos-green-dark:var(--pos-violet-dark); --pos-green-light:var(--pos-violet-light); --pos-bg:#f3f3f4; --pos-ink:#17151d; }
        *{box-sizing:border-box;} body{margin:0;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:var(--pos-bg);color:var(--pos-ink);}
        .t-wrap{display:flex;min-height:100vh;}
        .t-main{flex:1;margin-left:264px;padding:26px 30px;width:calc(100% - 264px);}
        .t-topbar{background:#fff;border:1px solid #eef0f4;border-radius:14px;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:22px;box-shadow:0 1px 3px rgba(16,24,40,.04);}
        .t-topbar h1{font-size:1.35rem;margin:0;font-weight:700;color:var(--pos-ink);}
        .t-flash{border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:.92rem;}
        .t-flash.ok{background:var(--pos-violet-light);color:var(--pos-violet-dark);} .t-flash.err{background:#fee2e2;color:#991b1b;}
        @media (max-width:992px){ .t-main{margin-left:0;width:100%;padding:18px;} .t-topbar{margin-top:54px;} }
        @media (max-width:576px){ .t-main{padding:14px;} .t-topbar h1{font-size:1.15rem;} }

        /* ---- site-wide red/light POS theme ---- */
        .btn-primary{ --bs-btn-bg:var(--pos-green); --bs-btn-border-color:var(--pos-green); --bs-btn-hover-bg:var(--pos-green-dark); --bs-btn-hover-border-color:var(--pos-green-dark); --bs-btn-active-bg:var(--pos-green-dark); --bs-btn-active-border-color:var(--pos-green-dark); --bs-btn-disabled-bg:var(--pos-green); --bs-btn-disabled-border-color:var(--pos-green); }
        .btn-outline-primary{ --bs-btn-color:var(--pos-green); --bs-btn-border-color:var(--pos-green); --bs-btn-hover-bg:var(--pos-green); --bs-btn-hover-border-color:var(--pos-green); --bs-btn-active-bg:var(--pos-green); --bs-btn-active-border-color:var(--pos-green); }
        .btn-success{ --bs-btn-bg:var(--pos-violet); --bs-btn-border-color:var(--pos-violet); --bs-btn-hover-bg:var(--pos-violet-dark); --bs-btn-hover-border-color:var(--pos-violet-dark); --bs-btn-active-bg:var(--pos-violet-dark); --bs-btn-active-border-color:var(--pos-violet-dark); }
        .btn-outline-success{ --bs-btn-color:var(--pos-violet); --bs-btn-border-color:var(--pos-violet); --bs-btn-hover-bg:var(--pos-violet); --bs-btn-hover-border-color:var(--pos-violet); --bs-btn-active-bg:var(--pos-violet); --bs-btn-active-border-color:var(--pos-violet); }
        a{ color:var(--pos-green); }
        .text-primary{ color:var(--pos-green) !important; }
        .text-success{ color:var(--pos-violet) !important; }
        .bg-primary{ background-color:var(--pos-green) !important; }
        .bg-success{ background-color:var(--pos-violet) !important; }
        .badge.bg-primary{ background-color:var(--pos-green) !important; }
        .badge.bg-success{ background-color:var(--pos-violet) !important; }
        .form-control:focus, .form-select:focus{ border-color:var(--pos-green); box-shadow:0 0 0 .2rem rgba(75,0,110,.14); }
        .card{ border-radius:14px; }
        .table thead th{ color:#8a8f9c; font-size:.72rem; letter-spacing:.04em; }
    </style>
    <?php echo $extra_css ?? ''; ?>
</head>
<body>
<div class="t-wrap">
    <?php include __DIR__ . '/../../components/tenants/sidebar.php'; ?>
    <main class="t-main">
        <div class="t-topbar">
            <h1><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></h1>
            <div class="text-muted small d-none d-md-block"><?php echo date('l, j M Y'); ?></div>
        </div>

        <?php if (!empty($_SESSION['flash']['success'])): ?>
            <div class="t-flash ok"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash']['error'])): ?>
            <div class="t-flash err"><?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
        <?php endif; ?>
        <?php foreach ((new Models\NotificationModel(Database::pdo()))->recentForCurrentUser(3) as $n): ?>
            <div class="alert alert-warning py-2 small d-flex justify-content-between align-items-center gap-2">
                <span><strong><?php echo htmlspecialchars($n['title']); ?>:</strong> <?php echo htmlspecialchars($n['message']); ?></span>
                <?php if (!empty($n['url'])): ?><a class="btn btn-sm btn-outline-dark" href="<?php echo public_url($n['url']); ?>">Open</a><?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php echo $content ?? ''; ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php echo $extra_js ?? ''; ?>
</body>
</html>
