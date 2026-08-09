<?php
// app/services/Mailer.php
// Thin PHPMailer wrapper. Works whether PHPMailer was installed via Composer
// (vendor/autoload.php) or dropped in manually at app/lib/PHPMailer/src/.

class Mailer {
    private $cfg;
    private static $loaded = false;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
        self::loadPhpMailer();
    }

    /** Locate PHPMailer from Composer vendor dir or a manual lib folder. */
    private static function loadPhpMailer(): void {
        if (self::$loaded) return;

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            self::$loaded = true;
            return;
        }

        $candidates = [
            __DIR__ . '/../../vendor/autoload.php',                  // composer
            __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php',         // manual drop-in
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                if (substr($path, -12) === 'autoload.php') {
                    require_once $path;
                } else {
                    $dir = dirname($path);
                    require_once $dir . '/Exception.php';
                    require_once $dir . '/PHPMailer.php';
                    require_once $dir . '/SMTP.php';
                }
                self::$loaded = true;
                return;
            }
        }
        // Not found — send() will report this rather than fatal.
    }

    /**
     * @return array ['ok'=>bool, 'message'=>string]
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): array {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return ['ok' => false, 'message' => 'PHPMailer is not installed (run composer require phpmailer/phpmailer)'];
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Invalid recipient email'];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            if (($this->cfg['transport'] ?? 'smtp') === 'smtp') {
                $mail->isSMTP();
                $mail->Host = $this->cfg['host'] ?? '';
                $mail->Port = (int) ($this->cfg['port'] ?? 587);
                $mail->SMTPAuth = true;
                $mail->Username = $this->cfg['username'] ?? '';
                $mail->Password = $this->cfg['password'] ?? '';
                $enc = $this->cfg['encryption'] ?? 'tls';
                if ($enc) $mail->SMTPSecure = $enc;
            }

            $mail->setFrom($this->cfg['from_email'] ?? 'no-reply@example.com', $this->cfg['from_name'] ?? 'Store');
            $mail->addAddress($toEmail, $toName);

            // Embed the logo so it shows without a public image URL: reference cid:ismanlogo.
            $logo = $this->cfg['logo_path'] ?? '';
            if ($logo && is_file($logo)) {
                $mail->addEmbeddedImage($logo, 'ismanlogo', 'logo.png');
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));

            $mail->send();
            return ['ok' => true, 'message' => 'sent'];
        } catch (Throwable $e) {
            error_log('[mailer] ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}