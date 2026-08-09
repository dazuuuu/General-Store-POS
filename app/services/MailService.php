<?php
// app/services/MailService.php
// One place that actually sends email, via PHPMailer over SMTP, using the
// settings in app/config/mail.php. Used for activation + OTP emails.
//
// Expected app/config/mail.php shape (adjust keys to match yours):
//   return [
//     'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls',
//     'username' => '...', 'password' => '...',
//     'from_email' => 'no-reply@yourdomain.com', 'from_name' => 'Modern POS',
//   ];

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class MailService
{
    private array $cfg;
    private static string $lastError = '';

    /** Reason the most recent send() failed (empty if it succeeded). */
    public static function lastError(): string
    {
        return self::$lastError;
    }

    public function __construct(?array $cfg = null)
    {
        // Single source of truth for mail settings: app/config/mail.php.
        $this->cfg = $cfg ?? (is_file(ROOT_PATH . '/app/config/mail.php') ? require ROOT_PATH . '/app/config/mail.php' : []);
    }

    /** @return bool true on send, false on failure (never throws to the page). */
    /**
     * @param array $attachments each: ['data' => binary string, 'name' => 'file.pdf', 'type' => 'application/pdf']
     */
    public function send(string $to, string $subject, string $html, string $altBody = '', array $attachments = []): bool
    {
        self::$lastError = '';
        if (!class_exists(PHPMailer::class)) {
            self::$lastError = 'PHPMailer is not loaded (no vendor/autoload.php, or it is not required in app.php).';
            error_log('MailService: ' . self::$lastError);
            return false;
        }
        if (empty($this->cfg)) {
            self::$lastError = 'app/config/mail.php is missing or empty — no SMTP settings to send with.';
            error_log('MailService: ' . self::$lastError);
            return false;
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->cfg['host'] ?? 'localhost';
            $mail->Port = (int) ($this->cfg['port'] ?? 587);
            if (!empty($this->cfg['username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->cfg['username'];
                $mail->Password = $this->cfg['password'] ?? '';
            }
            $enc = $this->cfg['encryption'] ?? 'tls';
            if ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            // Same escape hatch the working mail-test page uses: skip TLS cert
            // verification (local boxes often can't verify Gmail's cert).
            if (!empty($this->cfg['skip_verify'])) {
                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            }
            $mail->Timeout = 15;
            $mail->setFrom($this->cfg['from_email'] ?? 'no-reply@localhost', $this->cfg['from_name'] ?? 'Modern POS');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($html);
            foreach ($attachments as $att) {
                if (!empty($att['data']) && !empty($att['name'])) {
                    $mail->addStringAttachment($att['data'], $att['name'], 'base64', $att['type'] ?? 'application/octet-stream');
                }
            }
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            self::$lastError = ($mail->ErrorInfo ?? '') !== '' ? $mail->ErrorInfo : $e->getMessage();
            error_log('MailService send failed: ' . self::$lastError);
            return false;
        }
    }
}