<?php
// app/controllers/UserController.php
namespace Controllers;

use Models\UserModel;
use Models\SessionModel;

class UserController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
        SessionModel::start();
    }
    
    public function register($data) {
        // Validate
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        if (strlen($data['password']) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        // Check existence
        $existingUser = $this->userModel->getUserByEmail($data['email']);
        if ($existingUser) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        $existingUsername = $this->userModel->getUserByUsername($data['username']);
        if ($existingUsername) {
            return ['success' => false, 'message' => 'Username already taken'];
        }
        
        // Create user (role_id: 3 = regular user)
        if ($this->userModel->createUser($data['username'], $data['email'], $data['password'], 3)) {
            $user = $this->userModel->getUserByEmail($data['email']);
            if ($user) {
                $this->userModel->updateProfile(
                    $user['id'],
                    $data['first_name'] ?? '',
                    $data['last_name'] ?? '',
                    $data['phone'] ?? ''
                );
            }
            return ['success' => true, 'message' => 'Registration successful! Please login.'];
        }
        
        return ['success' => false, 'message' => 'Registration failed'];
    }
    
    public function updateProfile($user_id, $data) {
        return $this->userModel->updateProfile(
            $user_id,
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['phone'] ?? ''
        );
    }
    
    public function changePassword($user_id, $currentPassword, $newPassword, $confirmPassword) {
        $user = $this->userModel->getUserByEmail(SessionModel::get('email'));
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'New passwords do not match'];
        }
        
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        if ($this->userModel->updatePassword($user_id, $newPassword)) {
            return ['success' => true, 'message' => 'Password changed successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to change password'];
    }
}
?>