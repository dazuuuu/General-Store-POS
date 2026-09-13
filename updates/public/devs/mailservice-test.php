<?php
// public/devs/mailservice-test.php
// DEV-ONLY. Calls MailService directly — the exact path OTP uses — and reports
// which file loaded, what config it read, and the precise send result.
// Key-guarded. Delete before production.
//   http://localhost/Modern/public/devs/mailservice-test.php?key=modern-dev

require_once __DIR__ . '/../../app/app.php';

const DEV_KEY = 'modern-dev';
if (!hash_equals(DEV_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — append ?key=modern-dev');
}

use PHPMailer\PHPMailer\PHPMailer;

$mailServiceFile = class_exists('MailService') ? (new ReflectionClass('MailService'))->getFileName() : '(MailService class NOT found)';
$sendSrc = '';
if (class_exists('MailService') && method_exists('MailService', 'send')) {
    $rm = new ReflectionMethod('MailService', 'send');
    $hasSkipVerify = strpos(implode('', array_slice(file($rm->getFileName()), $rm->getStartLine() - 1, $rm->getEndLine() - $rm->getStartLine() + 1)), 'skip_verify') !== false;
    $sendSrc = $hasSkipVerify ? 'contains skip_verify (latest version)' : 'NO skip_verify (older version — redeploy MailService.php)';
}

$cfg = is_file(ROOT_PATH . '/app/config/mail.php') ? require ROOT_PATH . '/app/config/mail.php' : [];
$phpmailer = class_exists(PHPMailer::class);

$result = null; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result = false; $err = 'Enter a valid recipient email.';
    } else {
        $ok = (new MailService())->send($to, 'MailService direct test', '<p>If you received this, MailService works and OTP will too.</p>');
        $result = $ok;
        $err = $ok ? '' : MailService::lastError();
    }
}

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$yn = fn($b) => $b ? '<span style="color:#166534;font-weight:700">yes</span>' : '<span style="color:#991b1b;font-weight:700">no</span>';
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MailService test</title>
<style>
 body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0}
 .wrap{max-width:680px;margin:0 auto;padding:26px 18px}
 h1{font-size:1.3rem;margin:0 0 14px}
 .card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;margin-bottom:16px}
 .card h2{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin:0 0 12px}
 .r{display:flex;justify-content:space-between;gap:14px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:.9rem}
 .r span:first-child{color:#64748b} code{font-family:ui-monospace,Menlo,monospace;font-size:.82rem;word-break:break-all}
 input{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;font-size:.95rem;margin-bottom:12px}
 .btn{background:#2563eb;color:#fff;border:0;border-radius:8px;padding:11px 20px;font-weight:600;cursor:pointer}
 .alert{border-radius:8px;padding:12px 14px;font-size:.92rem;margin-bottom:14px}
 .ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}
</style></head><body><div class="wrap">
<h1>MailService direct test</h1>

<?php if ($result === true): ?>
  <div class="alert ok"><strong>MailService sent it.</strong> Check the inbox. If this works, OTP email works — the issue was elsewhere in the login flow.</div>
<?php elseif ($result === false): ?>
  <div class="alert bad"><strong>MailService failed.</strong> <?php echo $h($err); ?></div>
<?php endif; ?>

<div class="card">
  <h2>What's loaded</h2>
  <div class="r"><span>MailService file</span><code><?php echo $h($mailServiceFile); ?></code></div>
  <div class="r"><span>MailService version</span><code><?php echo $h($sendSrc); ?></code></div>
  <div class="r"><span>PHPMailer loaded</span><span><?php echo $yn($phpmailer); ?></span></div>
</div>

<div class="card">
  <h2>Config MailService reads (app/config/mail.php)</h2>
  <div class="r"><span>file exists</span><span><?php echo $yn(is_file(ROOT_PATH . '/app/config/mail.php')); ?></span></div>
  <div class="r"><span>host</span><code><?php echo $h($cfg['host'] ?? '(none)'); ?></code></div>
  <div class="r"><span>port / encryption</span><code><?php echo $h(($cfg['port'] ?? '?') . ' / ' . ($cfg['encryption'] ?? '?')); ?></code></div>
  <div class="r"><span>username</span><code><?php echo $h($cfg['username'] ?? '(none)'); ?></code></div>
  <div class="r"><span>password present</span><span><?php echo $yn(!empty($cfg['password'])); ?></span></div>
  <div class="r"><span>from_email</span><code><?php echo $h($cfg['from_email'] ?? '(none)'); ?></code></div>
  <div class="r"><span>skip_verify</span><span><?php echo $yn(!empty($cfg['skip_verify'])); ?></span></div>
</div>

<div class="card">
  <h2>Send via MailService</h2>
  <form method="post" action="?key=<?php echo $h(DEV_KEY); ?>">
    <input name="to" type="email" placeholder="your-email@example.com" value="<?php echo $h($_POST['to'] ?? ''); ?>" required>
    <button class="btn" type="submit">Send through MailService</button>
  </form>
</div>
</div></body></html>