<?php
// public/templates/client/layout.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . public_url('auth/login.php'));
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Cart link target — change this if your cart page lives elsewhere.
$cartUrl = public_url('store/cart.php');
$isCart = strpos($_SERVER['REQUEST_URI'], '/store/cart') !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - Modern POS Client</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --dark: #2d3748;
            --light: #f7fafc;
            --gray: #718096;
            --border: #e2e8f0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            padding-bottom: 70px;
        }
        
        /* Top Navbar */
        .top-navbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
            text-decoration: none;
        }
        
        .navbar-brand i {
            margin-right: 8px;
        }
        
        .user-dropdown .btn {
            background: transparent;
            border: none;
            padding: 0;
            color: #1a1a1a;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        
        .user-avatar i {
            font-size: 18px;
        }
        
        /* Main Content */
        .main-content {
            padding: 20px 16px;
            min-height: calc(100vh - 140px);
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 16px 12px;
            z-index: 100;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray);
            transition: all 0.2s;
            font-size: 12px;
        }
        
        .nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        
        .nav-item span {
            font-size: 10px;
        }
        
        .nav-item:hover {
            color: var(--primary);
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        /* Desktop Navigation - hide bottom nav and show sidebar on desktop */
        @media (min-width: 768px) {
            body {
                padding-bottom: 0;
            }
            
            .bottom-nav {
                display: none;
            }
            
            .desktop-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                width: 260px;
                height: 100vh;
                background: #fff;
                border-right: 1px solid var(--border);
                padding: 20px 0;
                overflow-y: auto;
            }
            
            .main-content {
                margin-left: 260px;
                padding: 20px 30px;
            }
            
            .top-navbar {
                margin-left: 260px;
                width: calc(100% - 260px);
            }
        }
        
        /* Desktop Sidebar Menu */
        .desktop-sidebar {
            display: none;
        }
        
        @media (min-width: 768px) {
            .desktop-sidebar {
                display: block;
            }
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 4px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s;
            border-radius: 8px;
            margin: 0 12px;
        }
        
        .sidebar-menu a:hover {
            background: #f5f5f5;
            color: var(--primary);
        }
        
        .sidebar-menu a.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
        }
        
        .sidebar-menu a i {
            width: 20px;
        }
        
        .sidebar-brand {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }
        
        .sidebar-brand h4 {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* Cards */
        .card-modern {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header-modern {
            padding: 16px 20px;
            background: #fafafa;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }
        
        /* Stats Card */
        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* ---- Cart count badge (shared across all three nav spots) ---- */
        .cart-count {
            background: #e74c3c;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 16px;
            height: 16px;
            line-height: 16px;
            padding: 0 4px;
            border-radius: 9999px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }
        /* Top-bar cart icon */
        .cart-top {
            position: relative;
            display: inline-flex;
            align-items: center;
            color: #1a1a1a;
            font-size: 20px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .cart-top:hover { color: var(--primary); }
        .cart-top .cart-count { position: absolute; top: -6px; right: -9px; }
        /* Sidebar cart count sits at the far right of the row */
        .sidebar-menu a .cart-count { margin-left: auto; }
        .sidebar-menu a.active .cart-count { background: #fff; color: var(--primary); }
        /* Bottom-nav cart icon wrapper so the badge anchors to the icon */
        .bn-icon { position: relative; display: inline-block; line-height: 1; margin-bottom: 4px; }
        .bn-icon i { margin-bottom: 0; }
        .bn-icon .cart-count { position: absolute; top: -9px; right: -11px; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .top-navbar {
                position: sticky;
                top: 0;
                z-index: 99;
            }
            
            .main-content {
                padding: 16px;
            }
        }
    </style>
    
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
</head>
<body>

<!-- Desktop Sidebar -->
<div class="desktop-sidebar">
    <div class="sidebar-brand">
        <h4><i class="fas fa-shield-alt me-2"></i>Ismano</h4>
        <small class="text-muted">Client Portal</small>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="/Modern/public/client/dashboard/index.php" class="<?php echo $current_page == 'index.php' && $current_dir == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="/Modern/public/client/cart/index.php" class="<?php echo $isCart ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> Cart
                <span class="cart-count" style="display:none;">0</span>
            </a>
        </li>
        <li>
            <a href="/Modern/public/profile/client/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], '/profile/client/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i> Profile
            </a>
        </li>
        <li>
            <a href="/Modern/public/client/settings/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], '/client/settings/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        <li>
            <a href="/Modern/public/auth/logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>

<!-- Top Navbar -->
<nav class="top-navbar">
    <div class="d-flex justify-content-between align-items-center">
        <a href="/Modern/public/client/dashboard/index.php" class="navbar-brand">
            <i class="fas fa-shield-alt"></i> Modern POS
        </a>
        
        <div class="d-flex align-items-center gap-3">
            <a href="<?php echo htmlspecialchars($cartUrl); ?>" class="cart-top" aria-label="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" style="display:none;">0</span>
            </a>

            <div class="user-dropdown dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/Modern/public/profile/client/index.php">
                        <i class="fas fa-user me-2"></i> My Profile
                    </a></li>
                    <li><a class="dropdown-item" href="/Modern/public/client/cart/index.php">
                        <i class="fas fa-shopping-cart me-2"></i> My Cart
                    </a></li>
                    <li><a class="dropdown-item" href="/Modern/public/client/settings/index.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/Modern/public/auth/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash']['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['flash']['success']; unset($_SESSION['flash']['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash']['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['flash']['error']; unset($_SESSION['flash']['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php echo $content; ?>
</main>

<!-- Bottom Navigation (Mobile Only) -->
<div class="bottom-nav">
    <a href="/Modern/public/client/dashboard/index.php" class="nav-item <?php echo $current_page == 'index.php' && $current_dir == 'dashboard' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i>
        <span>Home</span>
    </a>
    <a href="<?php echo htmlspecialchars($cartUrl); ?>" class="nav-item <?php echo $isCart ? 'active' : ''; ?>">
        <span class="bn-icon">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-count" style="display:none;">0</span>
        </span>
        <span>Cart</span>
    </a>
    <a href="/Modern/public/profile/client/index.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/profile/client/') !== false ? 'active' : ''; ?>">
        <i class="fas fa-user-circle"></i>
        <span>Profile</span>
    </a>
    <a href="/Modern/public/client/settings/index.php" class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/client/settings/') !== false ? 'active' : ''; ?>">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
    </a>
    <a href="/Modern/public/auth/logout.php" class="nav-item">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Live cart count for every nav spot (top bar, sidebar, bottom nav).
// Self-contained so it won't clash with the store page's own updateCartCount();
// all of them update the same .cart-count elements.
(function () {
    function applyCount(count) {
        var n = parseInt(count, 10) || 0;
        document.querySelectorAll('.cart-count').forEach(function (el) {
            el.textContent = n > 99 ? '99+' : n;
            el.style.display = n > 0 ? 'inline-block' : 'none';
        });
    }
    function refreshCart() {
        fetch('/Modern/public/api/count.php', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { if (data && data.success) applyCount(data.count); })
            .catch(function () { /* leave badges hidden on error */ });
    }
    if (document.readyState !== 'loading') {
        refreshCart();
    } else {
        document.addEventListener('DOMContentLoaded', refreshCart);
    }
    // Other scripts can refresh after add/remove: window.dispatchEvent(new Event('cart:updated'));
    window.addEventListener('cart:updated', refreshCart);
})();
</script>

<?php if (isset($extra_js)): ?>
    <?php echo $extra_js; ?>
<?php endif; ?>
</body>
</html>