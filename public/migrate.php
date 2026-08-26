<?php
// public/migrate.php — guarded web migration runner for environments where
// command-line access is inconvenient. Only the primary tenant owner can run it.
require_once __DIR__ . '/../app/app.php';
PageGuard::primaryOwner();

$page_title = 'Run migrations';
$result = null;
$error = null;

function public_migrate_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            $buffer .= $char;
            if ($char === "\n") { $lineComment = false; }
            continue;
        }
        if ($blockComment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= $next;
                $i++;
                $blockComment = false;
            }
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === $quote) { $quote = null; }
            continue;
        }
        if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
            $buffer .= $char . $next;
            $i++;
            $lineComment = true;
            continue;
        }
        if ($char === '#') {
            $buffer .= $char;
            $lineComment = true;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $buffer .= $char . $next;
            $i++;
            $blockComment = true;
            continue;
        }
        if ($char === '\'' || $char === '"' || $char === '`') {
            $buffer .= $char;
            $quote = $char;
            continue;
        }
        if ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '' && trim(preg_replace('/^\s*(--|#).*$/m', '', $trimmed)) !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '' && trim(preg_replace('/^\s*(--|#).*$/m', '', $trimmed)) !== '') {
        $statements[] = $trimmed;
    }
    return $statements;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $path = ROOT_PATH . '/databases/full_migration_db.sql';
    $sql = is_file($path) ? file_get_contents($path) : false;
    if ($sql === false) {
        $error = 'Migration file not found.';
    } elseif (stripos($sql, 'DELIMITER') !== false) {
        $error = 'This migration file contains DELIMITER blocks and cannot be run from the web runner.';
    } else {
        $pdo = Database::pdo();
        $statements = public_migrate_split_sql($sql);
        $ran = 0;
        $skipped = 0;
        $tolerable = [1050, 1060, 1061, 1062, 1091, 1826];

        foreach ($statements as $statement) {
            if (preg_match('/^\s*(?:--[^\n]*\n\s*)*(CREATE\s+DATABASE|USE)\b/i', $statement)) {
                $skipped++;
                continue;
            }
            try {
                $pdo->exec($statement);
                $ran++;
            } catch (PDOException $e) {
                $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
                if (in_array($mysqlCode, $tolerable, true) || ($mysqlCode === 1054 && preg_match('/^\s*ALTER\s+TABLE\b/i', $statement))) {
                    $skipped++;
                    continue;
                }
                $error = 'Migration failed: ' . $e->getMessage();
                break;
            }
        }

        if ($error === null) {
            $result = "Done. {$ran} statement(s) executed. {$skipped} statement(s) skipped.";
        }
    }
}

ob_start();
?>
<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-body p-4">
    <h1 class="h5 fw-bold mb-2">Run migrations</h1>
    <p class="text-muted small mb-3">Runs <code>databases/full_migration_db.sql</code> against the configured database. Duplicate columns/tables are skipped.</p>
    <?php if ($result): ?><div class="alert alert-success"><?php echo htmlspecialchars($result); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" onsubmit="return confirm('Run database migrations now?');">
      <button class="btn btn-primary"><i class="fas fa-database me-1"></i>Run migrations</button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/templates/tenants/layout.php';
