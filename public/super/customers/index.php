<?php
// public/super/customers/index.php — loyalty + B2B customer directory
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();
if (!TenantContext::can(Capabilities::CUSTOMERS_MANAGE) && TenantContext::role() !== 'tenant_owner') {
    PageGuard::capability(Capabilities::CUSTOMERS_MANAGE);
}

$pdo = Database::pdo();
$CM = new Models\CustomerModel($pdo);
$error = '';
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $payload = [
            'name'         => $_POST['name'] ?? '',
            'phone'        => $_POST['phone'] ?? '',
            'email'        => $_POST['email'] ?? '',
            'company_name' => $_POST['company_name'] ?? '',
            'is_b2b'       => !empty($_POST['is_b2b']),
            'credit_limit' => $_POST['credit_limit'] ?? 0,
            'loyalty_tier' => $_POST['loyalty_tier'] ?? 'standard',
            'notes'        => $_POST['notes'] ?? '',
            'status'       => $_POST['status'] ?? 'active',
        ];
        $id = (int) ($_POST['id'] ?? 0);
        $res = $id > 0 ? $CM->edit($id, $payload) : $CM->create($payload);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = $id > 0 ? 'Customer updated.' : 'Customer added.';
            header('Location: ' . public_url('super/customers/'));
            exit;
        }
        $error = $res['errors']['name'] ?? ($res['errors']['_'] ?? 'Could not save customer.');
        $editId = $id;
    }
}

$q = trim($_GET['q'] ?? '');
$customers = $CM->search($q, 200);
$editing = $editId > 0 ? $CM->find($editId) : null;
$page_title = 'Customers / Loyalty';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3"><?php echo $editing ? 'Edit customer' : 'Add customer'; ?></h2>
        <form method="post">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?php echo (int) ($editing['id'] ?? 0); ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Name</label>
            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($editing['phone'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Email</label>
              <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editing['email'] ?? ''); ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Company <span class="text-muted fw-normal">(B2B)</span></label>
            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($editing['company_name'] ?? ''); ?>">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Credit limit (KES)</label>
              <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="<?php echo htmlspecialchars((string) ($editing['credit_limit'] ?? '0')); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Loyalty tier</label>
              <select name="loyalty_tier" class="form-select">
                <?php foreach (['standard','silver','gold','platinum'] as $tier): ?>
                  <option value="<?php echo $tier; ?>" <?php echo (($editing['loyalty_tier'] ?? '') === $tier) ? 'selected' : ''; ?>><?php echo ucfirst($tier); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" name="is_b2b" value="1" id="isB2b" <?php echo !empty($editing['is_b2b']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="isB2b">B2B / wholesale customer</label>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($editing['notes'] ?? ''); ?></textarea>
          </div>
          <?php if ($editing): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?php echo ($editing['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
              <option value="inactive" <?php echo ($editing['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
          </div>
          <?php endif; ?>
          <button class="btn btn-primary"><?php echo $editing ? 'Save changes' : 'Add customer'; ?></button>
          <?php if ($editing): ?>
            <a href="<?php echo public_url('super/customers/'); ?>" class="btn btn-link">Cancel</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <h2 class="h5 mb-0">Directory</h2>
          <form method="get" class="d-flex gap-2">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search…" value="<?php echo htmlspecialchars($q); ?>">
            <button class="btn btn-sm btn-outline-primary">Search</button>
          </form>
        </div>
        <?php if (!$customers): ?>
          <p class="text-muted mb-0">No customers yet. Add one to enable loyalty points and B2B invoicing.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Contact</th><th>Tier</th><th>Points</th><th>Credit</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($customers as $c): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo htmlspecialchars($c['name']); ?></div>
                  <?php if (!empty($c['company_name'])): ?><div class="small text-muted"><?php echo htmlspecialchars($c['company_name']); ?><?php echo !empty($c['is_b2b']) ? ' · B2B' : ''; ?></div><?php endif; ?>
                </td>
                <td class="small"><?php echo htmlspecialchars($c['phone'] ?: ($c['email'] ?: '—')); ?></td>
                <td><span class="badge bg-light text-dark text-uppercase"><?php echo htmlspecialchars($c['loyalty_tier']); ?></span></td>
                <td><?php echo number_format((float) $c['loyalty_points'], 1); ?></td>
                <td class="small">KES <?php echo number_format((float) $c['credit_limit'], 0); ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="?edit=<?php echo (int) $c['id']; ?>">Edit</a></td>
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
