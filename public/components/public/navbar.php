<?php
// public/components/public/navbar.php
// Shared site navbar — ISMAN Company · Engineering Services.
// Two rows: a teal contact bar + a solid white main bar.
// Colours inherit from theme.css tokens with safe fallbacks.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Active-link helper. Guarded against double-include.
if (!function_exists('navIsActive')) {
    function navIsActive(string $segment): string {
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $self = basename($_SERVER['PHP_SELF'] ?? '');
        if ($segment === 'home') {
            $active = $self === 'index.php'
                   && str_contains($uri, '/public/')
                   && !str_contains($uri, '/projects/')
                   && !str_contains($uri, '/services/')
                   && !str_contains($uri, '/blogs/')
                  // && !str_contains($uri, '/store/')
                   && !str_contains($uri, '/contact');
        } else {
            $active = str_contains($uri, '/' . $segment);
        }
        return $active ? 'is-active' : '';
    }
}

// Logo path (falls back to an inline gear mark if the file is missing).
require_once __DIR__ . '/../../../app/helpers/PathConfig.php';
require_once __DIR__ . '/../../../app/helpers/ReceiptFooter.php';

// Brand contact details (single source of truth — used here and in the footer).
$ismanPhone = ReceiptFooter::SHOP_PHONE;
$ismanEmail = ReceiptFooter::SHOP_EMAIL;
$ismanLoc   = ReceiptFooter::SHOP_LOCATION;

$logoPath = public_url('assets/images/logo/logo.png');
$logoExists = isset($_SERVER['DOCUMENT_ROOT'])
    && is_file($_SERVER['DOCUMENT_ROOT'] . $logoPath);
?>
<!-- ===================== TOP CONTACT BAR ===================== -->
<div class="topbar" id="topbar">
    <div class="topbar-inner container">
        <div class="topbar-contacts">
            <a class="topbar-link" href="tel:<?php echo preg_replace('/\s+/', '', $ismanPhone); ?>">
                <i class="fas fa-phone-volume" aria-hidden="true"></i><span><?php echo htmlspecialchars($ismanPhone); ?></span>
            </a>
            <a class="topbar-link" href="mailto:<?php echo htmlspecialchars($ismanEmail); ?>">
                <i class="fas fa-envelope" aria-hidden="true"></i><span><?php echo htmlspecialchars($ismanEmail); ?></span>
            </a>
        </div>
        <div class="topbar-loc">
            <i class="fas fa-location-dot" aria-hidden="true"></i><span><?php echo htmlspecialchars($ismanLoc); ?></span>
        </div>
    </div>
</div>

<!-- ===================== MAIN NAV BAR ======================== -->
<header class="site-header" id="siteHeader">
    <div class="header-inner container">

        <!-- Brand -->
        <a class="header-brand" href="<?php echo public_url(); ?>" aria-label="Dazutech — Home">
            <span class="brand-mark" aria-hidden="true">
                <?php if ($logoExists): ?>
                    <img src="<?php echo $logoPath; ?>" alt="" width="44" height="44">
                <?php else: ?>
                    <!-- Inline engineering gear fallback -->
                    <svg width="40" height="40" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M24 4l3.2 4.1 5-1.7 1.1 5.2 5.2 1.1-1.7 5L46 24l-4.1 3.2 1.7 5-5.2 1.1-1.1 5.2-5-1.7L24 44l-3.2-4.1-5 1.7-1.1-5.2-5.2-1.1 1.7-5L2 24l4.1-3.2-1.7-5 5.2-1.1L10.7 9.4l5 1.7L24 4z"
                              fill="var(--brand-primary,#0D9488)"/>
                        <circle cx="24" cy="24" r="9" fill="#fff"/>
                        <text x="24" y="28.5" text-anchor="middle" font-family="Montserrat, sans-serif"
                              font-size="11" font-weight="800" fill="var(--brand-primary,#0D9488)">C</text>
                    </svg>
                <?php endif; ?>
            </span>
            <span class="brand-text">
                <span class="brand-name">Dazutech</span>
                <span class="brand-tag">Software & AI Services</span>
            </span>
        </a>

        <!-- Desktop nav -->
        <nav class="header-nav" aria-label="Primary navigation">
            <ul class="nav-list" role="list">
                <li><a class="nav-link <?php echo navIsActive('home'); ?>" href="<?php echo public_url(); ?>">Home</a></li>
                <li><a class="nav-link <?php echo navIsActive('services'); ?>" href="<?php echo public_url('services'); ?>">Our Services</a></li>
                <li><a class="nav-link <?php echo navIsActive('about'); ?>" href="<?php echo public_url('about'); ?>">About Us</a></li>
                <li><a class="nav-link <?php echo navIsActive('gallery'); ?>" href="<?php echo public_url('gallery'); ?>">Gallery</a></li>
                <li><a class="nav-link <?php echo navIsActive('blogs'); ?>" href="<?php echo public_url('blogs/'); ?>">Blog <span class="nav-pill">New</span></a></li>

                <!-- Store Dropdown -->
                <!--li class="nav-item-dropdown">
                    <a class="nav-link <?php echo navIsActive('store'); ?>" href="/Kitale/public/store/">
                        <i class="fas fa-store nav-ic" aria-hidden="true"></i> Store
                        <i class="fas fa-chevron-down dropdown-arrow" aria-hidden="true"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-link" href="/Kitale/public/store/"><i class="fas fa-box"></i> All Products</a></li>
                        <li><a class="dropdown-link" href="/Kitale/public/store/?sort=featured"><i class="fas fa-star"></i> Featured</a></li>
                        <li><a class="dropdown-link" href="/Kitale/public/store/?sort=price_low"><i class="fas fa-arrow-up-wide-short"></i> Price: Low to High</a></li>
                        <li><a class="dropdown-link" href="/Kitale/public/store/?sort=price_high"><i class="fas fa-arrow-down-wide-short"></i> Price: High to Low</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-link" href="/Kitale/public/client/cart/"><i class="fas fa-shopping-cart"></i> My Cart <span class="cart-badge" id="desktopCartCount"></span></a></li>
                    </ul>
                </li-->
                
                <li><a class="nav-link <?php echo navIsActive('contact'); ?>" href="<?php echo public_url('contact'); ?>">Contact Us</a></li>
                <li><a class="nav-link" href="<?php echo public_url('auth/login.php'); ?>">Login</a></li>
            </ul>
        </nav>

        <!-- Right-side actions -->
        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle dark mode">
                <i class="fas fa-moon" aria-hidden="true"></i>
            </button>

            <!-- Cart Icon (Mobile/Tablet) -->
            <!--a href="/Modern/public/client/cart/" class="cart-icon-mobile" id="mobileCartIcon">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-mobile" id="mobileCartCount">0</span>
            </a-->

            <?php if (!empty($_SESSION['logged_in'])): ?>
                <div class="user-menu" id="userMenu">
                    <button class="user-trigger" id="userMenuTrigger"
                            aria-expanded="false" aria-haspopup="true" aria-controls="userDropdown">
                        <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? '?', 0, 1)); ?></span>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                        <i class="fas fa-chevron-down user-caret" aria-hidden="true"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown" role="menu">
                        <a class="dropdown-item" href="<?php echo public_url('profile/'); ?>" role="menuitem">
                            <i class="fas fa-user" aria-hidden="true"></i> My Profile
                        </a>
                        <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2): ?>
                        <a class="dropdown-item" href="<?php echo public_url('admin/dashboard.php'); ?>" role="menuitem">
                            <i class="fas fa-gauge-high" aria-hidden="true"></i> Admin Panel
                        </a>
                        <?php endif; ?>
                        <!--a class="dropdown-item" href="/Kitale/public/client/cart/" role="menuitem">
                            <i class="fas fa-shopping-cart" aria-hidden="true"></i> My Cart
                            <span class="cart-badge" id="dropdownCartCount"></span>
                        </a-->
                        <hr class="dropdown-divider">
                        <a class="dropdown-item dropdown-item--danger" href="<?php echo public_url('auth/logout.php'); ?>" role="menuitem">
                            <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo public_url('#contact'); ?>" class="btn btn--primary nav-cta">
                    <i class="far fa-calendar-check" aria-hidden="true"></i> Book Now
                </a>
            <?php endif; ?>

            <button class="hamburger" id="hamburger"
                    aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobileDrawer">
                <span class="ham-bar ham-bar--top" aria-hidden="true"></span>
                <span class="ham-bar ham-bar--mid" aria-hidden="true"></span>
                <span class="ham-bar ham-bar--bot" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</header>

<div class="drawer-backdrop" id="drawerBackdrop" aria-hidden="true"></div>

<aside class="mobile-drawer" id="mobileDrawer" aria-hidden="true" aria-label="Mobile navigation">
    <div class="drawer-head">
        <a class="header-brand drawer-brand" href="<?php echo public_url(); ?>">
            <span class="brand-mark" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M24 4l3.2 4.1 5-1.7 1.1 5.2 5.2 1.1-1.7 5L46 24l-4.1 3.2 1.7 5-5.2 1.1-1.1 5.2-5-1.7L24 44l-3.2-4.1-5 1.7-1.1-5.2-5.2-1.1 1.7-5L2 24l4.1-3.2-1.7-5 5.2-1.1L10.7 9.4l5 1.7L24 4z" fill="var(--brand-primary,#0D9488)"/>
                    <circle cx="24" cy="24" r="9" fill="#fff"/>
                    <text x="24" y="28.5" text-anchor="middle" font-family="Montserrat, sans-serif" font-size="11" font-weight="800" fill="var(--brand-primary,#0D9488)">C</text>
                </svg>
            </span>
            <span class="brand-text">
                <span class="brand-name">DazuTech </span>
                <span class="brand-tag">Software & AI Services</span>
            </span>
        </a>
        <button class="drawer-close" id="drawerClose" aria-label="Close navigation menu">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="drawer-nav" aria-label="Mobile navigation">
        <ul class="drawer-list" role="list">
            <li class="drawer-item" style="--i:0"><a class="drawer-link" href="<?php echo public_url(); ?>"><span>Home</span><i class="fas fa-arrow-right drawer-arrow" aria-hidden="true"></i></a></li>
            <li class="drawer-item" style="--i:1"><a class="drawer-link" href="<?php echo public_url('services'); ?>"><span>Our Services</span><i class="fas fa-arrow-right drawer-arrow" aria-hidden="true"></i></a></li>
            <li class="drawer-item" style="--i:2"><a class="drawer-link" href="<?php echo public_url('about'); ?>"><span>About Us</span><i class="fas fa-arrow-right drawer-arrow" aria-hidden="true"></i></a></li>
            <li class="drawer-item" style="--i:3"><a class="drawer-link" href="<?php echo public_url('gallery'); ?>"><span>Gallery</span><i class="fas fa-arrow-right drawer-arrow" aria-hidden="true"></i></a></li>
            <li class="drawer-item" style="--i:4"><a class="drawer-link" href="<?php echo public_url('blogs/'); ?>"><span>Blog</span><i class="fas fa-arrow-right drawer-arrow" aria-hidden="true"></i></a></li>

            <!-- Store Section in Mobile Drawer -->
            <!--li class="drawer-item drawer-item-parent" style="--i:5">
                <button class="drawer-link drawer-toggle" data-target="storeSubmenu">
                    <span><i class="fas fa-store" aria-hidden="true"></i> Store</span>
                    <i class="fas fa-chevron-down drawer-chevron" aria-hidden="true"></i>
                </button>
                <ul class="drawer-submenu" id="storeSubmenu">
                    <li><a class="drawer-sub-link" href="/Kitale/public/store/"><i class="fas fa-box"></i> All Products</a></li>
                    <li><a class="drawer-sub-link" href="/Kitale/public/store/?sort=featured"><i class="fas fa-star"></i> Featured</a></li>
                    <li><a class="drawer-sub-link" href="/Kitale/public/store/?sort=price_low"><i class="fas fa-arrow-up-wide-short"></i> Price: Low to High</a></li>
                    <li><a class="drawer-sub-link" href="/Kitale/public/store/?sort=price_high"><i class="fas fa-arrow-down-wide-short"></i> Price: High to Low</a></li>
                    <li><hr class="drawer-sub-divider"></li>
                    <li><a class="drawer-sub-link" href="/Kitale/public/client/cart/"><i class="fas fa-shopping-cart"></i> My Cart <span class="cart-badge" id="mobileDrawerCartCount"></span></a></li>
                </ul>
            </li-->

            <li class="drawer-item" style="--i:6"><a class="drawer-link" href="<?php echo public_url('contact'); ?>"><span>Contact Us</span><i class="fas fa-arrow-right drawer-arrow" aria-hidden="true"></i></a></li>
            <li class="drawer-item" style="--i:7"><a class="drawer-link" href="<?php echo public_url('auth/login.php'); ?>"><span>Login</span><i class="fas fa-arrow-right drawer-arrow" aria-hidden="true"></i></a></li>
            <li class="drawer-item" style="--i:8"><a class="drawer-link" href="<?php echo public_url('feedback/'); ?>"><span>Feedback</span><i class="fas fa-arrow-right drawer-arrow" aria-hidden="true"></i></a></li>
        </ul>
    </nav>

    <div class="drawer-foot">
        <?php if (!empty($_SESSION['logged_in'])): ?>
            <div class="drawer-user">
                <div class="drawer-user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? '?', 0, 1)); ?></div>
                <div class="drawer-user-info">
                    <span class="drawer-user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                    <span class="drawer-user-role"><?php echo (isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2) ? 'Administrator' : 'Member'; ?></span>
                </div>
            </div>
            <div class="drawer-foot-links">
                <a href="<?php echo public_url('profile/'); ?>" class="drawer-foot-link"><i class="fas fa-user" aria-hidden="true"></i> My Profile</a>
                <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2): ?>
                <a href="<?php echo public_url('admin/dashboard.php'); ?>" class="drawer-foot-link"><i class="fas fa-gauge-high" aria-hidden="true"></i> Admin Panel</a>
                <?php endif; ?>
                <a href="<?php echo public_url('client/cart/'); ?>" class="drawer-foot-link"><i class="fas fa-shopping-cart" aria-hidden="true"></i> My Cart</a>
                <a href="<?php echo public_url('auth/logout.php'); ?>" class="drawer-foot-link drawer-foot-link--danger"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Logout</a>
            </div>
        <?php else: ?>
            <p class="drawer-foot-tagline">Need precision metalwork or a turnkey fit-out?</p>
            <a href="<?php echo public_url('#contact'); ?>" class="btn btn--primary drawer-cta"><i class="far fa-calendar-check" aria-hidden="true"></i> Book Now</a>
            <a href="tel:<?php echo preg_replace('/\s+/', '', $ismanPhone); ?>" class="drawer-call-link"><i class="fas fa-phone-volume"></i> <?php echo htmlspecialchars($ismanPhone); ?></a>
        <?php endif; ?>
    </div>
</aside>

<style>
/* ============================================================
   NAVBAR — ISMAN Engineering. Uses theme.css tokens; fallbacks safe.
============================================================ */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1001;
    height: 36px;
    display: flex;
    align-items: center;
    background: var(--brand-primary, #0D9488);
    color: #fff;
    font-family: var(--font-body, sans-serif);
    font-size: 0.8rem;
}

.topbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 20px;
}

.topbar-contacts {
    display: flex;
    align-items: center;
    gap: 22px;
    flex-wrap: wrap;
}

.topbar-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #fff;
    opacity: 0.95;
    transition: opacity 0.2s ease;
    text-decoration: none;
}

.topbar-link:hover {
    opacity: 0.75;
}

.topbar-link i {
    font-size: 0.78rem;
}

.topbar-loc {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    opacity: 0.95;
}

.topbar-loc i {
    font-size: 0.78rem;
}

.site-header {
    position: fixed;
    top: 36px;
    left: 0;
    right: 0;
    z-index: 1000;
    height: 80px;
    background: var(--color-surface, #fff);
    border-bottom: 1px solid var(--color-border, #E2EAE8);
    transition: box-shadow 0.3s ease, top 0.3s ease;
}

.site-header.is-scrolled {
    box-shadow: 0 4px 24px rgba(10, 52, 47, 0.08);
}

.header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    height: 100%;
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Brand */
.header-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    text-decoration: none;
}

.brand-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
}

.brand-mark img {
    width: 44px;
    height: 44px;
    object-fit: contain;
}

.brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.05;
}

.brand-name {
    font-family: var(--font-display, sans-serif);
    font-size: 1.18rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    color: var(--color-text-heading, #0A1413);
}

.brand-tag {
    font-family: var(--font-body, sans-serif);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-text-muted, #7B8987);
}

/* Desktop nav */
.header-nav {
    margin-left: auto;
}

.nav-list {
    display: flex;
    align-items: center;
    gap: 2px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-family: var(--font-body, sans-serif);
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-text-body, #44524F);
    padding: 9px 14px;
    border-radius: var(--radius-md, 12px);
    text-decoration: none;
    white-space: nowrap;
    position: relative;
    transition: color 0.2s ease, background 0.2s ease;
}

.nav-link:hover {
    color: var(--brand-primary, #0D9488);
    background: rgba(13, 148, 136, 0.07);
}

.nav-link.is-active {
    color: var(--brand-primary, #0D9488);
    font-weight: 600;
}

.nav-link.is-active::after {
    content: '';
    position: absolute;
    bottom: 3px;
    left: 14px;
    right: 14px;
    height: 2px;
    background: var(--brand-primary, #0D9488);
    border-radius: 2px;
}

.nav-ic {
    font-size: 0.85em;
}

.nav-pill {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #fff;
    background: var(--brand-primary, #0D9488);
    border-radius: var(--radius-full, 9999px);
    padding: 2px 7px;
}

/* Store Dropdown Styles */
.nav-item-dropdown {
    position: relative;
}

.dropdown-arrow {
    font-size: 0.7rem;
    margin-left: 4px;
    transition: transform 0.2s ease;
}

.nav-item-dropdown:hover .dropdown-arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    min-width: 220px;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #E2EAE8);
    border-radius: var(--radius-md, 12px);
    box-shadow: var(--shadow-lg);
    padding: 8px 0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s ease;
    z-index: 100;
    list-style: none;
    margin: 0;
}

.nav-item-dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    font-size: 0.85rem;
    color: var(--color-text-body, #44524F);
    text-decoration: none;
    transition: background 0.2s ease, color 0.2s ease;
}

.dropdown-link:hover {
    background: rgba(13, 148, 136, 0.08);
    color: var(--brand-primary, #0D9488);
}

.dropdown-link i {
    width: 18px;
    font-size: 0.85rem;
    color: var(--color-text-muted, #7B8987);
}

.dropdown-link:hover i {
    color: var(--brand-primary, #0D9488);
}

.dropdown-divider {
    border: none;
    border-top: 1px solid var(--color-border, #E2EAE8);
    margin: 5px 0;
}

.cart-badge {
    display: inline-block;
    background: var(--brand-primary, #0D9488);
    color: #fff;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 20px;
    margin-left: 8px;
    min-width: 20px;
    text-align: center;
}

/* Cart Icon Mobile */
.cart-icon-mobile {
    display: none;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--color-border, #E2EAE8);
    background: transparent;
    border-radius: var(--radius-md, 12px);
    color: var(--color-text-muted, #7B8987);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    text-decoration: none;
}

.cart-icon-mobile:hover {
    color: var(--brand-primary, #0D9488);
    border-color: var(--brand-primary, #0D9488);
}

.cart-count-mobile {
    position: absolute;
    top: -5px;
    right: -8px;
    background: #e74c3c;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 5px;
    border-radius: 20px;
    min-width: 18px;
    text-align: center;
}

/* Actions */
.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.theme-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--color-border, #E2EAE8);
    background: transparent;
    border-radius: var(--radius-md, 12px);
    color: var(--color-text-muted, #7B8987);
    cursor: pointer;
    transition: all 0.2s ease;
}

.theme-toggle:hover {
    color: var(--brand-primary, #0D9488);
    border-color: var(--brand-primary, #0D9488);
}

html[data-theme="dark"] .theme-toggle i::before {
    content: "\f185";
}

/* sun */
.nav-cta {
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.25);
}

/* User dropdown */
.user-menu {
    position: relative;
}

.user-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--color-surface-alt, #F1F6F5);
    border: 1px solid var(--color-border, #E2EAE8);
    border-radius: var(--radius-full, 9999px);
    padding: 5px 12px 5px 5px;
    cursor: pointer;
    font-family: var(--font-body, sans-serif);
    font-size: 0.875rem;
    color: var(--color-text-body, #44524F);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.user-trigger:hover {
    border-color: var(--brand-primary, #0D9488);
    box-shadow: var(--shadow-sm);
}

.user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--brand-primary, #0D9488);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}

.user-name {
    font-weight: 500;
    max-width: 90px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-caret {
    font-size: 0.6rem;
    color: var(--color-text-muted, #7B8987);
    transition: transform 0.2s ease;
}

.user-trigger[aria-expanded="true"] .user-caret {
    transform: rotate(180deg);
}

.user-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 220px;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #E2EAE8);
    border-radius: var(--radius-md, 12px);
    box-shadow: var(--shadow-xl);
    padding: 6px;
    opacity: 0;
    pointer-events: none;
    transform: translateY(-6px) scale(0.97);
    transform-origin: top right;
    transition: opacity 0.2s ease, transform 0.2s ease;
    z-index: 200;
}

.user-dropdown.is-open {
    opacity: 1;
    pointer-events: all;
    transform: translateY(0) scale(1);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: var(--radius-sm, 6px);
    font-size: 0.875rem;
    color: var(--color-text-body, #44524F);
    text-decoration: none;
    transition: background 0.2s ease, color 0.2s ease;
}

.dropdown-item:hover {
    background: rgba(13, 148, 136, 0.08);
    color: var(--brand-primary, #0D9488);
}

.dropdown-item--danger {
    color: #d9534f;
}

.dropdown-item--danger:hover {
    background: #fff5f5;
    color: #c0392b;
}

.dropdown-item i {
    width: 15px;
    text-align: center;
    font-size: 0.85em;
    color: var(--color-text-muted, #7B8987);
}

.dropdown-item--danger i {
    color: #d9534f;
}

.dropdown-divider {
    border: none;
    border-top: 1px solid var(--color-border, #E2EAE8);
    margin: 5px 0;
}

/* Mobile Drawer Submenu */
.drawer-item-parent {
    flex-direction: column;
}

.drawer-toggle {
    cursor: pointer;
    background: none;
    border: none;
    width: 100%;
    text-align: left;
}

.drawer-toggle .drawer-chevron {
    transition: transform 0.3s ease;
    margin-left: auto;
}

.drawer-toggle[aria-expanded="true"] .drawer-chevron {
    transform: rotate(180deg);
}

.drawer-submenu {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: rgba(13, 148, 136, 0.03);
}

.drawer-submenu.open {
    max-height: 400px;
}

.drawer-sub-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px 12px 48px;
    font-size: 0.9rem;
    color: var(--color-text-body, #44524F);
    text-decoration: none;
    border-bottom: 1px solid var(--color-border, #E2EAE8);
    transition: background 0.2s ease, color 0.2s ease;
}

.drawer-sub-link:hover {
    background: rgba(13, 148, 136, 0.08);
    color: var(--brand-primary, #0D9488);
}

.drawer-sub-link i {
    width: 18px;
    font-size: 0.85rem;
}

.drawer-sub-divider {
    margin: 5px 0;
    border-color: var(--color-border, #E2EAE8);
}

/* Hamburger */
.hamburger {
    display: none;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    background: none;
    border: 1px solid var(--color-border, #E2EAE8);
    border-radius: var(--radius-md, 12px);
    cursor: pointer;
    padding: 0;
    flex-direction: column;
    gap: 5px;
    transition: border-color 0.2s ease, background 0.2s ease;
    flex-shrink: 0;
}

.hamburger:hover {
    border-color: var(--brand-primary, #0D9488);
    background: rgba(13, 148, 136, 0.05);
}

.ham-bar {
    display: block;
    width: 18px;
    height: 2px;
    background: var(--color-text-heading, #0A1413);
    border-radius: 2px;
    transition: transform 0.3s ease, opacity 0.3s ease;
    pointer-events: none;
}

.hamburger.is-open .ham-bar--top {
    transform: translateY(7px) rotate(45deg);
}

.hamburger.is-open .ham-bar--mid {
    opacity: 0;
}

.hamburger.is-open .ham-bar--bot {
    transform: translateY(-7px) rotate(-45deg);
}

/* Drawer */
.drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1099;
    background: rgba(6, 52, 47, 0.55);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.35s ease;
}

.drawer-backdrop.is-open {
    opacity: 1;
    pointer-events: all;
}

.mobile-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: min(340px, 85vw);
    z-index: 1100;
    background: var(--color-surface, #fff);
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform 0.38s cubic-bezier(0.32, 0, 0.15, 1);
    box-shadow: -8px 0 40px rgba(6, 52, 47, 0.18);
    overflow-y: auto;
    overscroll-behavior: contain;
}

.mobile-drawer.is-open {
    transform: translateX(0);
}

.drawer-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid var(--color-border, #E2EAE8);
    flex-shrink: 0;
}

.drawer-head .brand-name {
    font-size: 1rem;
}

.drawer-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: var(--color-surface-alt, #F1F6F5);
    border: 1px solid var(--color-border, #E2EAE8);
    border-radius: var(--radius-md, 12px);
    cursor: pointer;
    font-size: 1rem;
    color: var(--color-text-body, #44524F);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.drawer-close:hover {
    background: var(--brand-primary, #0D9488);
    border-color: var(--brand-primary, #0D9488);
    color: #fff;
}

.drawer-nav {
    flex: 1;
    padding: 10px 0;
    overflow-y: auto;
}

.drawer-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.drawer-item {
    opacity: 0;
    transform: translateX(20px);
    transition: opacity 0.3s ease calc(var(--i) * 45ms + 80ms), transform 0.3s ease calc(var(--i) * 45ms + 80ms);
}

.mobile-drawer.is-open .drawer-item {
    opacity: 1;
    transform: translateX(0);
}

.drawer-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 24px;
    font-family: var(--font-display, sans-serif);
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--color-text-heading, #0A1413);
    text-decoration: none;
    border-bottom: 1px solid var(--color-border, #E2EAE8);
    transition: color 0.2s ease, background 0.2s ease;
    gap: 16px;
}

.drawer-link:hover,
.drawer-link:focus-visible {
    color: var(--brand-primary, #0D9488);
    background: rgba(13, 148, 136, 0.05);
}

.drawer-arrow {
    font-size: 0.75rem;
    color: var(--color-text-muted, #7B8987);
    transition: transform 0.2s ease, color 0.2s ease;
    flex-shrink: 0;
}

.drawer-link:hover .drawer-arrow {
    transform: translateX(4px);
    color: var(--brand-primary, #0D9488);
}

.drawer-foot {
    border-top: 1px solid var(--color-border, #E2EAE8);
    padding: 22px;
    background: var(--color-surface-alt, #F1F6F5);
    flex-shrink: 0;
}

.drawer-user {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding: 12px;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #E2EAE8);
    border-radius: var(--radius-md, 12px);
}

.drawer-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--brand-primary, #0D9488);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
    flex-shrink: 0;
}

.drawer-user-info {
    display: flex;
    flex-direction: column;
}

.drawer-user-name {
    font-weight: 600;
    color: var(--color-text-heading, #0A1413);
}

.drawer-user-role {
    font-size: 0.75rem;
    color: var(--color-text-muted, #7B8987);
}

.drawer-foot-links {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.drawer-foot-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: var(--radius-sm, 6px);
    color: var(--color-text-body, #44524F);
    text-decoration: none;
    font-size: 0.875rem;
    transition: background 0.2s ease, color 0.2s ease;
}

.drawer-foot-link i {
    width: 16px;
    text-align: center;
}

.drawer-foot-link:hover {
    background: rgba(13, 148, 136, 0.08);
    color: var(--brand-primary, #0D9488);
}

.drawer-foot-link--danger {
    color: #d9534f;
}

.drawer-foot-link--danger:hover {
    background: #fff5f5;
    color: #c0392b;
}

.drawer-foot-tagline {
    font-size: 0.9rem;
    color: var(--color-text-muted, #7B8987);
    margin-bottom: 14px;
}

.drawer-cta {
    width: 100%;
    justify-content: center;
    margin-bottom: 14px;
}

.drawer-call-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--brand-primary, #0D9488);
    text-decoration: none;
}

/* ============================================================
   RESPONSIVE BREAKPOINTS - FIXED!
============================================================ */
/* Tablet Landscape */
@media (max-width: 1100px) {
    .nav-link {
        padding: 9px 10px;
        font-size: 0.8rem;
    }
    
    .brand-name {
        font-size: 1rem;
    }
    
    .brand-tag {
        font-size: 0.6rem;
    }
}

/* Tablet Portrait - Show Hamburger */
@media (max-width: 992px) {
    .topbar {
        display: flex;
    }
    
    .header-nav {
        display: none;
    }
    
    .nav-cta {
        display: none;
    }
    
    .user-name,
    .user-caret {
        display: none;
    }
    
    .cart-icon-mobile {
        display: inline-flex;
    }
    
    .hamburger {
        display: flex;
    }
    
    .header-actions {
        gap: 8px;
    }
    
    .site-header {
        top: 0;
    }
    
    .header-inner {
        padding: 0 16px;
    }
}

/* Mobile Landscape */
@media (max-width: 768px) {
    .topbar {
        display: none;
    }
    
    .site-header {
        top: 0;
    }
    
    .brand-mark {
        width: 36px;
        height: 36px;
    }
    
    .brand-mark img {
        width: 36px;
        height: 36px;
    }
    
    .brand-name {
        font-size: 0.85rem;
    }
    
    .brand-tag {
        font-size: 0.5rem;
    }
    
    .header-brand {
        gap: 8px;
    }
    
    .theme-toggle,
    .cart-icon-mobile {
        width: 36px;
        height: 36px;
    }
    
    .hamburger {
        width: 36px;
        height: 36px;
    }
    
    .header-inner {
        padding: 0 12px;
        gap: 12px;
    }
    
    .mobile-drawer {
        width: 85vw;
    }
    
    .drawer-link {
        font-size: 1rem;
        padding: 12px 20px;
    }
    
    .drawer-sub-link {
        padding: 10px 20px 10px 40px;
        font-size: 0.85rem;
    }
}

/* Mobile Small */
@media (max-width: 480px) {
    .brand-name {
        font-size: 0.75rem;
    }
    
    .brand-tag {
        font-size: 0.45rem;
    }
    
    .brand-mark {
        width: 32px;
        height: 32px;
    }
    
    .brand-mark img {
        width: 32px;
        height: 32px;
    }
    
    .theme-toggle,
    .cart-icon-mobile,
    .hamburger {
        width: 32px;
        height: 32px;
    }
    
    .header-inner {
        padding: 0 10px;
        gap: 8px;
    }
    
    .user-trigger {
        padding: 3px 8px 3px 3px;
    }
    
    .user-avatar {
        width: 24px;
        height: 24px;
        font-size: 0.7rem;
    }
}

/* Ensure container doesn't overflow */
.container {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
}

/* Fix for body padding when navbar is fixed */
body {
    padding-top: 116px;
}

@media (max-width: 768px) {
    body {
        padding-top: 80px;
    }
}

/* Focus styles for accessibility */
.hamburger:focus-visible,
.drawer-close:focus-visible,
.drawer-link:focus-visible,
.nav-link:focus-visible,
.theme-toggle:focus-visible {
    outline: 2px solid var(--brand-primary, #0D9488);
    outline-offset: 2px;
}
</style>

<script>
(function () {
    'use strict';
    var header      = document.getElementById('siteHeader');
    var hamburger   = document.getElementById('hamburger');
    var drawer      = document.getElementById('mobileDrawer');
    var backdrop    = document.getElementById('drawerBackdrop');
    var closeBtn    = document.getElementById('drawerClose');
    var userTrigger = document.getElementById('userMenuTrigger');
    var userDropdown= document.getElementById('userDropdown');
    var themeToggle = document.getElementById('themeToggle');

    /* Scroll shadow */
    var ticking = false;
    function onScroll(){
        if (!ticking){
            requestAnimationFrame(function(){
                if (header) header.classList.toggle('is-scrolled', window.scrollY > 24);
                ticking = false;
            });
            ticking = true;
        }
    }
    window.addEventListener('scroll', onScroll, { passive:true });
    onScroll();

    /* Dark-mode toggle (persists across pages) */
    try {
        var saved = localStorage.getItem('isman-theme');
        if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch (e) {}
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) { document.documentElement.removeAttribute('data-theme'); }
            else        { document.documentElement.setAttribute('data-theme', 'dark'); }
            try { localStorage.setItem('isman-theme', isDark ? 'light' : 'dark'); } catch (e) {}
        });
    }

    /* Mobile drawer */
    function openDrawer(){
        drawer.classList.add('is-open'); 
        backdrop.classList.add('is-open'); 
        hamburger.classList.add('is-open');
        hamburger.setAttribute('aria-expanded','true');
        drawer.setAttribute('aria-hidden','false'); 
        backdrop.setAttribute('aria-hidden','false');
        document.body.style.overflow='hidden';
        setTimeout(function(){ if (closeBtn) closeBtn.focus(); }, 50);
    }
    
    function closeDrawer(){
        drawer.classList.remove('is-open'); 
        backdrop.classList.remove('is-open'); 
        hamburger.classList.remove('is-open');
        hamburger.setAttribute('aria-expanded','false');
        drawer.setAttribute('aria-hidden','true'); 
        backdrop.setAttribute('aria-hidden','true');
        document.body.style.overflow=''; 
        if (hamburger) hamburger.focus();
    }
    
    if (hamburger) hamburger.addEventListener('click', openDrawer);
    if (closeBtn)  closeBtn.addEventListener('click', closeDrawer);
    if (backdrop)  backdrop.addEventListener('click', closeDrawer);
    
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && drawer && drawer.classList.contains('is-open')) closeDrawer();
    });
    
    if (drawer){
        drawer.querySelectorAll('a:not(.drawer-toggle)').forEach(function(link){
            link.addEventListener('click', function(){ setTimeout(closeDrawer, 150); });
        });
    }

    /* Mobile drawer submenu toggle */
    var drawerToggles = document.querySelectorAll('.drawer-toggle');
    drawerToggles.forEach(function(toggle){
        toggle.addEventListener('click', function(e){
            e.preventDefault();
            var targetId = this.getAttribute('data-target');
            var submenu = document.getElementById(targetId);
            if (submenu) {
                var expanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !expanded);
                submenu.classList.toggle('open');
            }
        });
    });

    /* User dropdown */
    if (userTrigger && userDropdown){
        userTrigger.addEventListener('click', function(e){
            e.stopPropagation();
            var open = userDropdown.classList.toggle('is-open');
            userTrigger.setAttribute('aria-expanded', String(open));
        });
        
        document.addEventListener('click', function(e){
            if (!userTrigger.contains(e.target) && !userDropdown.contains(e.target)){
                userDropdown.classList.remove('is-open');
                userTrigger.setAttribute('aria-expanded','false');
            }
        });
    }

    /* Update cart count */
   /* function updateCartCount() {
        fetch('/Modern/public/api/store/cart/count.php')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                var count = data.count || 0;
                var cartBadges = document.querySelectorAll('.cart-badge, .cart-count-mobile');
                cartBadges.forEach(function(badge) {
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            })
            .catch(function(err) { console.error('Failed to fetch cart count:', err); });
    }
    /* updateCartCount();
    // Update every 30 seconds
    setInterval(updateCartCount, 30000);

    */
})();
</script>
