<?php
// app/controllers/AdminController.php
namespace Controllers;

use Models\UserModel;
use Models\SessionModel;

class AdminController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
        SessionModel::start();
    }
    
    public function createAdmin($data) {
        // Admin-specific validation
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        if (strlen($data['password']) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        // Admin code validation
        if (empty($data['admin_code']) || $data['admin_code'] !== 'ADMIN2024') {
            return ['success' => false, 'message' => 'Invalid admin registration code'];
        }
        
        // Check existence
        $existingUser = $this->userModel->getUserByEmail($data['email']);
        if ($existingUser) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Create admin (role_id: 2 = admin)
        if ($this->userModel->createUser($data['username'], $data['email'], $data['password'], 2)) {
            $user = $this->userModel->getUserByEmail($data['email']);
            if ($user) {
                $this->userModel->updateProfile(
                    $user['id'],
                    $data['first_name'] ?? '',
                    $data['last_name'] ?? '',
                    $data['phone'] ?? ''
                );
            }
            return ['success' => true, 'message' => 'Admin registration successful! Please login.'];
        }
        
        return ['success' => false, 'message' => 'Admin registration failed'];
    }
}
?>