<?php
// public/profile/admin/index.php
$page_title = 'Admin Profile';
$breadcrumbs = [
    ['label' => 'Profile', 'active' => true]
];

ob_start();

require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

// Check if logged in
if (!isLoggedIn()) {
    redirect(public_url('auth/login.php'));
}

// Check if user is admin (role_id 1 or 2)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    redirect(public_url('auth/login.php'));
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];
$role = $_SESSION['role'] ?? 'admin';

$success = '';
$error = '';

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$profile = $stmt->fetch();

// Get admin stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
$stmt->execute();
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE status = 'published'");
$stmt->execute();
$totalServices = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM projects WHERE status = 'published'");
$stmt->execute();
$totalProjects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM blogs WHERE status = 'published'");
$stmt->execute();
$totalBlogs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

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
            $success = 'Password changed successfully! Please login again.';
            // Optionally logout and require re-login
            // session_destroy();
            // redirect('/Kitale/public/auth/login.php');
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
    .stat-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        transition: all 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 5px;
    }
    .stat-label {
        color: #666;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 8px;
        color: #1a1a1a;
    }
    .form-control {
        border-radius: 6px;
        border: 1px solid #ccc;
        padding: 8px 12px;
    }
    .form-control:focus {
        border-color: #1a1a1a;
        box-shadow: none;
    }
    .btn-primary-custom {
        background: #1a1a1a;
        border: 1px solid #1a1a1a;
        color: #fff;
        padding: 10px 24px;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .btn-primary-custom:hover {
        background: #333;
        border-color: #333;
    }
    .btn-warning-custom {
        background: #f5f5f5;
        border: 1px solid #ccc;
        color: #1a1a1a;
        padding: 10px 24px;
        border-radius: 6px;
    }
    .btn-warning-custom:hover {
        background: #e0e0e0;
    }
    .card-modern {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        background: #fff;
        margin-bottom: 20px;
    }
    .card-header-modern {
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        background: #fafafa;
        border-radius: 8px 8px 0 0;
        font-weight: 600;
    }
    .card-body {
        padding: 20px;
    }
    .avatar-circle {
        width: 100px;
        height: 100px;
        background: #1a1a1a;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
    }
    .avatar-circle i {
        font-size: 50px;
        color: #fff;
    }
    .role-badge {
        display: inline-block;
        padding: 4px 12px;
        background: #e0e0e0;
        color: #1a1a1a;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
</style>

<div class="row">
    <!-- Admin Stats Cards -->
    <div class="col-md-12 mb-4">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalServices; ?></div>
                    <div class="stat-label">Published Services</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalProjects; ?></div>
                    <div class="stat-label">Published Projects</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalBlogs; ?></div>
                    <div class="stat-label">Published Blogs</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Sidebar -->
    <div class="col-md-4 mb-4">
        <div class="card-modern">
            <div class="card-body text-center">
                <div class="avatar-circle">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h4><?php echo htmlspecialchars($username); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($email); ?></p>
                <span class="role-badge">
                    <i class="fas fa-crown me-1"></i> <?php echo ucfirst($role); ?>
                </span>
                <hr>
                <div class="d-grid gap-2">
                    <a href="#profile-info" class="btn btn-primary-custom">Profile Info</a>
                    <a href="#change-password" class="btn btn-warning-custom">Change Password</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Forms -->
    <div class="col-md-8 mb-4">
        <!-- Profile Information -->
        <div class="card-modern" id="profile-info">
            <div class="card-header-modern">
                <i class="fas fa-user me-2"></i> Profile Information
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
                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($role); ?>" disabled>
                        <small class="text-muted">Administrator account with full access</small>
                    </div>
                    <button type="submit" name="update_profile" class="btn-primary-custom">
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card-modern mt-4" id="change-password">
            <div class="card-header-modern">
                <i class="fas fa-key me-2"></i> Change Password
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
                    <button type="submit" name="change_password" class="btn-warning-custom">
                        <i class="fas fa-key me-2"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card-modern mt-4">
            <div class="card-header-modern">
                <i class="fas fa-bolt me-2"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <a href="/Kitale/public/admin/services/create.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-plus me-1"></i> New Service
                        </a>
                    </div>
                    <div class="col-md-4 mb-2">
                        <a href="/Kitale/public/admin/projects/create.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-plus me-1"></i> New Project
                        </a>
                    </div>
                    <div class="col-md-4 mb-2">
                        <a href="/Kitale/public/admin/blogs/create.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-plus me-1"></i> New Blog Post
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>