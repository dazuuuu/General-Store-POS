<?php
/**
 * GET /Ismano/public/api/gallery/get.php?id=123
 * Public, read-only. Returns one active item and increments its view count.
 */
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/GalleryController.php';
require_once __DIR__ . '/../_lib/respond.php';

require_method('GET');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['success' => false, 'message' => 'A valid id is required'], 400);
}

$controller = new GalleryController($pdo);

try {
    $item = $controller->getById($id); // increments views
} catch (Throwable $e) {
    error_log('[gallery/get] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to load item'], 500);
}

if (!$item || ($item['status'] ?? '') !== 'active') {
    json_response(['success' => false, 'message' => 'Gallery item not found'], 404);
}

json_response(['success' => true, 'data' => shape_gallery_item($item)]);