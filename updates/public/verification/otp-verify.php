<?php
// public/verification/otp-verify.php
// Step 2 of login: verify the emailed OTP. Only on success is the full session
// established (logged_in + otp_verified) and TenantContext populated.
require_once __DIR__ . '/../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/otp_email.php';

$pendingId = $_SESSION['pending_user_id'] ?? null;
if (!$pendingId) {
    header('Location: ' . public_url('auth/login.php'));
    exit;
}

$pdo = Database::pdo();
$otp = new OtpService($pdo);
$error = '';
$notice = '';

// Resend
if (($_GET['resend'] ?? '') === '1') {
    $tid = $_SESSION['pending_tenant_id'] ?? null;
    $issue = $otp->issue((int) $pendingId, $tid !== null ? (int) $tid : null, 'login_2fa', $_SERVER['REMOTE_ADDR'] ?? null);
    if ($issue['ok']) {
        $shop = '';
        if ($tid !== null) { $t = (new Models\TenantModel($pdo))->find((int) $tid); $shop = $t['name'] ?? ''; }
        $msg = build_otp_email($issue['code'], $shop !== '' ? $shop : 'your login');
        $sent = (new MailService())->send($_SESSION['pending_email'] ?? '', $msg['subject'], $msg['html']);
        $notice = 'A new code has been sent.';
        $appCfg = is_file(ROOT_PATH . '/app/config/app.php') ? require ROOT_PATH . '/app/config/app.php' : [];
        if (!empty($appCfg['otp_debug'])) {
            $_SESSION['dev_otp'] = $issue['code'];
            $_SESSION['dev_mail_error'] = $sent ? '' : MailService::lastError();
        }
    } else {
        $error = OtpService::message($issue['reason']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\D+/', '', $_POST['code'] ?? '');
    $res = $otp->verify((int) $pendingId, 'login_2fa', $code);
    if ($res['ok']) {
        // Full login. Load the user fresh and establish the real session.
        $stmt = $pdo->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
        $stmt->execute([(int) $pendingId]);
        $user = $stmt->fetch();

        session_regenerate_id(true);
        TenantContext::establish($pdo, $user);
        $_SESSION['username']     = $user['username'];
        $_SESSION['logged_in']    = true;
        $_SESSION['otp_verified'] = true;
        $_SESSION['first_login']  = true;
        unset($_SESSION['dev_otp']);
        unset($_SESSION['pending_user_id'], $_SESSION['pending_email'], $_SESSION['pending_tenant_id']);

        // First-time staff must change their temporary password before anything else.
        $_SESSION['must_reset'] = !empty($user['must_reset_password']);
        if ($_SESSION['must_reset'] && ($user['role_name'] ?? '') === 'staff') {
            header('Location: ' . public_url('staff/reset-password.php'));
            exit;
        }

        $dest = ($user['role_name'] === 'staff')
            ? public_url('staff/dashboard/')
            : public_url('super/dashboard/');
        header('Location: ' . $dest);
        exit;
    }
    $error = OtpService::message($res['reason']);
}

$maskedEmail = preg_replace_callback('/^(.).*(.@.*)$/', fn($m) => $m[1] . '****' . $m[2], $_SESSION['pending_email'] ?? '');

$page_title = 'Verify it\'s you';
ob_start();
?>
<div class="auth-title">Enter your code</div>
<div class="auth-sub">We emailed a 6-digit code to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>.</div>
<?php if ($error): ?><div class="auth-alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($notice): ?><div class="auth-alert ok"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

<form method="post" novalidate>
    <div class="mb-4">
        <input name="code" class="form-control otp-input" maxlength="6" inputmode="numeric"
               autocomplete="one-time-code" placeholder="••••••" autofocus>
    </div>
    <button class="btn-auth">Verify &amp; continue</button>
</form>
<div class="auth-foot">
    Didn't get it? <a href="<?php echo public_url('verification/otp-verify.php'); ?>?resend=1">Resend code</a><br>
    <a href="<?php echo public_url('auth/login.php'); ?>">Back to login</a>
</div>
<?php
$content = ob_get_clean();
include ROOT_PATH . '/public/templates/auth/layout.php';