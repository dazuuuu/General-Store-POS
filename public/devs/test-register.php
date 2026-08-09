<?php
// public/devs/test-register.php
// ⚠ DEV / TEST ONLY — DELETE THIS FILE BEFORE LAUNCH.
// This does NOT bypass anything. It simply drops you into the REAL registration
// flow on the hidden 10-bob "Test (2 weeks)" plan, so you can exercise the full
// production pipeline cheaply: real M-Pesa STK -> real activation -> real OTP
// login through the normal tenant login page. Key-guarded so the public can't
// reach the cheap plan.
require_once __DIR__ . '/../../app/app.php';

const DEV_KEY = 'modern-dev';   // change me; remove this whole file before launch
if (($_GET['key'] ?? '') !== DEV_KEY) {
    http_response_code(403);
    echo 'Forbidden. Append ?key=' . htmlspecialchars(DEV_KEY) . ' to use the test registration.';
    exit;
}

$pdo = Database::pdo();
$test = $pdo->query("SELECT * FROM subscription_plans WHERE name = 'Test (2 weeks)' AND is_active = 1 LIMIT 1")->fetch();

$page_title = 'Test registration';
ob_start();

if (!$test) {
    echo '<div class="auth-alert err">No active <strong>Test (2 weeks)</strong> plan found. Run migration 017 first.</div>';
} else {
    // Test plan only offers the 2-week interval (KES 10).
    $url = '/Modern/public/auth/register.php?plan_id=' . (int) $test['id'] . '&interval=biweekly';
    ?>
    <div class="auth-alert err" style="background:#fef3c7;color:#92400e;">⚠ Dev/test only — delete <code>public/devs/test-register.php</code> before launch.</div>
    <div class="auth-title">Test on the 10-bob plan</div>
    <div class="auth-sub">This runs the <strong>real</strong> flow — M-Pesa prompt, activation, and OTP login — on the
        <strong>Test (2 weeks)</strong> plan at <strong>KES 10</strong>. Nothing is skipped.</div>
    <div style="background:#f1f6f5;border:1px solid #e2eae8;border-radius:12px;padding:14px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;">
        <div><div style="font-weight:700;">Every 2 weeks</div><div class="text-muted small">Test (2 weeks) plan</div></div>
        <div style="font-weight:800;font-size:1.1rem;">KES 10</div>
    </div>
    <a class="btn-auth d-block text-center text-decoration-none" href="<?php echo $url; ?>">Continue to registration</a>
    <div class="auth-foot">You'll enter your business name, email, M-Pesa phone &amp; password next, then pay KES 10.</div>
    <?php
}

$content = ob_get_clean();
include ROOT_PATH . '/public/templates/auth/layout.php';