<!-- app/templates/layouts/main.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Ismano'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f4f4; }
        .container { margin-top: 50px; }
        .card { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .navbar { margin-bottom: 30px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/Ismano/public/">Ismano</a>
            <div class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                    <span class="nav-item nav-link text-light">Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a class="nav-link" href="/Ismano/public/auth/logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="/Ismano/public/auth/login.php">Login</a>
                    <a class="nav-link" href="/Ismano/public/auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <?php if (isset($content)) echo $content; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>