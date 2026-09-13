<?php
// public/auth/forgot-password.php
// Step 1: enter email → issue password_reset OTP → redirect to step 2.
// Works for both tenant owners and staff (same users table).
require_once __DIR__ . '/../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/password_reset_email.php';

// Already logged in → go home
if (!empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified'])) {
    header('Location: ' . public_url('auth/login.php'));
    exit;
}

// Already have a reset in progress → jump to step 2
if (!empty($_SESSION['reset_user_id'])) {
    header('Location: ' . public_url('auth/reset-otp.php'));
    exit;
}

$pdo   = Database::pdo();
$error = '';
$sent  = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Look up the user — works for both owners and staff
        $stmt = $pdo->prepare(
            'SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ? AND u.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Don't reveal whether the email exists — same message either way
            $sent = true; // show "if found, check inbox" message
        } else {
            $shopName = '2in1';
            if ($user['tenant_id'] !== null) {
                $t = (new Models\TenantModel($pdo))->find((int) $user['tenant_id']);
                $shopName = $t['name'] ?? '2in1';
            }

            $otp   = new OtpService($pdo);
            $issue = $otp->issue(
                (int) $user['id'],
                $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
                'password_reset',
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            if ($issue['ok']) {
                $msg    = build_password_reset_email($issue['code'], $shopName);
                $mailer = new MailService();
                $mailer->send($user['email'], $msg['subject'], $msg['html']);

                // Store minimal state for step 2 (no password data stored in session)
                session_regenerate_id(true);
                $_SESSION['reset_user_id']   = (int) $user['id'];
                $_SESSION['reset_email']     = $user['email'];
                $_SESSION['reset_tenant_id'] = $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null;
                $_SESSION['reset_shop']      = $shopName;

                header('Location: ' . public_url('auth/reset-otp.php'));
                exit;
            } elseif ($issue['reason'] === 'cooldown') {
                // Already have a live code — just redirect
                $_SESSION['reset_user_id']   = (int) $user['id'];
                $_SESSION['reset_email']     = $user['email'];
                $_SESSION['reset_tenant_id'] = $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null;
                $_SESSION['reset_shop']      = $shopName;
                header('Location: ' . public_url('auth/reset-otp.php'));
                exit;
            } else {
                $error = 'Could not send a reset code right now. Please try again shortly.';
            }
        }
    }
}

$page_title = 'Forgot password';
ob_start();
?>
<div class="auth-title">Reset your password</div>
<div class="auth-sub">Enter the email address linked to your account and we'll send you a code.</div>
<?php if ($error): ?><div class="auth-alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($sent): ?>
<div class="auth-alert ok">
  If that email address is registered, a reset code is on its way. Check your inbox (and spam folder).
</div>
<div class="auth-foot" style="margin-top:10px;">
  <a href="<?php echo public_url('auth/login.php'); ?>">Back to login</a>
</div>
<?php else: ?>
<form method="post" novalidate>
    <div class="mb-4">
        <label class="form-label">Email address</label>
        <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" autofocus placeholder="you@example.com">
    </div>
    <button class="btn-auth">Send reset code</button>
</form>
<div class="auth-foot"><a href="<?php echo public_url('auth/login.php'); ?>">Back to login</a></div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include ROOT_PATH . '/public/templates/auth/layout.php';
