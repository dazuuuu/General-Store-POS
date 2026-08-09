<?php
// public/super/staff/attendance.php — owner view of staff clock in/out:
// who's in right now, recent history (flagging forgotten clock-outs), and
// authorizing a second clock-in for someone who's already used today's.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant(); // owner only

$pdo = Database::pdo();
$svc = new StaffService($pdo);
$TL  = new Models\TimeLogModel($pdo);
$tenantId = TenantContext::tenantId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'authorize') {
    $staffId = (int) ($_POST['staff_id'] ?? 0);
    $staff = $svc->findStaff($tenantId, $staffId);
    if ($staff) {
        $TL->authorizeReclock($tenantId, $staffId, (int) TenantContext::userId());
        $_SESSION['flash']['success'] = 'Authorized another clock-in today for ' . ($staff['username'] ?? 'staff') . '.';
    }
    header('Location: ' . public_url('super/staff/attendance.php'));
    exit;
}

$roster = $svc->listForTenant($tenantId);
$currentlyIn = $TL->currentlyIn($tenantId);
$currentlyInIds = array_column($currentlyIn, 'user_id');
$recent = $TL->recentForTenant($tenantId, 100);

$today = date('Y-m-d');
$clockedInToday = [];
foreach ($recent as $r) {
    if (date('Y-m-d', strtotime($r['clock_in_at'])) === $today) {
        $clockedInToday[(int) $r['user_id']] = true;
    }
}

$page_title = 'Attendance';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h1 class="h5 mb-0 fw-bold">Attendance</h1>
    <p class="text-muted small mb-0">Who's clocked in, recent history, and authorizing an extra clock-in for today.</p>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/staff/'); ?>"><i class="fas fa-arrow-left me-1"></i>Back to staff</a>
</div>

<?php if (!empty($_SESSION['flash']['success'])): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <h2 class="h6 mb-3"><i class="fas fa-circle text-success me-2" style="font-size:.6rem;"></i>In right now <span class="badge bg-light text-dark"><?php echo count($currentlyIn); ?></span></h2>
    <?php if (!$currentlyIn): ?>
      <div class="text-muted small">No one is currently clocked in.</div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($currentlyIn as $c):
          $since = strtotime($c['clock_in_at']);
          $hrs = (time() - $since) / 3600;
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="d-flex align-items-center gap-3 p-3 border rounded" style="border-color:#e2e8f0!important;">
            <span class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;border-radius:10px;background:#dcfce7;color:#16a34a;font-weight:700;">
              <?php echo strtoupper(substr($c['username'] ?? '?', 0, 1)); ?>
            </span>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($c['username']); ?></div>
              <div class="small text-muted">Since <?php echo date('g:i a', $since); ?> &middot; <?php echo $hrs < 1 ? round($hrs * 60) . 'm' : round($hrs, 1) . 'h'; ?> ago</div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <h2 class="h6 mb-3">Staff</h2>
    <?php if (!$roster): ?>
      <div class="text-muted small">No staff yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Today</th><th></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($roster as $s):
              $sid = (int) $s['id'];
              $isIn = in_array($sid, $currentlyInIds, true);
              $loggedToday = !empty($clockedInToday[$sid]);
              $authorized = $TL->hasUnusedAuthorization($tenantId, $sid);
            ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($s['username']); ?><?php echo $s['position'] ? '<div class="small text-muted fw-normal">' . htmlspecialchars($s['position']) . '</div>' : ''; ?></td>
              <td>
                <?php if ($isIn): ?><span class="badge bg-success">Clocked in</span>
                <?php elseif ($loggedToday): ?><span class="badge bg-light text-dark">Clocked out</span>
                <?php else: ?><span class="badge bg-secondary">Not in today</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($authorized): ?><span class="badge bg-warning text-dark">Re-clock authorized — unused</span><?php endif; ?>
              </td>
              <td class="text-end">
                <?php if ($loggedToday && !$authorized): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="authorize"><input type="hidden" name="staff_id" value="<?php echo $sid; ?>">
                  <button class="btn btn-sm btn-outline-primary">Authorize another clock-in today</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-body p-4">
    <h2 class="h6 mb-3">Recent history</h2>
    <?php if (!$recent): ?>
      <div class="text-muted small">No clock in/out records yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Clock in</th><th>Clock out</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($r['username']); ?></td>
              <td class="text-muted small"><?php echo date('j M Y, g:i a', strtotime($r['clock_in_at'])); ?></td>
              <td class="text-muted small">
                <?php echo $r['clock_out_at'] ? date('j M Y, g:i a', strtotime($r['clock_out_at'])) : '—'; ?>
              </td>
              <td>
                <?php if (!empty($r['auto_closed'])): ?>
                  <span class="badge bg-warning text-dark" title="Clocked in without clocking out — auto-closed at day's end">Forgot to clock out</span>
                <?php elseif ($r['clock_out_at'] === null): ?>
                  <span class="badge bg-success">Still in</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
