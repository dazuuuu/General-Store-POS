<?php
/**
 * POST /Ismano/public/api/gallery/update_sort.php
 * Admin only. Updates a single item's sort_order.
 * Body: JSON { "id": 12, "sort_order": 3 }  (also accepts form-encoded).
 *
 * NOTE: the admin page's fetch() does not currently send a CSRF token, so this
 * relies on an authenticated admin session. To harden it, expose the token in a
 * <meta name="csrf-token"> tag and send it as an X-CSRF-Token header (see the
 * notes I left in chat), then uncomment the CSRF block below.
 */
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/GalleryController.php';
require_once __DIR__ . '/../_lib/respond.php';

require_method('POST');
require_admin();

/*
if (function_exists('csrf_verify')) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_verify($token)) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token'], 419);
    }
}
*/

$body = read_json_body();
if (empty($body)) {
    $body = $_POST;
}

$id   = (int) ($body['id'] ?? 0);
$sort = (int) ($body['sort_order'] ?? 0);

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'A valid id is required'], 400);
}

$controller = new GalleryController($pdo);

try {
    $result = $controller->update($id, ['sort_order' => $sort]);
} catch (Throwable $e) {
    error_log('[gallery/update_sort] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

json_response($result, $result['success'] ? 200 : 422);