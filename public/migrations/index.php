<?php
// public/migrations/index.php
// SETUP TOOL — one-shot install of the full schema (databases/full_schema.sql)
// against a brand-new, empty database on a fresh host. Not a replay of
// databases/migrations/*.sql (see that file's own header for why) — this is
// a verified, known-good snapshot instead.
//
// Safe by construction: refuses to run at all if the schema already looks
// installed (so it can never be used to accidentally wipe real data), and
// the schema file itself has no DROP TABLE statements. DELETE THIS FILE
// once you're done setting up a new install.

require_once __DIR__ . '/../../app/app.php';

$schemaFile = ROOT_PATH . '/databases/full_schema.sql';
$error = '';
$connectionError = '';
$alreadyInstalled = false;
$roleCount = 0;
$tableCount = 0;
$pdo = null;

try {
    $pdo = Database::pdo();
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    $rolesExists = (bool) $pdo->query("SHOW TABLES LIKE 'roles'")->fetchColumn();
    if ($rolesExists) {
        $roleCount = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
    }
    $alreadyInstalled = $rolesExists && $roleCount > 0;
} catch (\Throwable $e) {
    $connectionError = $e->getMessage();
}

/** Split a mysqldump-style file into individual executable statements. */
if (!function_exists('migrations_split_sql')) {
    function migrations_split_sql(string $sql): array
    {
        $parts = preg_split('/;\s*\n/', $sql);
        $statements = [];
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '') { continue; }
            $withoutComments = trim(preg_replace('/^\s*--.*$/m', '', $trimmed));
            if ($withoutComments === '') { continue; }
            $statements[] = $trimmed;
        }
        return $statements;
    }
}

// Error codes that mean "this already exists / already applied" — safe to
// skip rather than treat as a real failure (makes the runner idempotent).
const MIGRATIONS_TOLERABLE_CODES = [1050, 1060, 1061, 1062, 1826];

$results = null; // ['created'=>int,'skipped'=>int,'errors'=>[[stmt,message]]]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run' && !$connectionError) {
    // Re-check on submit — never trust what the GET page rendered.
    if ($alreadyInstalled) {
        $error = 'Refusing to run — the schema already looks installed. Nothing was changed.';
    } elseif (!is_file($schemaFile)) {
        $error = 'databases/full_schema.sql was not found.';
    } else {
        $statements = migrations_split_sql(file_get_contents($schemaFile));
        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];
        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
                $results['created']++;
            } catch (\PDOException $e) {
                $code = (int) ($e->errorInfo[1] ?? 0);
                if (in_array($code, MIGRATIONS_TOLERABLE_CODES, true)) {
                    $results['skipped']++;
                } else {
                    $results['errors'][] = [
                        'statement' => mb_substr($stmt, 0, 200),
                        'message'   => $e->getMessage(),
                    ];
                    break; // stop at the first real problem — don't cascade
                }
            }
        }
        // Refresh state for the page render below.
        $rolesExists = (bool) $pdo->query("SHOW TABLES LIKE 'roles'")->fetchColumn();
        $roleCount = $rolesExists ? (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() : 0;
        $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
        $alreadyInstalled = $rolesExists && $roleCount > 0;
    }
}

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database setup — setup tool</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: -apple-system,'Segoe UI',Roboto,Arial,sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 0; line-height: 1.5; }
  .wrap { max-width: 620px; margin: 0 auto; padding: 32px 18px; }
  h1 { font-size: 1.3rem; margin: 0 0 4px; }
  .lead { color: #64748b; font-size: .9rem; margin: 0 0 20px; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 18px; }
  .btn { display: inline-block; padding: 11px 20px; background: #16a34a; color: #fff; border: none; border-radius: 8px; font-size: .95rem; font-weight: 600; cursor: pointer; text-decoration: none; }
  .btn:hover { background: #15803d; }
  .alert { border-radius: 8px; padding: 12px 16px; font-size: .88rem; margin-bottom: 14px; }
  .alert.ok { background: #dcfce7; color: #166534; }
  .alert.err { background: #fee2e2; color: #991b1b; }
  .alert.warn { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
  .alert.info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
  .badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: .68rem; font-weight: 700; padding: 2px 7px; border-radius: 999px; margin-left: 6px; vertical-align: middle; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; font-size: .85em; word-break: break-all; }
  ul { margin: 6px 0 0; padding-left: 18px; }
  .stat { display: inline-block; margin-right: 20px; }
  .stat b { display: block; font-size: 1.3rem; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Database setup <span class="badge">SETUP ONLY</span></h1>
  <p class="lead">Installs every table this app needs on a brand-new database, in one step.</p>

  <div class="alert warn">⚠ No protection beyond this page's own safety checks. <strong>Delete this file</strong> once your database is set up.</div>

  <?php if ($connectionError): ?>
    <div class="alert err">
      <strong>Can't connect to the database.</strong><br>
      <?php echo $h($connectionError); ?><br><br>
      Create the (empty) database and check <code>app/config/database.php</code> matches its host/name/username/password, then reload this page.
    </div>

  <?php elseif ($results): ?>
    <?php if ($results['errors']): ?>
      <div class="alert err">
        <strong>Stopped at a problem — nothing after it was run.</strong><br>
        Statement: <code><?php echo $h($results['errors'][0]['statement']); ?>…</code><br>
        Error: <?php echo $h($results['errors'][0]['message']); ?>
      </div>
    <?php else: ?>
      <div class="alert ok">✓ Done — <?php echo $results['created']; ?> statement<?php echo $results['created'] === 1 ? '' : 's'; ?> run<?php echo $results['skipped'] ? ', ' . $results['skipped'] . ' already in place (skipped)' : ''; ?>.</div>
    <?php endif; ?>
    <div class="card">
      <div class="stat"><b><?php echo $tableCount; ?></b><span class="text-muted">tables</span></div>
      <div class="stat"><b><?php echo $roleCount; ?></b><span class="text-muted">roles</span></div>
    </div>
    <?php if ($alreadyInstalled && !$results['errors']): ?>
      <p><a class="btn" href="<?php echo public_url('admin/register.php'); ?>">Next: create your admin account →</a></p>
    <?php endif; ?>

  <?php elseif ($error): ?>
    <div class="alert err"><?php echo $h($error); ?></div>

  <?php elseif ($alreadyInstalled): ?>
    <div class="alert info">
      This database already looks set up — <strong><?php echo $tableCount; ?> tables</strong>, <strong><?php echo $roleCount; ?> roles</strong> found.
      Refusing to run again (this schema file has no <code>DROP TABLE</code> statements, but re-running is still unnecessary and skipped for safety).
    </div>
    <p><a class="btn" href="<?php echo public_url('admin/register.php'); ?>">Go to admin setup →</a></p>

  <?php else: ?>
    <div class="card">
      <p style="margin:0 0 14px;">This database is empty (or missing the app's tables). Installing will create all 52 tables and the 6 baseline roles the app needs.</p>
      <form method="post">
        <input type="hidden" name="action" value="run">
        <button class="btn" type="submit">Install now</button>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
