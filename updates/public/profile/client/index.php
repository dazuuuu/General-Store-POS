<?php
// public/profile/client/index.php
$page_title = 'My Profile';
$page_header = 'Profile Settings';
$page_subheader = 'Manage your account information and preferences';

ob_start();

require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

// Check if logged in
if (!isLoggedIn()) {
    redirect(public_url('auth/login.php'));
}

// Check if user is client (role_id 3)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
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

// Get user activity stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM projects WHERE created_by = :user_id");
$stmt->execute([':user_id' => $user_id]);
$userProjects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
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
        // Refresh profile data
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

// Set flash messages
if ($success) {
    $_SESSION['flash']['success'] = $success;
}
if ($error) {
    $_SESSION['flash']['error'] = $error;
}
?>

<style>
    .profile-avatar {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    .profile-avatar i {
        font-size: 60px;
        color: #fff;
    }
    .stat-card-client {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        transition: transform 0.3s;
    }
    .stat-card-client:hover {
        transform: translateY(-5px);
    }
    .stat-number-client {
        font-size: 1.8rem;
        font-weight: 700;
        color: #667eea;
    }
</style>

<div class="row">
    <!-- Profile Sidebar -->
    <div class="col-md-4 mb-4">
        <div class="card-modern">
            <div class="card-body text-center">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h3><?php echo htmlspecialchars($username); ?></h3>
                <p class="text-muted"><?php echo htmlspecialchars($email); ?></p>
                <span class="badge bg-info">Client Account</span>
                <hr>
                <div class="d-grid gap-2">
                    <a href="#profile-info" class="btn btn-primary">Profile Info</a>
                    <a href="#change-password" class="btn btn-outline-warning">Change Password</a>
                </div>
            </div>
        </div>
        
        <!-- Client Stats -->
        <div class="card-modern mt-4">
            <div class="card-header-modern">
                <h5 class="mb-0">Your Activity</h5>
            </div>
            <div class="card-body">
                <div class="stat-card-client mb-3">
                    <div class="stat-number-client"><?php echo $userProjects; ?></div>
                    <div class="stat-label">Projects Submitted</div>
                </div>
                <div class="stat-card-client">
                    <div class="stat-number-client"><?php echo date('Y-m-d'); ?></div>
                    <div class="stat-label">Member Since</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Forms -->
    <div class="col-md-8 mb-4">
        <!-- Profile Information -->
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
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card-modern mt-4" id="change-password">
            <div class="card-header-modern">
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
include __DIR__ . '/../../templates/client/layout.php';
?>