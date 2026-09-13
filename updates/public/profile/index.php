<?php
// public/profile/index.php (Using Client Template)
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

// Check if logged in
if (!isLoggedIn()) {
    redirect(public_url('auth/login.php'));
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];

$success = '';
$error = '';

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$profile = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    $sql = "INSERT INTO user_profiles (user_id, first_name, last_name, phone) 
            VALUES (:user_id, :first_name, :last_name, :phone)
            ON DUPLICATE KEY UPDATE 
            first_name = VALUES(first_name), 
            last_name = VALUES(last_name), 
            phone = VALUES(phone)";
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([
        ':user_id' => $user_id,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':phone' => $phone
    ])) {
        $success = 'Profile updated successfully!';
        $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $profile = $stmt->fetch();
    } else {
        $error = 'Failed to update profile';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!password_verify($current_password, $user['password_hash'])) {
        $error = 'Current password is incorrect';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :user_id");
        if ($stmt->execute([':hash' => $new_hash, ':user_id' => $user_id])) {
            $success = 'Password changed successfully!';
        } else {
            $error = 'Failed to change password';
        }
    }
}

// Page variables
$page_title = 'My Profile';
$page_header = 'Profile Settings';
$page_subheader = 'Manage your account information and preferences';

// Set flash messages
if ($success) {
    $_SESSION['flash']['success'] = $success;
}
if ($error) {
    $_SESSION['flash']['error'] = $error;
}

// Content
ob_start();
?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card-modern">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-5x" style="color: var(--primary-color);"></i>
                </div>
                <h4><?php echo htmlspecialchars($username); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($email); ?></p>
                <hr>
                <div class="d-grid gap-2">
                    <a href="#profile-info" class="btn btn-outline-primary">Profile Info</a>
                    <a href="#change-password" class="btn btn-outline-warning">Change Password</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8 mb-4">
        <div class="card-modern" id="profile-info">
            <div class="card-header-modern">
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" disabled>
                        <small class="text-muted">Email cannot be changed</small>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary-modern">
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card-modern mt-4" id="change-password">
            <div class="card-header-modern bg-warning">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

// Include the client template
include __DIR__ . '/../templates/client/layout.php';
?>