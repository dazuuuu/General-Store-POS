<?php
// public/auth/reset-otp.php
// Step 2: verify OTP + choose new password. All on one page.
// Session: reset_user_id, reset_email, reset_tenant_id, reset_shop
require_once __DIR__ . '/../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/password_reset_email.php';

// Guard: must have a reset in progress
if (empty($_SESSION['reset_user_id'])) {
    header('Location: ' . public_url('auth/forgot-password.php'));
    exit;
}

$pdo       = Database::pdo();
$userId    = (int) $_SESSION['reset_user_id'];
$resetEmail = $_SESSION['reset_email'] ?? '';
$tenantId  = isset($_SESSION['reset_tenant_id']) ? (int) $_SESSION['reset_tenant_id'] : null;
$shopName  = $_SESSION['reset_shop'] ?? 'Modern POS';

$otp    = new OtpService($pdo);
$error  = '';
$notice = '';

// Resend
if (($_GET['resend'] ?? '') === '1') {
    $issue = $otp->issue($userId, $tenantId, 'password_reset', $_SERVER['REMOTE_ADDR'] ?? null);
    if ($issue['ok']) {
        $msg = build_password_reset_email($issue['code'], $shopName);
        (new MailService())->send($resetEmail, $msg['subject'], $msg['html']);
        $notice = 'A new code has been sent to your inbox.';
    } else {
        $error = OtpService::message($issue['reason']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\D+/', '', $_POST['code'] ?? '');
    $pw   = $_POST['password'] ?? '';
    $pw2  = $_POST['confirm'] ?? '';

    // Validate new password first (before burning the OTP)
    if (strlen($pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pw !== $pw2) {
        $error = 'The passwords do not match.';
    } else {
        $res = $otp->verify($userId, 'password_reset', $code);
        if ($res['ok']) {
            // Update the password
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE id = ?');
            $stmt->execute([password_hash($pw, PASSWORD_DEFAULT), $userId]);

            // Clear the reset session
            unset(
                $_SESSION['reset_user_id'], $_SESSION['reset_email'],
                $_SESSION['reset_tenant_id'], $_SESSION['reset_shop']
            );

            header('Location: ' . public_url('auth/login.php') . '?reset=1');
            exit;
        }
        $error = OtpService::message($res['reason']);
    }
}

$maskedEmail = preg_replace_callback(
    '/^(.).*(.@.*)$/',
    fn($m) => $m[1] . '****' . $m[2],
    $resetEmail
);

$page_title = 'Enter your code';
ob_start();
?>
<div class="auth-title">Check your inbox</div>
<div class="auth-sub">
  We sent a 6-digit code to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>.<br>
  Enter it below along with your new password.
</div>
<?php if ($error): ?><div class="auth-alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($notice): ?><div class="auth-alert ok"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

<form method="post" novalidate>
    <div class="mb-3">
        <label class="form-label">6-digit code</label>
        <input name="code" class="form-control otp-input" maxlength="6" inputmode="numeric"
               autocomplete="one-time-code" placeholder="••••••" autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label">New password</label>
        <input name="password" type="password" class="form-control" placeholder="At least 8 characters">
    </div>
    <div class="mb-4">
        <label class="form-label">Confirm new password</label>
        <input name="confirm" type="password" class="form-control">
    </div>
    <button class="btn-auth">Set new password</button>
</form>
<div class="auth-foot">
    Didn't get it? <a href="<?php echo public_url('auth/reset-otp.php'); ?>?resend=1">Resend code</a><br>
    <a href="<?php echo public_url('auth/forgot-password.php'); ?>">Try a different email</a>
</div>
<?php
$content = ob_get_clean();
include ROOT_PATH . '/public/templates/auth/layout.php';
