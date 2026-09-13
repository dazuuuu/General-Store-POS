<?php
/**
 * POST /Ismano/public/api/gallery/create.php
 * Admin only. Accepts multipart/form-data (so it can carry a file upload).
 *
 * Expected fields: title (required), description, media_type, category, tags,
 * sort_order, is_featured, status, video_url, video_embed_code, media_file (file).
 * CSRF: send the token as a `csrf_token` field or an `X-CSRF-Token` header.
 */
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/GalleryController.php';
require_once __DIR__ . '/../../../app/helpers/uploads.php';
require_once __DIR__ . '/../_lib/respond.php';

require_method('POST');
require_admin();

// Verify CSRF if your project provides the helper.
if (function_exists('csrf_verify')) {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!csrf_verify($token)) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token'], 419);
    }
}

$controller = new GalleryController($pdo);
$media_file = $_FILES['media_file'] ?? null;

try {
    $result = $controller->create($_POST, $media_file);
} catch (Throwable $e) {
    error_log('[gallery/create] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while creating item'], 500);
}

json_response($result, $result['success'] ? 201 : 422);