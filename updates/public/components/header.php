<?php
// public/components/public/header.php
// ---------------------------------------------------------------------
// Shared banner header for public pages. Rendered by templates/public/layout.php.
// Expects these to be set already (layout.php sets them):
//   $bannerTitle (string), $bannerSub (string), $bannerImg (?string url),
//   $page_key (string)
// It is also safe to include standalone — it falls back gracefully.
// ---------------------------------------------------------------------

if (!function_exists('settings_asset_url')) {
    require_once __DIR__ . '/../../../app/helpers/uploads.php';
}

$bannerTitle = $bannerTitle ?? ($page_title ?? 'Ismano');
$bannerSub   = $bannerSub   ?? '';
$bannerImg   = $bannerImg   ?? null;
$page_key    = $page_key    ?? '';
$crumbLabel  = $page_key !== '' ? ucfirst($page_key) : $bannerTitle;
?>
<section class="page-banner <?php echo $bannerImg ? 'has-image' : 'no-image'; ?>">
  <?php if ($bannerImg): ?>
    <div class="page-banner-bg" aria-hidden="true">
      <img src="<?php echo htmlspecialchars($bannerImg); ?>"
           alt="" loading="eager">
    </div>
  <?php endif; ?>

  <div class="page-banner-overlay" aria-hidden="true"></div>

  <div class="container page-banner-inner">
    <nav class="page-crumb" aria-label="Breadcrumb">
      <a href="<?php echo BASE_URL; ?>/">Home</a>
      <i class="fa-solid fa-angle-right crumb-sep" aria-hidden="true"></i>
      <span><?php echo htmlspecialchars($crumbLabel); ?></span>
    </nav>

    <h1 class="page-banner-title"><?php echo htmlspecialchars($bannerTitle); ?></h1>

    <?php if ($bannerSub !== ''): ?>
      <p class="page-banner-sub"><?php echo htmlspecialchars($bannerSub); ?></p>
    <?php endif; ?>
  </div>
</section>

<style>
/* ===================================================================
   PUBLIC PAGE BANNER  (brand palette)
   =================================================================== */
.page-banner {
  position: relative;
  overflow: hidden;
  padding-block: clamp(3.5rem, 8vw, 6rem);
  background: var(--brand-black, #000);
  isolation: isolate;
}

/* Background image (admin-managed) */
.page-banner-bg { position: absolute; inset: 0; z-index: 0; }
.page-banner-bg img {
  width: 100%; height: 100%;
  object-fit: cover; object-position: center;
  transform: scale(1.04);
}

/* Gradient overlay — royal → sky, plus dark floor for legibility */
.page-banner-overlay {
  position: absolute; inset: 0; z-index: 1;
}
.page-banner.has-image .page-banner-overlay {
  background:
    linear-gradient(120deg,
      rgba(7,89,248,0.88) 0%,
      rgba(0,161,243,0.55) 55%,
      rgba(0,0,0,0.30) 100%),
    linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 55%);
}
.page-banner.no-image .page-banner-overlay {
  background:
    linear-gradient(120deg, var(--brand-royal, #0759F8) 0%, var(--brand-sky, #00A1F3) 100%);
}
/* subtle gold light, top-right */
.page-banner::after {
  content: '';
  position: absolute; inset: 0; z-index: 1; pointer-events: none;
  background: radial-gradient(ellipse 50% 70% at 88% 10%,
              rgba(235,169,78,0.22) 0%, transparent 60%);
}

.page-banner-inner { position: relative; z-index: 2; }

/* Breadcrumb */
.page-crumb {
  display: flex; align-items: center; gap: 8px;
  font-size: 0.72rem; font-weight: 600;
  letter-spacing: 0.08em; text-transform: uppercase;
  color: rgba(255,255,255,0.7);
  margin-bottom: 1rem;
}
.page-crumb a { color: rgba(255,255,255,0.85); text-decoration: none; transition: color .2s ease; }
.page-crumb a:hover { color: var(--brand-gold, #EBA94E); }
.page-crumb .crumb-sep { font-size: 0.6rem; opacity: 0.7; }

/* Title */
.page-banner-title {
  font-family: var(--font-display, Georgia, serif);
  font-style: italic;
  font-weight: 700;
  font-size: clamp(2.2rem, 5.5vw, 3.6rem);
  line-height: 1.08;
  color: var(--brand-white, #fff);
  margin: 0 0 0.75rem;
  max-width: 18ch;
}
/* gold accent underline */
.page-banner-title::after {
  content: '';
  display: block;
  width: 56px; height: 4px;
  margin-top: 1rem;
  border-radius: 4px;
  background: var(--brand-gold, #EBA94E);
}

.page-banner-sub {
  font-family: var(--font-body, system-ui, sans-serif);
  font-size: 0.95rem;
  font-weight: 300;
  line-height: 1.75;
  color: rgba(255,255,255,0.82);
  max-width: 52ch;
  margin: 0;
}

@media (max-width: 600px) {
  .page-banner { padding-block: 2.75rem; }
  .page-banner-title { max-width: 100%; }
}
</style>