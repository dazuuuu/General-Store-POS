<?php
// public/devs/whoami.php
// DEV-ONLY. Shows what role the session carries vs what the DB join returns, so
// we can see exactly why a tenant_owner gets ?denied=1. Key-guarded. Delete before prod.
//   http://localhost/Modern/public/devs/whoami.php?key=modern-dev&user=2

require_once __DIR__ . '/../../app/app.php';

const DEV_KEY = 'modern-dev';
if (!hash_equals(DEV_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — append ?key=modern-dev');
}

$pdo = Database::pdo();
$uid = (int)($_GET['user'] ?? ($_SESSION['user_id'] ?? 0));

$userCols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
$roles    = $pdo->query('SELECT id, role_name, LENGTH(role_name) AS len FROM roles ORDER BY id')->fetchAll();

$row = null;
if ($uid) {
    $st = $pdo->prepare(
        'SELECT u.id, u.role_id, u.tenant_id, u.is_active, u.email_verified, r.role_name AS joined_role
           FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?'
    );
    $st->execute([$uid]);
    $row = $st->fetch();
}

$joined        = $row['joined_role'] ?? null;
$wouldPass     = $joined === 'tenant_owner';
$sessionRole   = $_SESSION['role'] ?? null;
$ctxRole       = TenantContext::role();
$legacyRoleCol = array_values(array_filter($userCols, fn($c) => in_array(strtolower($c), ['role', 'role_name', 'user_role', 'usertype', 'type'], true)));

$ev = fn($v) => htmlspecialchars(var_export($v, true), ENT_QUOTES);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>whoami — Modern POS</title>
<style>
 body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0}
 .wrap{max-width:760px;margin:0 auto;padding:26px 18px}
 h1{font-size:1.3rem;margin:0 0 16px}
 .card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;margin-bottom:16px}
 .card h2{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin:0 0 12px}
 .r{display:flex;justify-content:space-between;gap:14px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:.9rem}
 .r span:first-child{color:#64748b} code{font-family:ui-monospace,Menlo,monospace}
 .ok{color:#166534;font-weight:700}.bad{color:#991b1b;font-weight:700}
 .verdict{font-size:1rem;padding:14px 16px;border-radius:10px}
 .verdict.ok{background:#dcfce7;color:#166534}.verdict.bad{background:#fee2e2;color:#991b1b}
 .pill{display:inline-block;background:#f1f5f9;border-radius:6px;padding:2px 8px;font-size:.82rem}
</style></head><body><div class="wrap">
<h1>whoami diagnostic (user #<?php echo (int)$uid; ?>)</h1>

<div class="verdict <?php echo $wouldPass ? 'ok' : 'bad'; ?>">
  PageGuard::tenant() would <strong><?php echo $wouldPass ? 'PASS' : 'DENY'; ?></strong>
  — DB join returns role <code>[<?php echo $ev($joined); ?>]</code>, and it must be exactly <code>'tenant_owner'</code>.
</div>

<div class="card">
  <h2>Live session (this browser)</h2>
  <div class="r"><span>$_SESSION['role']</span><code><?php echo $ev($sessionRole); ?></code></div>
  <div class="r"><span>TenantContext::role()</span><code><?php echo $ev($ctxRole); ?></code></div>
  <div class="r"><span>logged_in / otp_verified</span><code><?php echo $ev($_SESSION['logged_in'] ?? null); ?> / <?php echo $ev($_SESSION['otp_verified'] ?? null); ?></code></div>
  <div class="r"><span>user_id / tenant_id</span><code><?php echo $ev($_SESSION['user_id'] ?? null); ?> / <?php echo $ev($_SESSION['tenant_id'] ?? null); ?></code></div>
  <p class="pill">If this is empty, login.php already cleared the session — read the DB section below instead.</p>
</div>

<div class="card">
  <h2>DB row for this user (same query otp-verify uses)</h2>
  <div class="r"><span>role_id</span><code><?php echo $ev($row['role_id'] ?? null); ?></code></div>
  <div class="r"><span>joined role_name (bracketed)</span><code>[<?php echo $ev($joined); ?>]</code></div>
  <div class="r"><span>strict === 'tenant_owner'</span><code class="<?php echo $wouldPass?'ok':'bad'; ?>"><?php echo $ev($wouldPass); ?></code></div>
  <div class="r"><span>tenant_id / is_active / email_verified</span><code><?php echo $ev($row['tenant_id'] ?? null); ?> / <?php echo $ev($row['is_active'] ?? null); ?> / <?php echo $ev($row['email_verified'] ?? null); ?></code></div>
</div>

<?php
// Replicate PageGuard::tenant() exactly, without redirecting, to see the verdict.
$full   = !empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified']) && TenantContext::check();
$roleOk = TenantContext::role() === 'tenant_owner';
$tid    = TenantContext::tenantId();
$sub    = null; $subVerdict = ['ok' => true, 'reason' => null];
if ($tid !== null) {
    try {
        $sub = (new Models\SubscriptionModel($pdo))->forTenant((int)$tid);
        $subVerdict = AccountGuard::evaluate(['is_active' => 1, 'email_verified' => 1, 'tenant_id' => $tid], $sub);
    } catch (\Throwable $e) { $subVerdict = ['ok' => false, 'reason' => 'error: ' . $e->getMessage()]; }
}
if (!$full)            { $final = 'redirect to login.php (no param) — not fully authenticated'; $fc='bad'; }
elseif (!$roleOk)      { $final = 'DENY → login.php?denied=1 — role is not tenant_owner'; $fc='bad'; }
elseif (!$subVerdict['ok']) { $final = 'LOCK → login.php?locked=1 — subscription gate failed'; $fc='bad'; }
else                   { $final = 'PASS → dashboard renders'; $fc='ok'; }
?>
<div class="card">
  <h2>Subscription gate (tenant #<?php echo $ev($tid); ?>)</h2>
  <div class="r"><span>subscription found</span><code><?php echo $ev($sub !== null && $sub !== false); ?></code></div>
  <div class="r"><span>status</span><code><?php echo $ev($sub['status'] ?? null); ?></code></div>
  <div class="r"><span>current_period_end</span><code><?php echo $ev($sub['current_period_end'] ?? null); ?></code></div>
  <div class="r"><span>AccountGuard verdict</span><code class="<?php echo ($subVerdict['ok']??false)?'ok':'bad'; ?>"><?php echo $ev($subVerdict['ok'] ?? null); ?> (<?php echo $ev($subVerdict['reason'] ?? null); ?>)</code></div>
</div>

<div class="card">
  <h2>PageGuard::tenant() — step by step (my code)</h2>
  <div class="r"><span>1. fully authenticated</span><code class="<?php echo $full?'ok':'bad'; ?>"><?php echo $ev($full); ?></code></div>
  <div class="r"><span>2. role === 'tenant_owner'</span><code class="<?php echo $roleOk?'ok':'bad'; ?>"><?php echo $ev($roleOk); ?></code></div>
  <div class="r"><span>3. subscription ok</span><code class="<?php echo ($subVerdict['ok']??false)?'ok':'bad'; ?>"><?php echo $ev($subVerdict['ok'] ?? null); ?></code></div>
  <div class="verdict <?php echo $fc; ?>" style="margin-top:10px"><?php echo htmlspecialchars($final); ?></div>
  <p class="pill">If this says PASS but your browser still shows <code>?denied=1</code>, the PageGuard.php / dashboard running on your server is an OLDER version than the one delivered — redeploy them.</p>
</div>

<div class="card">
  <h2>roles table</h2>
  <?php foreach ($roles as $r): ?>
    <div class="r"><span>id <?php echo (int)$r['id']; ?></span><code>[<?php echo htmlspecialchars($r['role_name']); ?>]  (len <?php echo (int)$r['len']; ?>)</code></div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>users columns</h2>
  <div class="r"><span>all columns</span><code><?php echo htmlspecialchars(implode(', ', $userCols)); ?></code></div>
  <div class="r"><span>legacy role-ish column?</span>
    <code class="<?php echo $legacyRoleCol ? 'bad' : 'ok'; ?>">
      <?php echo $legacyRoleCol ? htmlspecialchars(implode(', ', $legacyRoleCol)) . ' — old code may read this instead of the joined role!' : 'none'; ?>
    </code>
  </div>
</div>

</div></body></html>