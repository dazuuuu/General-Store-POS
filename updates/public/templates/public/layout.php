<?php
// public/templates/public/layout.php
require_once __DIR__ . '/../../../app/helpers/PathConfig.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Use theme.css colors instead of overriding
// The brand colors are already defined in theme.css
$useHomeNavbar = $use_home_navbar ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $page_title ?? 'ISMAN Company - Engineering Services'; ?></title>
    <meta name="description" content="<?php echo $page_description ?? 'ISMAN Company provides premium engineering services, metal fabrication, and turnkey fit-out solutions.'; ?>">
    
    <!-- Theme CSS (contains all design tokens) -->
    <link rel="stylesheet" href="<?php echo public_url('assets/css/theme.css'); ?>">
    
    <!-- Bootstrap CSS (only for components that need it) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
</head>
<body>

<!-- Include navbar -->
<?php include __DIR__ . '/../../components/public/navbar.php'; ?>

<!-- Main Content -->
<main class="main-content">
    <?php echo $content; ?>
</main>

<!-- Footer -->
<?php include __DIR__ . '/../../components/public/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($extra_js)): ?>
    <?php echo $extra_js; ?>
<?php endif; ?>
</body>
</html>