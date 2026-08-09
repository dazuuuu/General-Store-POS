<?php
// public/auth/activate.php
require_once __DIR__ . '/../../app/app.php';
require_once ROOT_PATH . '/app/helpers/PathConfig.php';

$reg = new RegistrationService(Database::pdo());
$token = $_GET['token'] ?? '';
$res = $token ? $reg->activate($token) : ['ok' => false, 'reason' => 'invalid'];

$messages = [
    'invalid'           => 'This activation link is invalid.',
    'expired'           => 'This activation link has expired. Please register again.',
    'already_activated' => 'This account is already activated. You can log in.',
    'error'             => 'Something went wrong. Please try again.',
];

$page_title = 'Account activation';
ob_start();
if ($res['ok']): ?>
    <div class="auth-title">You're all set 🎉</div>
    <div class="auth-sub">Your account is active. Log in to reach your dashboard.</div>
    <a class="btn-auth d-block text-center text-decoration-none" href="<?php echo public_url('auth/login.php'); ?>">Log in</a>
<?php else: ?>
    <div class="auth-title">Activation problem</div>
    <div class="auth-alert err"><?php echo htmlspecialchars($messages[$res['reason']] ?? 'Activation failed.'); ?></div>
    <div class="auth-foot"><a href="<?php echo public_url('auth/login.php'); ?>">Go to login</a></div>
<?php endif;
$content = ob_get_clean();
include ROOT_PATH . '/public/templates/auth/layout.php';