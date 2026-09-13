<?php
// public/staff/reset-password.php
// First-login (or forced) password change for staff. Reachable only when fully
// authenticated as staff; uses its own light guard so it can't redirect to itself.
require_once __DIR__ . '/../../app/app.php';

$loggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified']) && TenantContext::check();
if (!$loggedIn || TenantContext::role() !== 'staff') {
    header('Location: ' . public_url('auth/login.php'));
    exit;
}

// Nothing to reset → go to the dashboard.
if (empty($_SESSION['must_reset'])) {
    header('Location: ' . public_url('staff/dashboard/'));
    exit;
}

$pdo = Database::pdo();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm']  ?? '';
    if (strlen($pw) < 8) {
        $error = 'Use at least 8 characters.';
    } elseif ($pw !== $pw2) {
        $error = 'The two passwords don\'t match.';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE id = ?');
        $stmt->execute([password_hash($pw, PASSWORD_DEFAULT), TenantContext::userId()]);
        // Log out so the staff signs in fresh with their new password.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: ' . public_url('auth/login.php') . '?reset=1');
        exit;
    }
}

$page_title = 'Set your password';
ob_start();
?>
<div class="auth-title">Set your password</div>
<div class="auth-sub">This is your first login. Choose a password you'll remember to replace the temporary one.</div>
<?php if ($error): ?><div class="auth-alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post" novalidate>
    <div class="mb-3">
        <label class="form-label">New password</label>
        <input name="password" type="password" class="form-control" placeholder="At least 8 characters" autofocus>
    </div>
    <div class="mb-4">
        <label class="form-label">Confirm password</label>
        <input name="confirm" type="password" class="form-control">
    </div>
    <button class="btn-auth">Save &amp; continue</button>
</form>
<?php
$content = ob_get_clean();
include ROOT_PATH . '/public/templates/auth/layout.php';