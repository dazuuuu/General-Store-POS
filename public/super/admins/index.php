<?php
// public/super/admins/index.php — the PRIMARY owner adds/manages other
// admin accounts for this shop. Only the primary owner (tenants.owner_user_id)
// can reach this page (see PageGuard::primaryOwner()) — an admin created here
// cannot create further admins themselves.
require_once __DIR__ . '/../../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/admin_credentials_email.php';
PageGuard::primaryOwner();

$pdo = Database::pdo();
$tenantId = TenantContext::tenantId();
$tenant = (new Models\TenantModel($pdo))->find($tenantId);
$primaryId = (int) ($tenant['owner_user_id'] ?? 0);
$shopName = $tenant['name'] ?? 'the shop';
$base = public_url('super/admins/');

/** A unique `users.username` derived from the email's local part. */
function admins_unique_username(PDO $pdo, string $email): string
{
    $base = preg_replace('/[^a-z0-9_]+/', '', strtolower(explode('@', $email)[0])) ?: 'admin';
    $name = $base;
    $i = 0;
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
    while (true) {
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) {
            return $name;
        }
        $name = $base . (++$i);
    }
}

$errors = [];
$success = '';
$mailResult = null;
$mailError = '';
$old = ['name' => '', 'email' => '', 'password' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $old = [
        'name'     => trim($_POST['name'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'password' => (string) ($_POST['password'] ?? ''),
    ];
    if ($old['name'] === '') {
        $errors[] = "The new admin's name is required.";
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    } else {
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $chk->execute([$old['email']]);
        if ($chk->fetchColumn()) { $errors[] = 'That email is already registered.'; }
    }
    if (strlen($old['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE role_name = 'tenant_owner' LIMIT 1")->fetchColumn();
        $username = admins_unique_username($pdo, $old['email']);
        $pdo->prepare(
            'INSERT INTO users (tenant_id, username, email, password_hash, role_id, is_active, email_verified, must_reset_password)
             VALUES (?,?,?,?,?,1,1,0)'
        )->execute([$tenantId, $username, $old['email'], password_hash($old['password'], PASSWORD_DEFAULT), $roleId]);
        $newId = (int) $pdo->lastInsertId();

        $success = 'Admin account created for ' . htmlspecialchars($old['email']) . '.';
        $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . public_url('admin/');
        $msg = build_admin_credentials_email($old['name'], $old['email'], $old['password'], $shopName, $loginUrl);
        if ((new MailService())->send($old['email'], $msg['subject'], $msg['html'], $msg['text'])) {
            $mailResult = 'ok';
        } else {
            $mailResult = 'error';
            $mailError = MailService::lastError() ?: 'Could not send the credentials email.';
        }
        $old = ['name' => '', 'email' => '', 'password' => ''];
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id !== $primaryId) {
        $makeActive = ($_POST['make_active'] ?? '') === '1';
        $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ? AND tenant_id = ?')->execute([$makeActive ? 1 : 0, $id, $tenantId]);
        $_SESSION['flash']['success'] = $makeActive ? 'Admin unblocked.' : 'Admin blocked — they can no longer log in.';
    }
    header('Location: ' . $base); exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id !== $primaryId) {
        $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM users WHERE id = ? AND tenant_id = ?')->execute([$id, $tenantId]);
        $_SESSION['flash']['success'] = 'Admin account deleted.';
    }
    header('Location: ' . $base); exit;
}

$stmt = $pdo->prepare(
    "SELECT u.id, u.username, u.email, u.is_active
       FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.tenant_id = ? AND r.role_name = 'tenant_owner'
   ORDER BY (u.id = ?) DESC, u.username ASC"
);
$stmt->execute([$tenantId, $primaryId]);
$admins = $stmt->fetchAll();

$page_title = 'Admins';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Add an admin</h2>
        <p class="text-muted small mb-3">They'll get full owner access to <?php echo htmlspecialchars($shopName); ?> and their credentials by email — but they won't be able to add other admins themselves.</p>
        <?php if ($errors): ?><div class="alert alert-danger py-2"><ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success py-2"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($mailResult === 'ok'): ?><div class="alert alert-success py-2">✓ Credentials email sent.</div>
        <?php elseif ($mailResult === 'error'): ?><div class="alert alert-warning py-2">Account created, but the email failed: <?php echo htmlspecialchars($mailError); ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Full name</label>
            <input name="name" class="form-control" value="<?php echo htmlspecialchars($old['name']); ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($old['email']); ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input name="password" type="text" class="form-control" minlength="8" placeholder="Min. 8 characters" autocomplete="off" required>
            <small class="text-muted">Shown as plain text so you can confirm it — it's hashed before storing, and emailed to them.</small>
          </div>
          <button class="btn btn-primary">Create admin</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Admins <span class="badge bg-light text-dark"><?php echo count($admins); ?></span></h2>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($admins as $a): $isPrimary = (int) $a['id'] === $primaryId; ?>
              <tr>
                <td class="fw-semibold"><?php echo htmlspecialchars($a['username']); ?></td>
                <td class="text-muted small"><?php echo htmlspecialchars($a['email']); ?></td>
                <td><?php echo $isPrimary ? '<span class="badge bg-primary">Primary</span>' : '<span class="badge bg-light text-dark">Admin</span>'; ?></td>
                <td>
                  <?php if ((int) $a['is_active'] === 1): ?><span class="badge bg-success">Active</span>
                  <?php else: ?><span class="badge bg-secondary">Blocked</span>
                  <?php endif; ?>
                </td>
                <td class="text-end" style="white-space:nowrap;">
                  <?php if (!$isPrimary): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                      <input type="hidden" name="make_active" value="<?php echo (int) $a['is_active'] === 1 ? '0' : '1'; ?>">
                      <button class="btn btn-sm btn-outline-<?php echo (int) $a['is_active'] === 1 ? 'warning' : 'success'; ?>"><?php echo (int) $a['is_active'] === 1 ? 'Block' : 'Unblock'; ?></button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete <?php echo htmlspecialchars($a['username'], ENT_QUOTES); ?>? This can\'t be undone.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
