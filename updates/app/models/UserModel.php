<?php
// app/models/UserModel.php
require_once __DIR__ . '/../config/db_connect.php';

class UserModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    public function createUser($username, $email, $password, $role_id = 3) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified) 
                VALUES (:username, :email, :password_hash, :role_id, 1, 1)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $password_hash,
            ':role_id' => $role_id
        ]);
    }
    
    public function getUserByEmail($email) {
        $sql = "SELECT u.*, r.role_name FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }
    
    public function getUserByUsername($username) {
        $sql = "SELECT u.*, r.role_name FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.username = :username";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $username]);
        return $stmt->fetch();
    }
    
    public function getUserById($id) {
        $sql = "SELECT u.*, r.role_name FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function updateProfile($user_id, $first_name, $last_name, $phone) {
        $sql = "INSERT INTO user_profiles (user_id, first_name, last_name, phone) 
                VALUES (:user_id, :first_name, :last_name, :phone)
                ON DUPLICATE KEY UPDATE 
                first_name = VALUES(first_name), 
                last_name = VALUES(last_name), 
                phone = VALUES(phone)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user_id,
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':phone' => $phone
        ]);
    }
    
    public function getProfile($user_id) {
        $sql = "SELECT * FROM user_profiles WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetch();
    }
    
    public function updatePassword($user_id, $newPassword) {
        $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = :hash WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':hash' => $password_hash, ':id' => $user_id]);
    }
    
    public function logLoginAttempt($email, $ip) {
        $sql = "INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':email' => $email, ':ip' => $ip]);
    }
}
?>