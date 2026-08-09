<?php
// public/devs/mail-test.php
// DEV-ONLY mail diagnostic. Sends a real test email with full SMTP logging so
// you can see exactly why mail isn't going out. Key-guarded. DELETE before production.
//
//   http://localhost/Modern/public/devs/mail-test.php?key=modern-dev

require_once __DIR__ . '/../../app/app.php';

const DEV_KEY = 'modern-dev';
if (!hash_equals(DEV_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — append ?key=modern-dev to the URL.');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$cfg = is_file(ROOT_PATH . '/app/config/mail.php') ? require ROOT_PATH . '/app/config/mail.php' : [];

$phpmailerAvailable = class_exists(PHPMailer::class);
$vendorAutoload     = is_file(ROOT_PATH . '/vendor/autoload.php');
$opensslLoaded      = extension_loaded('openssl');
$mailConfigExists   = is_file(ROOT_PATH . '/app/config/mail.php');

$form = [
    'to'         => '',
    'host'       => (string)($cfg['host'] ?? ''),
    'port'       => (int)($cfg['port'] ?? 587),
    'encryption' => (string)($cfg['encryption'] ?? 'tls'),
    'username'   => (string)($cfg['username'] ?? ''),
    'from_email' => (string)($cfg['from_email'] ?? ''),
    'from_name'  => (string)($cfg['from_name'] ?? 'Modern POS'),
    'auth'       => !empty($cfg['username']),
    'skip_verify'=> false,
];

$result = null;        // 'ok' | 'error'
$errorInfo = '';
$transcript = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['to']          = trim($_POST['to'] ?? '');
    $form['host']        = trim($_POST['host'] ?? $form['host']);
    $form['port']        = (int)($_POST['port'] ?? $form['port']);
    $form['encryption']  = $_POST['encryption'] ?? $form['encryption'];
    $form['username']    = trim($_POST['username'] ?? $form['username']);
    $form['from_email']  = trim($_POST['from_email'] ?? $form['from_email']);
    $form['from_name']   = trim($_POST['from_name'] ?? $form['from_name']);
    $form['auth']        = !empty($_POST['auth']);
    $form['skip_verify'] = !empty($_POST['skip_verify']);
    // Reuse saved password unless a new one is typed.
    $password = ($_POST['password'] ?? '') !== '' ? $_POST['password'] : (string)($cfg['password'] ?? '');

    if (!$phpmailerAvailable) {
        $result = 'error';
        $errorInfo = 'PHPMailer is not loaded, so no email can be sent. vendor/autoload.php '
                   . ($vendorAutoload ? 'exists but PHPMailer is not in it' : 'is MISSING')
                   . '. Fix autoloading first (see notes below).';
    } elseif ($form['to'] === '' || !filter_var($form['to'], FILTER_VALIDATE_EMAIL)) {
        $result = 'error';
        $errorInfo = 'Enter a valid recipient email address to send the test to.';
    } else {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function ($str, $level) use (&$transcript) { $transcript .= rtrim($str) . "\n"; };
        try {
            $mail->isSMTP();
            $mail->Host = $form['host'];
            $mail->Port = $form['port'];
            if ($form['auth']) {
                $mail->SMTPAuth = true;
                $mail->Username = $form['username'];
                $mail->Password = $password;
            }
            if ($form['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($form['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            if ($form['skip_verify']) {
                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            }
            $mail->Timeout = 15;
            $mail->setFrom($form['from_email'] ?: 'no-reply@localhost', $form['from_name'] ?: 'Modern POS');
            $mail->addAddress($form['to']);
            $mail->isHTML(true);
            $mail->Subject = 'Modern POS — SMTP test ' . date('Y-m-d H:i:s');
            $mail->Body    = '<p>This is a test email from the Modern POS mail diagnostic page.</p>'
                           . '<p>If you can read this in your inbox, your SMTP settings work and OTP emails will send.</p>';
            $mail->AltBody = 'SMTP test OK — your settings work.';
            $mail->send();
            $result = 'ok';
        } catch (\Throwable $e) {
            $result = 'error';
            $errorInfo = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        }
    }
}

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$badge = fn(bool $ok) => $ok
    ? '<span class="pill ok">yes</span>'
    : '<span class="pill bad">no</span>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mail diagnostic — Modern POS</title>
<style>
  *{box-sizing:border-box} body{margin:0;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#f1f5f9;color:#0f172a;line-height:1.5}
  .wrap{max-width:780px;margin:0 auto;padding:28px 18px}
  h1{font-size:1.4rem;margin:0 0 4px} .lead{color:#64748b;margin:0 0 22px;font-size:.95rem}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 22px;margin-bottom:18px}
  .card h2{font-size:1rem;margin:0 0 14px;text-transform:uppercase;letter-spacing:.03em;color:#475569}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 22px}
  .row{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:.9rem}
  .row span:first-child{color:#64748b}
  .pill{font-size:.72rem;padding:2px 9px;border-radius:999px;font-weight:600}
  .pill.ok{background:#dcfce7;color:#166534}.pill.bad{background:#fee2e2;color:#991b1b}
  label{display:block;font-size:.82rem;color:#475569;margin:0 0 4px;font-weight:600}
  input,select{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;font-size:.92rem;background:#fff}
  .f{margin-bottom:14px}
  .two{display:grid;grid-template-columns:2fr 1fr;gap:14px}
  .check{display:flex;align-items:center;gap:8px;font-size:.88rem;color:#334155;margin-bottom:10px}
  .check input{width:auto}
  .btn{background:#2563eb;color:#fff;border:0;border-radius:8px;padding:11px 20px;font-size:.95rem;font-weight:600;cursor:pointer}
  .alert{border-radius:8px;padding:12px 14px;font-size:.9rem;margin-bottom:14px}
  .alert.ok{background:#dcfce7;color:#166534}.alert.err{background:#fee2e2;color:#991b1b}
  pre{background:#0f172a;color:#e2e8f0;border-radius:8px;padding:14px;overflow:auto;font-size:.78rem;line-height:1.45;max-height:360px}
  .note{font-size:.84rem;color:#64748b}.note code{background:#f1f5f9;padding:1px 5px;border-radius:4px}
  .warn{background:#fef9c3;color:#854d0e;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:.85rem;margin-bottom:18px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Mail diagnostic</h1>
  <p class="lead">Sends a real test email with full SMTP logging, so you can see exactly what's failing. This is the same config your OTP emails use.</p>
  <div class="warn"><strong>Dev tool.</strong> Anyone with the key can send mail from here. Delete <code>public/devs/mail-test.php</code> before going live.</div>

  <div class="card">
    <h2>Environment</h2>
    <div class="grid">
      <div class="row"><span>PHP version</span><span><?php echo $h(PHP_VERSION); ?></span></div>
      <div class="row"><span>PHPMailer loaded</span><span><?php echo $badge($phpmailerAvailable); ?></span></div>
      <div class="row"><span>vendor/autoload.php</span><span><?php echo $badge($vendorAutoload); ?></span></div>
      <div class="row"><span>openssl (for TLS/SSL)</span><span><?php echo $badge($opensslLoaded); ?></span></div>
      <div class="row"><span>app/config/mail.php</span><span><?php echo $badge($mailConfigExists); ?></span></div>
      <div class="row"><span>Password in config</span><span><?php echo $badge(!empty($cfg['password'])); ?></span></div>
    </div>
  </div>

  <?php if ($result === 'ok'): ?>
    <div class="alert ok"><strong>Sent.</strong> PHPMailer accepted the message for <?php echo $h($form['to']); ?>. Check that inbox (and spam). If it arrives, your settings are correct — copy them into <code>app/config/mail.php</code>.</div>
  <?php elseif ($result === 'error'): ?>
    <div class="alert err"><strong>Failed.</strong> <?php echo $h($errorInfo); ?></div>
  <?php endif; ?>

  <div class="card">
    <h2>Send a test</h2>
    <form method="post" action="?key=<?php echo $h(DEV_KEY); ?>">
      <div class="f">
        <label>Send test email to</label>
        <input name="to" type="email" value="<?php echo $h($form['to']); ?>" placeholder="you@example.com" required>
      </div>
      <div class="two">
        <div class="f"><label>SMTP host</label><input name="host" value="<?php echo $h($form['host']); ?>" placeholder="smtp.gmail.com"></div>
        <div class="f"><label>Port</label><input name="port" value="<?php echo $h($form['port']); ?>" placeholder="587"></div>
      </div>
      <div class="two">
        <div class="f">
          <label>Encryption</label>
          <select name="encryption">
            <?php foreach (['tls'=>'TLS (STARTTLS, 587)','ssl'=>'SSL (SMTPS, 465)','none'=>'None'] as $k=>$lbl): ?>
              <option value="<?php echo $k; ?>" <?php echo $form['encryption']===$k?'selected':''; ?>><?php echo $lbl; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="f" style="display:flex;align-items:flex-end">
          <label class="check" style="margin:0 0 9px"><input type="checkbox" name="auth" value="1" <?php echo $form['auth']?'checked':''; ?>> Use SMTP auth</label>
        </div>
      </div>
      <div class="two">
        <div class="f"><label>Username</label><input name="username" value="<?php echo $h($form['username']); ?>" placeholder="you@gmail.com" autocomplete="off"></div>
        <div class="f"><label>Password</label><input name="password" type="password" placeholder="<?php echo !empty($cfg['password'])?'•••• (using saved)':'app password'; ?>" autocomplete="off"></div>
      </div>
      <div class="two">
        <div class="f"><label>From email</label><input name="from_email" value="<?php echo $h($form['from_email']); ?>" placeholder="no-reply@yourshop.com"></div>
        <div class="f"><label>From name</label><input name="from_name" value="<?php echo $h($form['from_name']); ?>"></div>
      </div>
      <label class="check"><input type="checkbox" name="skip_verify" value="1" <?php echo $form['skip_verify']?'checked':''; ?>> Skip TLS certificate verification (local testing only)</label>
      <button class="btn" type="submit">Send test email</button>
    </form>
  </div>

  <?php if ($transcript !== ''): ?>
  <div class="card">
    <h2>SMTP transcript</h2>
    <pre><?php echo $h($transcript); ?></pre>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Reading the result</h2>
    <p class="note" style="margin-top:0">
      If <strong>PHPMailer loaded = no</strong>: it isn't autoloaded. Make sure <code>vendor/autoload.php</code> exists and is current
      (<code>composer require phpmailer/phpmailer</code>), or that your bootstrap requires PHPMailer where it actually lives.<br><br>
      <strong>Connection refused / timed out</strong> → wrong host/port, or your network/firewall blocks outgoing SMTP.<br>
      <strong>535 / authentication failed</strong> → wrong username/password. For Gmail you must use a 16-char <em>App Password</em>, not your normal password, with 2-step verification enabled.<br>
      <strong>Certificate / SSL error</strong> → tick "Skip TLS certificate verification" for local testing (don't ship that).<br>
      <strong>Sent, but no inbox email</strong> → check spam; the From address may be getting filtered.
    </p>
  </div>
</div>
</body>
</html>