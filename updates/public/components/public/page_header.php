<?php
// public/components/public/page_header.php
/**
 * Page Header Component
 * Usage: include __DIR__ . '/components/public/page_header.php';
 * 
 * Required variables:
 * - $header_title: The main heading (e.g., "Our Projects")
 * - $header_subtitle: The accent/subtitle text (e.g., "What we've created")
 * - $header_description: The descriptive paragraph (e.g., "Explore our portfolio...")
 * - $header_image: (Optional) Background image URL - if not set, uses default gradient
 * 
 * Optional variables:
 * - $header_highlight: Word to highlight in the title (e.g., "Projects")
 * - $header_overlay_opacity: Custom overlay opacity (0-1, default 0.6)
 */

// Default values if not set
$header_image = $header_image ?? null;
$header_highlight = $header_highlight ?? null;
$header_overlay_opacity = $header_overlay_opacity ?? 0.7;

// Generate highlighted title
$header_title_display = $header_title;
if ($header_highlight && str_contains($header_title, $header_highlight)) {
    $header_title_display = str_replace($header_highlight, '<strong>' . $header_highlight . '</strong>', $header_title);
}
?>

<!-- Page Header Component -->
<div class="page-header-modern <?php echo $header_image ? 'has-image' : ''; ?>">
    
    <!-- Background Image (if provided) -->
    <?php if ($header_image): ?>
    <div class="header-background">
        <img src="<?php echo htmlspecialchars($header_image); ?>" alt="" loading="eager">
        <div class="header-overlay" style="background: linear-gradient(135deg, rgba(0,0,0,0.<?php echo $header_overlay_opacity * 10; ?>) 0%, rgba(0,0,0,0.<?php echo ($header_overlay_opacity - 0.2) * 10; ?>) 100%);"></div>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <div class="header-content">
            <?php if ($header_subtitle): ?>
            <div class="header-eyebrow">
                <span class="eyebrow-line"></span>
                <span class="eyebrow-text"><?php echo htmlspecialchars($header_subtitle); ?></span>
                <span class="eyebrow-line"></span>
            </div>
            <?php endif; ?>
            
            <h1 class="header-title">
                <?php echo $header_title_display; ?>
            </h1>
            
            <?php if ($header_description): ?>
            <p class="header-description">
                <?php echo htmlspecialchars($header_description); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Page Header Styles */
.page-header-modern {
    position: relative;
    padding: 80px 0;
    background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-secondary) 100%);
    color: white;
    overflow: hidden;
    margin-bottom: 60px;
}

.page-header-modern.has-image {
    background: none;
    padding: 100px 0;
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 0;
}

.header-background img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    filter: brightness(0.7);
}

.header-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1;
}

.page-header-modern .container {
    position: relative;
    z-index: 2;
}

.header-content {
    max-width: 700px;
    text-align: left;
}

/* Eyebrow / Subtitle */
.header-eyebrow {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.eyebrow-line {
    width: 40px;
    height: 2px;
    background: var(--brand-accent);
    border-radius: 2px;
}

.eyebrow-text {
    font-family: var(--font-body);
    font-size: var(--text-xs);
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.7);
}

/* Title */
.header-title {
    font-family: var(--font-display);
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 20px;
    color: white;
}

.header-title strong {
    color: var(--brand-accent);
    font-weight: 700;
    position: relative;
    display: inline-block;
}

.header-title strong::after {
    content: '';
    position: absolute;
    bottom: 8px;
    left: 0;
    right: 0;
    height: 8px;
    background: rgba(235, 169, 78, 0.3);
    z-index: -1;
    border-radius: 4px;
}

/* Description */
.header-description {
    font-family: var(--font-body);
    font-size: var(--text-md);
    line-height: 1.6;
    color: rgba(255,255,255,0.85);
    max-width: 550px;
    margin-bottom: 0;
}

/* Decorative elements */
.page-header-modern::before {
    content: '';
    position: absolute;
    bottom: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
    z-index: 1;
}

.page-header-modern::after {
    content: '';
    position: absolute;
    top: -80px;
    left: -80px;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
    z-index: 1;
}

/* With image variant adjustments */
.page-header-modern.has-image::before,
.page-header-modern.has-image::after {
    display: none;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header-modern {
        padding: 50px 0;
        margin-bottom: 40px;
    }
    
    .page-header-modern.has-image {
        padding: 60px 0;
    }
    
    .header-eyebrow {
        gap: 12px;
    }
    
    .eyebrow-line {
        width: 30px;
    }
    
    .header-description {
        font-size: var(--text-sm);
    }
    
    .header-title strong::after {
        bottom: 4px;
        height: 5px;
    }
}

@media (max-width: 480px) {
    .page-header-modern {
        padding: 40px 0;
    }
    
    .header-eyebrow {
        margin-bottom: 15px;
    }
    
    .header-title {
        margin-bottom: 15px;
    }
}
</style>