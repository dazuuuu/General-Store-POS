<?php
// public/super/staff/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$tenantId = TenantContext::tenantId();
$svc = new StaffService($pdo);

$errors = [];
$old = ['name' => '', 'position' => '', 'pin' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $old = [
        'name'     => trim($_POST['name'] ?? ''),
        'position' => trim($_POST['position'] ?? ''),
        'pin'      => trim($_POST['pin'] ?? ''),
    ];

    $res = $svc->createWithPin((int) $tenantId, $old);

    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Staff account created for ' . $old['name'] . '. They log in with their name and PIN at the staff terminal.';
        header('Location: ' . public_url('super/staff/'));
        exit;
    }
    $errors = $res['errors'];

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    $id = (int) ($_POST['id'] ?? 0);
    $makeActive = ($_POST['make_active'] ?? '') === '1';
    $res = $svc->setActive((int) $tenantId, $id, $makeActive);
    $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
        ? ($makeActive ? 'Staff member unblocked.' : 'Staff member blocked — they can no longer log in or clock in/out.')
        : ($res['error'] ?? 'Could not update this staff member.');
    header('Location: ' . public_url('super/staff/'));
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_pin') {
    $id = (int) ($_POST['id'] ?? 0);
    $res = $svc->setPin((int) $tenantId, $id, trim($_POST['pin'] ?? ''));
    $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? "PIN reset — let them know their new PIN." : ($res['error'] ?? 'Could not reset that PIN.');
    header('Location: ' . public_url('super/staff/'));
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $res = $svc->deleteStaff((int) $tenantId, $id);
    $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? 'Staff member deleted.' : ($res['error'] ?? 'Could not delete this staff member.');
    header('Location: ' . public_url('super/staff/'));
    exit;
}

$staff = $svc->listForTenant((int) $tenantId);
$page_title = 'Staff';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Add staff</h2>
        <p class="text-muted small mb-3">Staff log in at the terminal with their name and PIN — no email or password needed. Set what they're allowed to do afterwards on the Permissions page.</p>
        <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Staff name</label>
            <input name="name" class="form-control" placeholder="e.g. Alice Wanjiru" value="<?php echo htmlspecialchars($old['name']); ?>" required autofocus>
            <?php if (!empty($errors['name'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['name']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Position <span class="text-muted">(optional)</span></label>
            <input name="position" class="form-control" placeholder="e.g. Cashier, Sales Assistant, Store Manager" value="<?php echo htmlspecialchars($old['position']); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">PIN</label>
            <input name="pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6" class="form-control" placeholder="4 to 6 digits" value="<?php echo htmlspecialchars($old['pin']); ?>" required>
            <?php if (!empty($errors['pin'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['pin']); ?></small><?php endif; ?>
            <small class="text-muted">They'll use this PIN, together with their name, to log in.</small>
          </div>
          <button class="btn btn-primary">Create staff</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Your staff <span class="badge bg-light text-dark"><?php echo count($staff); ?></span></h2>
        <?php if (!$staff): ?>
          <div class="text-muted">No staff yet. Add your first team member on the left.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Position</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($staff as $s): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($s['username']); ?></td>
                  <td class="text-muted"><?php echo htmlspecialchars($s['position'] ?? '') ?: '—'; ?></td>
                  <td>
                    <?php if ((int)$s['is_active'] === 1): ?>
                      <span class="badge bg-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Disabled</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end" style="white-space:nowrap;">
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/staff/permissions.php?staff=' . (int) $s['id']); ?>">Permissions</a>
                    <div class="dropdown d-inline">
                      <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">Reset PIN</button>
                      <form method="post" class="dropdown-menu p-3" style="min-width:220px;" onclick="event.stopPropagation();">
                        <input type="hidden" name="action" value="reset_pin">
                        <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                        <label class="form-label small mb-1">New PIN for <?php echo htmlspecialchars($s['username']); ?></label>
                        <input name="pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6" class="form-control form-control-sm mb-2" placeholder="4 to 6 digits" required>
                        <button class="btn btn-sm btn-primary w-100">Set new PIN</button>
                      </form>
                    </div>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                      <input type="hidden" name="make_active" value="<?php echo (int)$s['is_active'] === 1 ? '0' : '1'; ?>">
                      <button class="btn btn-sm btn-outline-<?php echo (int)$s['is_active'] === 1 ? 'warning' : 'success'; ?>"><?php echo (int)$s['is_active'] === 1 ? 'Block' : 'Unblock'; ?></button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete <?php echo htmlspecialchars($s['username'], ENT_QUOTES); ?>? This can\'t be undone — their past sales stay on record, but the staff account is gone for good.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
