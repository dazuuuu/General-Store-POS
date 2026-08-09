<?php
/**
 * GET /Ismano/public/api/gallery/list.php
 * Public, read-only. Returns active gallery items as JSON.
 *
 * Query params (all optional):
 *   category=Weddings      filter by category
 *   type=image|video       filter by media type
 *   featured=1             only featured items
 *   limit=30  offset=0     pagination
 */
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/GalleryController.php';
require_once __DIR__ . '/../_lib/respond.php';

require_method('GET');

$controller = new GalleryController($pdo);

$filters = ['status' => 'active']; // the public endpoint never exposes inactive items

if (isset($_GET['category']) && $_GET['category'] !== '') {
    $filters['category'] = $_GET['category'];
}
if (isset($_GET['type']) && in_array($_GET['type'], ['image', 'video'], true)) {
    $filters['media_type'] = $_GET['type'];
}
if (isset($_GET['featured']) && in_array((string) $_GET['featured'], ['0', '1'], true)) {
    $filters['featured'] = (int) $_GET['featured'];
}
$filters['limit']  = isset($_GET['limit'])  ? min(100, max(1, (int) $_GET['limit'])) : 30;
$filters['offset'] = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

try {
    $items = $controller->getFiltered($filters);
} catch (Throwable $e) {
    error_log('[gallery/list] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to load gallery items'], 500);
}

$data = array_map('shape_gallery_item', $items);

json_response([
    'success' => true,
    'count'   => count($data),
    'data'    => $data,
]);