<?php
// public/devs/index.php (Updated with separate registration links)
require_once __DIR__ . '/../../app/init.php';

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ismano Dev Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #f8f9fa; min-height: 100vh; }
        .nav-link { color: #333; transition: all 0.3s; }
        .nav-link:hover { background: #e9ecef; transform: translateX(5px); }
        .iframe-container { height: 80vh; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; }
        .status-logged-in { background: #d4edda; color: #155724; }
        .status-logged-out { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4 class="mb-3">🔧 Ismano Dev</h4>
                    <hr>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="?page=">🏠 Home</a>
                        <div class="mt-2 mb-2"><strong>Authentication:</strong></div>
                        <a class="nav-link" href="?page=register-user">👤 User Registration</a>
                        <a class="nav-link" href="?page=register-admin">👑 Admin Registration</a>
                        <a class="nav-link" href="?page=login">🔐 Login</a>
                        <a class="nav-link" href="?page=forgot">🔑 Forgot Password</a>
                        <a class="nav-link" href="?page=dashboard">📊 Dashboard</a>
                    </nav>
                    <hr>
                    <div class="mt-3">
                        <strong>Session Status:</strong><br>
                        <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                            <span class="status-badge status-logged-in">✓ Logged In</span>
                            <div class="mt-2 small">
                                <strong>User:</strong> <?php echo $_SESSION['username']; ?><br>
                                <strong>Role:</strong> <?php echo $_SESSION['role']; ?><br>
                                <strong>Email:</strong> <?php echo $_SESSION['email']; ?>
                            </div>
                        <?php else: ?>
                            <span class="status-badge status-logged-out">✗ Not Logged In</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <h2>Developer Testing Portal</h2>
                    <hr>
                    
                    <div class="alert alert-info">
                        <strong>ℹ️ Production Note:</strong> 
                        <ul class="mb-0 mt-2">
                            <li>Users register via <code>register.php</code> - Automatically assigned <strong>USER</strong> role</li>
                            <li>Admins register via <code>register_admin.php</code> - Requires admin code, automatically assigned <strong>ADMIN</strong> role</li>
                            <li>No role selection by users - Roles are hardcoded in the registration process</li>
                        </ul>
                    </div>
                    
                    <div class="iframe-container">
                        <?php
                        $page_param = $_GET['page'] ?? '';
                        $url = '';
                        
                        switch($page_param) {
                            case 'register-user':
                                $url = '../auth/register.php';
                                break;
                            case 'register-admin':
                                $url = '../auth/register_admin.php';
                                break;
                            case 'login':
                                $url = '../auth/login.php';
                                break;
                            case 'forgot':
                                $url = '../auth/forgot_password.php';
                                break;
                            case 'dashboard':
                                $url = '../index.php';
                                break;
                            default:
                                $url = '../index.php';
                        }
                        ?>
                        <iframe src="<?php echo $url; ?>" style="width:100%; height:100%; border:none;"></iframe>
                    </div>
                    
                    <div class="mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <strong>Testing Instructions</strong>
                                    </div>
                                    <div class="card-body">
                                        <h6>1. Test User Registration:</h6>
                                        <p>Click "User Registration" and create a new account<br>
                                        <small class="text-muted">→ Account will be created with ROLE: USER automatically</small></p>
                                        
                                        <h6 class="mt-3">2. Test Admin Registration:</h6>
                                        <p>Click "Admin Registration" - Requires admin code: <code>ADMIN2024</code><br>
                                        <small class="text-muted">→ Account will be created with ROLE: ADMIN automatically</small></p>
                                        
                                        <h6 class="mt-3">3. Test Login:</h6>
                                        <p>Test both user and admin accounts<br>
                                        <small class="text-muted">→ Check sidebar to see your role after login</small></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <strong>Database Tables</strong>
                                    </div>
                                    <div class="card-body">
                                        <ul class="mb-0">
                                            <li><code>users</code> - role_id determines access level</li>
                                            <li><code>roles</code> - 1:superadmin, 2:admin, 3:user</li>
                                            <li><code>user_profiles</code> - Extended info</li>
                                            <li><code>login_attempts</code> - Security logging</li>
                                        </ul>
                                        <hr>
                                        <small>Run SQL from <code>databases/migrations/001_create_users_table.sql</code></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>