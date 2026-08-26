<?php /* PWA head tags + service-worker registration. Include inside <head>. */ ?>
<?php require_once __DIR__ . '/../../app/helpers/PathConfig.php'; ?>
<link rel="manifest" href="<?php echo public_url('manifest.webmanifest'); ?>">
<meta name="theme-color" content="#16a34a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Shop POS">
<link rel="apple-touch-icon" href="<?php echo public_url('assets/icons/apple-touch-icon.png'); ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo public_url('assets/icons/favicon-32.png'); ?>">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('<?php echo public_url('sw.js'); ?>', { scope: '<?php echo public_url(); ?>' })
      .catch(function (e) { console.warn('SW registration failed', e); });
  });
}
</script>
