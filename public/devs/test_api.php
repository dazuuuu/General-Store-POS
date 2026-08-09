<?php
// public/devs/test_api.php (API testing endpoint)
header('Content-Type: application/json');

require_once '../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/init.php';

use Controllers\AuthController;

$auth = new AuthController();
$action = $_GET['action'] ?? '';

switch($action) {
    case 'check_session':
        echo json_encode([
            'logged_in' => $auth->isLoggedIn(),
            'user' => $auth->getCurrentUser()
        ]);
        break;
        
    case 'test_login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $auth->login($data['email'] ?? '', $data['password'] ?? '');
            echo json_encode($result);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>