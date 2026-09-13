<?php
// app/controllers/AuthController.php
namespace Controllers;

use Models\UserModel;
use Models\SessionModel;

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
        SessionModel::start();
    }
    
    public function login($email, $password, $role_type = 'user') {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }
        
        $user = $this->userModel->getUserByEmail($email);
        
        if (!$user) {
            $this->userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR']);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            $this->userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR']);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Check role access
        if ($role_type === 'admin' && $user['role_id'] > 2) {
            return ['success' => false, 'message' => 'Access denied. Admin privileges required.'];
        }
        
        if ($role_type === 'user' && $user['role_id'] == 2) {
            // Admin can also access user area
            // Allow access
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Account is deactivated'];
        }
        
        // Set session
        SessionModel::set('user_id', $user['id']);
        SessionModel::set('username', $user['username']);
        SessionModel::set('email', $user['email']);
        SessionModel::set('role', $user['role_name']);
        SessionModel::set('role_id', $user['role_id']);
        SessionModel::set('logged_in', true);
        
        return [
            'success' => true, 
            'message' => 'Login successful!',
            'role' => $user['role_name'],
            'redirect' => $role_type === 'admin' ? '/Ismano/public/admin/dashboard.php' : '/Ismano/public/profile/'
        ];
    }
    
    public function logout() {
        SessionModel::destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public function isLoggedIn() {
        return SessionModel::has('logged_in') && SessionModel::get('logged_in') === true;
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => SessionModel::get('user_id'),
                'username' => SessionModel::get('username'),
                'email' => SessionModel::get('email'),
                'role' => SessionModel::get('role')
            ];
        }
        return null;
    }
}
?>