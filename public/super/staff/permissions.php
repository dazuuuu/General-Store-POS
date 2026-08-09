<?php
// public/super/staff/permissions.php — owner grants/revokes per-staff authorities
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant(); // owner only

$pdo = Database::pdo();
$svc = new StaffService($pdo);
$tenantId = TenantContext::tenantId();

$roleId       = $svc->staffRoleId();
$roleDefaults = $svc->roleDefaultCaps('staff');

// Exactly three switches for this business: take orders, process payments,
// manage inventory. Each maps to one or more of the real capabilities
// underneath (never owner-only powers) — the fine-grained caps still exist,
// this just simplifies what the owner sees and toggles.
$toggles = [
    ['key' => 'take_orders',      'label' => 'Take orders',       'desc' => 'Open tabs, add drinks, and record sales.',                    'caps' => [Capabilities::SALES_RECORD]],
    ['key' => 'process_payments', 'label' => 'Process payments',  'desc' => 'View open/unpaid tabs shop-wide and mark them as paid.',      'caps' => [Capabilities::PAYMENTS_PROCESS]],
    // Note: inventory.view is deliberately NOT part of this toggle — every
    // staff member can already see the Inventory page by role default; these
    // two switches only gate the ability to change stock. Split into two so
    // a staff member can be trusted to log deliveries without also being
    // able to edit/delete the catalogue, put items on offer, or archive them.
    ['key' => 'record_stock',     'label' => 'Record stock',      'desc' => 'Record stock deliveries — new titles and restocks.',           'caps' => [Capabilities::STOCK_ENTER]],
    ['key' => 'edit_inventory',   'label' => 'Edit inventory',    'desc' => 'Edit books, put items on offer or archive them, and manage suppliers/subjects/grades/publishers.', 'caps' => [Capabilities::INVENTORY_EDIT]],
];
$manageable = [];
foreach ($toggles as $t) { foreach ($t['caps'] as $c) { $manageable[] = $c; } }

$flash = '';
$staffId = (int) ($_GET['staff'] ?? $_POST['staff_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $staffId) {
    $staff = $svc->findStaff($tenantId, $staffId);
    if ($staff) {
        $posted = array_keys($_POST['caps'] ?? []);
        $desired = [];
        foreach ($toggles as $t) {
            if (in_array($t['key'], $posted, true)) {
                $desired = array_merge($desired, $t['caps']);
            }
        }
        $desired = array_values(array_unique($desired));
        $svc->setCapabilities($tenantId, $staffId, $desired, $manageable, $roleDefaults);
        $_SESSION['flash']['success'] = 'Permissions updated for ' . ($staff['username'] ?? 'staff') . '.';
        header('Location: ' . public_url('super/staff/permissions.php?staff=' . $staffId));
        exit;
    }
    $flash = 'That staff member was not found.';
}

$staff = $staffId ? $svc->findStaff($tenantId, $staffId) : null;
$effective = $staff ? $svc->effectiveCaps((int) $staff['id'], (int) $roleId) : [];
$allStaff = $svc->listForTenant($tenantId);

$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$page_title = 'Staff permissions';
ob_start();
?>
<?php if (!empty($_SESSION['flash']['success'])): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>
<?php if ($flash): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>

<?php if (!$staff): ?>
  <!-- ===== staff picker ===== -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0 fw-bold">Staff permissions</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/staff/'); ?>"><i class="fas fa-arrow-left me-1"></i>Back to staff</a>
  </div>
  <p class="text-muted">Choose a staff member to set what they're allowed to do. Sensitive actions like <strong>entering stock</strong> and <strong>editing products</strong> are off by default — grant them only to people you trust.</p>
  <?php if (!$allStaff): ?>
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-5 text-center text-muted">
      <i class="fas fa-user-group fa-2x mb-2 d-block" style="opacity:.3;"></i>
      No staff yet. <a href="<?php echo public_url('super/staff/'); ?>">Add a staff member</a> first.
    </div></div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($allStaff as $s):
      $eff = $svc->effectiveCaps((int) $s['id'], (int) $roleId);
      $extra = count(array_diff(array_intersect($eff, $manageable), $roleDefaults)); ?>
    <div class="col-12 col-md-6 col-lg-4">
      <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:14px;" href="?staff=<?php echo (int) $s['id']; ?>">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <span class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:11px;background:#eef2ff;color:#4f46e5;font-weight:700;">
            <?php echo strtoupper(substr($s['username'] ?? '?', 0, 1)); ?>
          </span>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($s['username']); ?></div>
            <div class="small text-muted text-truncate"><?php echo htmlspecialchars($s['position'] ?? '') ?: '&mdash;'; ?></div>
          </div>
          <span class="badge <?php echo $extra ? 'bg-success' : 'bg-light text-dark'; ?>"><?php echo $extra ? ('+' . $extra . ' granted') : 'defaults'; ?></span>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php else: ?>
  <!-- ===== permission editor ===== -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h5 mb-0 fw-bold"><?php echo htmlspecialchars($staff['username']); ?></h1>
      <div class="small text-muted"><?php echo htmlspecialchars($staff['position'] ?? 'Staff'); ?></div>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/staff/permissions.php'); ?>"><i class="fas fa-arrow-left me-1"></i>All staff</a>
  </div>

  <div class="alert alert-info py-2 small"><i class="fas fa-circle-info me-1"></i> Changes take effect the next time this staff member logs in.</div>

  <form method="post">
    <input type="hidden" name="staff_id" value="<?php echo (int) $staff['id']; ?>">
    <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
      <div class="card-body p-4">
        <?php foreach ($toggles as $t):
          $isDefault = !array_diff($t['caps'], $roleDefaults);   // every cap in this switch is a role default
          $isOn = !array_diff($t['caps'], $effective);           // every cap in this switch is currently effective
          $id = 'cap_' . $t['key']; ?>
        <div class="form-check form-switch perm-row d-flex align-items-start gap-2 py-2 <?php echo $isOn ? '' : 'is-off'; ?>" style="padding-left:3.2em;">
          <input class="form-check-input mt-1" type="checkbox" role="switch" id="<?php echo $id; ?>" name="caps[<?php echo $t['key']; ?>]" value="1" <?php echo $isOn ? 'checked' : ''; ?>>
          <label class="form-check-label flex-grow-1" for="<?php echo $id; ?>">
            <span class="fw-semibold"><?php echo htmlspecialchars($t['label']); ?></span>
            <?php if (!$isDefault): ?><span class="badge bg-light text-dark ms-1" style="font-weight:500;">off by default</span><?php endif; ?>
            <span class="d-block small text-muted"><?php echo htmlspecialchars($t['desc']); ?></span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk me-1"></i>Save permissions</button>
      <a class="btn btn-outline-secondary" href="<?php echo public_url('super/staff/permissions.php'); ?>">Cancel</a>
    </div>
  </form>

  <style>
    .perm-row.is-off .form-check-label .fw-semibold { color:#94a3b8; }
    .perm-row.is-off { opacity:.85; }
  </style>
  <script>
    document.querySelectorAll('.perm-row .form-check-input').forEach(function (cb) {
      cb.addEventListener('change', function () { cb.closest('.perm-row').classList.toggle('is-off', !cb.checked); });
    });
  </script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';