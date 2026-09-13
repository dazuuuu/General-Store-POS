<?php
// public/templates/auth/layout.php
// Centered card used by register / login / otp-verify / activate / forgot-password.
// Shows the real business's logo (single-tenant deployment — see TenantModel::primary()).

$__authTenant = $__authTenant ?? (new Models\TenantModel(Database::pdo()))->primary();
$__authShop   = $__authTenant['name'] ?? '2in1';
$__authLogo   = Branding::tenantLogo($__authTenant);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Admin'); ?> — <?php echo htmlspecialchars($__authShop); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{ --pos-green:#16a34a; --pos-green-dark:#15803d; --pos-green-light:#f0fdf4; --pos-bg:#f7f7fb; --pos-ink:#1f2330; }
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;
             background:var(--pos-bg);font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;padding:40px 20px;}

        .auth-card{width:420px;max-width:100%;background:#fff;border:1px solid #eef0f4;border-radius:20px;
                   box-shadow:0 12px 40px rgba(16,24,40,.08);overflow:hidden;}
        .auth-head{padding:32px 32px 0;text-align:center}
        .logo-icon{width:56px;height:56px;border-radius:14px;overflow:hidden;background:var(--pos-green-light);
                   border:1px solid #f3d3d3;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px}
        .logo-icon img{width:100%;height:100%;object-fit:contain}
        .logo-icon i{color:var(--pos-green);font-size:22px}
        .logo-name{font-size:1.1rem;font-weight:800;color:var(--pos-ink);letter-spacing:-.02em;display:block}
        .logo-role{display:inline-block;margin-top:4px;padding:2px 10px;border-radius:999px;background:#f3f4f7;
                   color:#5b6070;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
        .auth-body{padding:20px 32px 32px}
        .auth-title{font-size:1.4rem;font-weight:800;color:var(--pos-ink);text-align:center;margin:0 0 6px;letter-spacing:-.02em}
        .auth-sub{color:#7a7f8c;font-size:.88rem;text-align:center;margin-bottom:22px;line-height:1.5}

        .form-label{display:block;font-size:.8rem;font-weight:600;color:#5b6070;margin-bottom:6px}
        .field-wrap{position:relative}
        .field-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#b7bac3;pointer-events:none}
        .form-control{border:1px solid #e2e4ea!important;border-radius:12px!important;padding:11px 14px!important;
                      font-size:.92rem!important;color:var(--pos-ink)!important}
        .field-wrap .form-control{padding-left:42px!important}
        .form-control:focus{border-color:var(--pos-green)!important;box-shadow:0 0 0 .2rem rgba(22,163,74,.1)!important}
        .field-focus-line{display:none}

        .btn-auth{width:100%;padding:13px;font-weight:700;font-size:.95rem;
                  background:var(--pos-green)!important;border:none!important;border-radius:12px!important;
                  color:#fff!important;letter-spacing:.02em;transition:background .15s!important}
        .btn-auth:hover{background:var(--pos-green-dark)!important}

        .auth-alert{border-radius:10px;padding:10px 14px;font-size:.88rem;margin-bottom:16px}
        .auth-alert.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .auth-alert.ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
        .otp-input{letter-spacing:.5em;text-align:center;font-size:1.4rem;font-weight:700}

        .auth-foot{text-align:center;margin-top:18px;font-size:.85rem;color:#7a7f8c}
        .auth-foot a{color:var(--pos-green);text-decoration:none;font-weight:600}
        .auth-foot a:hover{text-decoration:underline}

        @media(max-width:480px){
          .auth-head{padding:24px 24px 0}
          .auth-body{padding:16px 24px 24px}
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-head">
            <div class="logo-icon">
                <?php if ($__authLogo): ?><img src="<?php echo htmlspecialchars($__authLogo); ?>" alt="">
                <?php else: ?><i class="fas fa-layer-group"></i><?php endif; ?>
            </div>
            <span class="logo-name"><?php echo htmlspecialchars($__authShop); ?></span>
            <span class="logo-role">Admin</span>
        </div>
        <div class="auth-body">
            <?php echo $content ?? ''; ?>
        </div>
    </div>
</body>
</html>
