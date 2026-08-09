<?php
// app/helpers/uploads.php
//
// Security-conscious image upload + CSRF helpers, plus small view helpers
// for resolving the logo / hero URLs. Safe to include more than once.

require_once __DIR__ . '/PathConfig.php';

/* ====================================================================
   CSRF
   ==================================================================== */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }

    function csrf_verify(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/* ====================================================================
   IMAGE UPLOAD
   ==================================================================== */
if (!function_exists('validate_image_upload')) {
    /**
     * Pure validation (no filesystem move) — easy to unit test.
     * @return array{ok:bool, ext?:string, error?:string}
     */
    function validate_image_upload(
        array $file,
        array $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        int $maxBytes = 5242880        // 5 MB
    ): array {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'Invalid upload parameters.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['ok' => false, 'error' => 'No file was selected.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['ok' => false, 'error' => 'The file is too large.'];
            default:
                return ['ok' => false, 'error' => 'Upload failed (code ' . $file['error'] . ').'];
        }

        if (($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'File exceeds ' . round($maxBytes / 1048576, 1) . ' MB.'];
        }

        // Confirm the bytes are actually a recognised image.
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'error' => 'That file is not a valid image.'];
        }

        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        $mime = $info['mime'] ?? '';
        if (!isset($mimeToExt[$mime])) {
            return ['ok' => false, 'error' => 'Unsupported image type. Use JPG, PNG, WebP or GIF.'];
        }

        $ext = $mimeToExt[$mime];
        if (!in_array($ext, $allowedExt, true)) {
            return ['ok' => false, 'error' => 'This image type is not allowed here.'];
        }

        return ['ok' => true, 'ext' => $ext];
    }
}

if (!function_exists('upload_image')) {
    /**
     * Validate, then store the uploaded image with a random safe name.
     * @param string $absDir  absolute destination dir
     * @param string $webDir  web path (relative to /public) saved in DB, no trailing slash
     * @return array{ok:bool, path?:string, error?:string}
     */
    function upload_image(
        array $file,
        string $absDir,
        string $webDir,
        array $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        int $maxBytes = 5242880
    ): array {
        $v = validate_image_upload($file, $allowedExt, $maxBytes);
        if (!$v['ok']) {
            return $v;
        }

        // In a real request the temp file must be an HTTP upload.
        // Tests define UPLOAD_TEST_MODE to exercise the move with copy().
        if (!defined('UPLOAD_TEST_MODE') && !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Security check failed on the uploaded file.'];
        }

        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        }

        $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $v['ext'];
        $dest = rtrim($absDir, '/\\') . DIRECTORY_SEPARATOR . $name;

        $moved = defined('UPLOAD_TEST_MODE')
            ? @copy($file['tmp_name'], $dest)
            : @move_uploaded_file($file['tmp_name'], $dest);

        if (!$moved) {
            return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
        }

        return ['ok' => true, 'path' => rtrim($webDir, '/') . '/' . $name];
    }
}

/* ====================================================================
   VIEW HELPERS
   ==================================================================== */
if (!function_exists('settings_asset_url')) {
    /** Turn a stored relative path (uploads/hero/x.png) into a full web URL. */
    function settings_asset_url(?string $relPath): ?string
    {
        if ($relPath === null || $relPath === '') {
            return null;
        }
        // Already absolute (http or leading slash)? Leave it.
        if (preg_match('#^(https?:)?/#', $relPath)) {
            return $relPath;
        }
        return rtrim(BASE_URL, '/') . '/' . ltrim($relPath, '/');
    }
}