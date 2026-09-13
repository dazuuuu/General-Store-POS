<?php
// public/staff/change-pin.php — self-service PIN change, reachable any time
// from the staff dashboard (not just the forced first-login reset).
require_once __DIR__ . '/../../app/app.php';
PageGuard::staff();

$pdo = Database::pdo();
$svc = new StaffService($pdo);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = trim($_POST['current_pin'] ?? '');
    $new     = trim($_POST['new_pin'] ?? '');
    $confirm = trim($_POST['confirm_pin'] ?? '');

    if ($new !== $confirm) {
        $error = 'The new PIN and confirmation don\'t match.';
    } else {
        $res = $svc->changeOwnPin((int) TenantContext::tenantId(), (int) TenantContext::userId(), $current, $new);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'PIN changed. Use your new PIN next time you log in.';
            header('Location: ' . public_url('staff/dashboard/'));
            exit;
        }
        $error = $res['error'];
    }
}

$page_title = 'Change PIN';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="h5 mb-0 fw-bold">Change PIN</h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('staff/dashboard/'); ?>"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row">
  <div class="col-12 col-md-6 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <p class="text-muted small mb-3">This is the PIN you use with your name at the staff terminal to log in.</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <div class="mb-3">
            <label class="form-label">Current PIN</label>
            <input name="current_pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">New PIN</label>
            <input name="new_pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6" class="form-control" placeholder="4 to 6 digits" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Confirm new PIN</label>
            <input name="confirm_pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6" class="form-control" required>
          </div>
          <button class="btn btn-primary">Save new PIN</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/staff/layout.php';
