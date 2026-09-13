<?php
// public/admin/register.php
// SETUP TOOL — manually create the shop's owner/admin account (and the shop
// itself, if it doesn't exist yet) without going through the public
// subscription sign-up flow. Emails the new owner their credentials.
//
// No auth guard on purpose — DELETE THIS FILE once you're done setting up
// admins, and definitely before hosting the project for real. Anyone who
// finds this URL while it still exists can create themselves an admin
// account.

require_once __DIR__ . '/../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/admin_credentials_email.php';

$pdo = Database::pdo();
$tenants = new Models\TenantModel($pdo);
$shop = $tenants->primary();

$errors = [];
$success = null;
$mailResult = null; // 'ok' | 'error' | 'skipped'
$mailError = '';
$old = ['name' => '', 'email' => '', 'phone' => '', 'password' => '', 'shop_name' => ''];

/** A unique `users.username` derived from the email's local part. */
function admin_unique_username(PDO $pdo, string $email): string
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name'      => trim($_POST['name'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
        'password'  => (string) ($_POST['password'] ?? ''),
        'shop_name' => trim($_POST['shop_name'] ?? ''),
    ];

    if ($old['name'] === '') {
        $errors[] = "Owner's name is required.";
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    } else {
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $chk->execute([$old['email']]);
        if ($chk->fetchColumn()) {
            $errors[] = 'That email is already registered.';
        }
    }
    if (strlen($old['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!$shop && $old['shop_name'] === '') {
        $errors[] = 'Shop name is required (no shop exists yet).';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $roleId = $pdo->query("SELECT id FROM roles WHERE role_name = 'tenant_owner' LIMIT 1")->fetchColumn();
            if (!$roleId) {
                throw new \RuntimeException('tenant_owner role is missing — run the role/permission migrations first.');
            }

            $tenantId = $shop ? (int) $shop['id'] : null;
            $shopName = $shop ? $shop['name'] : $old['shop_name'];
            if (!$tenantId) {
                $slug = $tenants->uniqueSlug($shopName);
                $tenantId = $tenants->create($shopName, $slug);
            }

            $username = admin_unique_username($pdo, $old['email']);
            $stmt = $pdo->prepare(
                'INSERT INTO users (tenant_id, username, email, password_hash, role_id, is_active, email_verified, must_reset_password)
                 VALUES (?,?,?,?,?,1,1,0)'
            );
            $stmt->execute([
                $tenantId, $username, $old['email'],
                password_hash($old['password'], PASSWORD_DEFAULT),
                (int) $roleId,
            ]);
            $ownerId = (int) $pdo->lastInsertId();

            if ($old['phone'] !== '') {
                $pdo->prepare(
                    'INSERT INTO user_profiles (user_id, phone) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE phone = VALUES(phone)'
                )->execute([$ownerId, $old['phone']]);
            }

            if (!$shop) {
                $tenants->setOwner($tenantId, $ownerId);
            }

            $pdo->commit();
            $success = 'Admin account created for ' . htmlspecialchars($old['email']) . ' on ' . htmlspecialchars($shopName) . '.';

            $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . public_url('admin/');
            $msg = build_admin_credentials_email($old['name'], $old['email'], $old['password'], $shopName, $loginUrl);
            if ((new MailService())->send($old['email'], $msg['subject'], $msg['html'], $msg['text'])) {
                $mailResult = 'ok';
            } else {
                $mailResult = 'error';
                $mailError = MailService::lastError() ?: 'Could not send the credentials email.';
            }

            $shop = $tenants->find($tenantId); // refresh for the "shop already exists" banner
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = 'Could not create the account: ' . $e->getMessage();
        }
    }
}

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register admin — setup tool</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: -apple-system,'Segoe UI',Roboto,Arial,sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 0; line-height: 1.5; }
  .wrap { max-width: 560px; margin: 0 auto; padding: 32px 18px; }
  h1 { font-size: 1.3rem; margin: 0 0 4px; }
  .lead { color: #64748b; font-size: .9rem; margin: 0 0 20px; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 18px; }
  .card h2 { font-size: .76rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin: 0 0 16px; }
  label { display: block; font-size: .82rem; font-weight: 600; color: #475569; margin-bottom: 4px; }
  input { width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .92rem; background: #fff; outline: none; margin-bottom: 14px; }
  input:focus { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.15); }
  .two { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; }
  .btn { width: 100%; padding: 11px; background: #16a34a; color: #fff; border: none; border-radius: 8px; font-size: .95rem; font-weight: 600; cursor: pointer; }
  .btn:hover { background: #15803d; }
  .alert { border-radius: 8px; padding: 12px 16px; font-size: .88rem; margin-bottom: 14px; }
  .alert.ok { background: #dcfce7; color: #166534; }
  .alert.err { background: #fee2e2; color: #991b1b; }
  .alert.warn { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
  .badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: .68rem; font-weight: 700; padding: 2px 7px; border-radius: 999px; margin-left: 6px; vertical-align: middle; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; font-size: .85em; }
  ul { margin: 6px 0 0; padding-left: 18px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Register admin <span class="badge">SETUP ONLY</span></h1>
  <p class="lead">Creates the shop's owner/admin account and emails them their login credentials.</p>

  <div class="alert warn">⚠ This page has <strong>no protection at all</strong> — anyone with the URL can create an admin account. <strong>Delete this file</strong> once you're done setting up admins, and definitely before hosting the project for real.</div>

  <?php if ($errors): ?>
    <div class="alert err"><strong>Please fix the following:</strong><ul><?php foreach ($errors as $e): ?><li><?php echo $h($e); ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if ($success): ?><div class="alert ok">✓ <?php echo $success; ?></div><?php endif; ?>
  <?php if ($mailResult === 'ok'): ?>
    <div class="alert ok">✓ Credentials email sent to <strong><?php echo $h($old['email']); ?></strong>.</div>
  <?php elseif ($mailResult === 'error'): ?>
    <div class="alert err">
      Account created, but the credentials email failed to send: <?php echo $h($mailError); ?><br><br>
      Share these manually — Email: <code><?php echo $h($old['email']); ?></code> · Password: <code><?php echo $h($old['password']); ?></code>
    </div>
  <?php endif; ?>

  <form method="post">
    <div class="card">
      <h2>Shop</h2>
      <?php if ($shop): ?>
        <p style="margin:0;font-size:.92rem;">Adding an admin to <strong><?php echo $h($shop['name']); ?></strong>.</p>
      <?php else: ?>
        <label for="shop_name">Shop name</label>
        <input id="shop_name" name="shop_name" type="text" required value="<?php echo $h($old['shop_name']); ?>" placeholder="e.g. Downtown General Shop">
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Admin account</h2>
      <label for="name">Full name</label>
      <input id="name" name="name" type="text" required value="<?php echo $h($old['name']); ?>" placeholder="Jane Doe">
      <div class="two">
        <div>
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required value="<?php echo $h($old['email']); ?>" placeholder="jane@example.com">
        </div>
        <div>
          <label for="phone">Phone <small style="font-weight:400">(optional)</small></label>
          <input id="phone" name="phone" type="tel" value="<?php echo $h($old['phone']); ?>" placeholder="+254700000000">
        </div>
      </div>
      <label for="password">Password</label>
      <input id="password" name="password" type="text" required minlength="8" value="<?php echo $h($old['password']); ?>" placeholder="Min. 8 characters" autocomplete="off">
      <p style="margin:-10px 0 14px;font-size:.78rem;color:#64748b;">Shown as plain text so you can confirm it before it's emailed. It's hashed before storing.</p>
    </div>

    <button class="btn" type="submit">Create admin &amp; send credentials</button>
  </form>
</div>
</body>
</html>
