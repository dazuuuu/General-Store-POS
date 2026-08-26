<?php
// public/components/public/footer.php
require_once __DIR__ . '/../../../app/helpers/PathConfig.php';
require_once __DIR__ . '/../../../app/helpers/ReceiptFooter.php';
?>
<footer class="site-footer">

    <!-- Top bar -->
    <div class="footer-top">
        <div class="container footer-top-inner">
            <div class="footer-brand">
                <a href="<?php echo public_url(); ?>" class="footer-logo">
                    <svg width="24" height="24" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect width="12" height="12" fill="currentColor"/>
                        <rect x="16" width="12" height="12" fill="currentColor" opacity=".4"/>
                        <rect y="16" width="12" height="12" fill="currentColor" opacity=".4"/>
                        <rect x="16" y="16" width="12" height="12" fill="currentColor"/>
                    </svg>
                    <span>Ismano</span>
                </a>
                <p class="footer-tagline">
                    Creative projects, innovative solutions.<br>
                    Built with craft and intention.
                </p>
                <div class="footer-social">
                    <a href="#" class="social-link" aria-label="X / Twitter">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                </div>
            </div>

            <nav class="footer-col" aria-label="Quick links">
                <h6 class="footer-col-title">Quick links</h6>
                <ul class="footer-links">
                    <li><a href="<?php echo public_url(); ?>">Home</a></li>
                    <li><a href="<?php echo public_url('projects/'); ?>">Projects</a></li>
                    <li><a href="<?php echo public_url('services/'); ?>">Services</a></li>
                    <li><a href="<?php echo public_url('blogs/'); ?>">Blog</a></li>
                    <li><a href="<?php echo public_url('auth/register.php'); ?>">Get started</a></li>
                </ul>
            </nav>

            <nav class="footer-col" aria-label="Services links" id="footerServicesCol">
                <h6 class="footer-col-title">Services</h6>
                <ul class="footer-links" id="footerCategories">
                    <li><span class="footer-placeholder">Loading&hellip;</span></li>
                </ul>
            </nav>

            <div class="footer-col footer-contact">
                <h6 class="footer-col-title">Contact</h6>
                <p class="footer-contact-item">
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:<?php echo htmlspecialchars(ReceiptFooter::SHOP_EMAIL); ?>"><?php echo htmlspecialchars(ReceiptFooter::SHOP_EMAIL); ?></a>
                </p>
                <p class="footer-contact-item">
                    <i class="fas fa-phone"></i>
                    <a href="tel:<?php echo preg_replace('/\D+/', '', ReceiptFooter::SHOP_PHONE); ?>"><?php echo htmlspecialchars(ReceiptFooter::SHOP_PHONE); ?></a>
                </p>
                <a href="<?php echo public_url('auth/register.php'); ?>" class="btn btn--primary footer-cta-btn">
                    Let's Talk <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Bottom bar -->
    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <small>&copy; <?php echo date('Y'); ?> Ismano Portfolio. All rights reserved.</small>
            <div class="footer-bottom-links">
                <a href="#">Terms &amp; Conditions</a>
                <a href="#">Privacy Policy</a>
            </div>
        </div>
    </div>
</footer>

<style>
/* ── Footer ───────────────────────────────────────────────── */
.site-footer {
  background: var(--color-secondary);
  color: rgba(255,255,255,0.75);
  font-family: var(--font-body);
  margin-top: 0;
}

/* Top section */
.footer-top { padding: var(--space-20) 0 var(--space-16); }

.footer-top-inner {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1.5fr;
  gap: var(--space-10);
  align-items: start;
}

/* Brand column */
.footer-logo {
  display: inline-flex;
  align-items: center;
  gap: var(--space-3);
  color: #fff;
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  margin-bottom: var(--space-4);
  transition: opacity var(--transition-fast);
}
.footer-logo:hover { opacity: 0.8; }
.footer-logo svg { flex-shrink: 0; }

.footer-tagline {
  font-size: var(--text-sm);
  line-height: 1.7;
  color: rgba(255,255,255,0.5);
  margin-bottom: var(--space-6);
  max-width: 260px;
}

/* Social links */
.footer-social {
  display: flex;
  gap: var(--space-2);
}
.social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px; height: 36px;
  border-radius: 50%;
  border: 1px solid rgba(255,255,255,0.15);
  color: rgba(255,255,255,0.6);
  font-size: 0.875rem;
  transition: all var(--transition-fast);
}
.social-link:hover {
  border-color: var(--color-accent);
  color: var(--color-accent);
  transform: translateY(-2px);
}

/* Column headings */
.footer-col-title {
  font-family: var(--font-display);
  font-size: var(--text-sm);
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: var(--space-5);
}

/* Nav lists */
.footer-links {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.footer-links a {
  font-size: var(--text-sm);
  color: rgba(255,255,255,0.55);
  transition: color var(--transition-fast);
  position: relative;
}
.footer-links a::after {
  content: '';
  position: absolute;
  bottom: -2px; left: 0;
  height: 1px;
  width: 0;
  background: var(--color-accent);
  transition: width var(--transition-base);
}
.footer-links a:hover { color: #fff; }
.footer-links a:hover::after { width: 100%; }

.footer-placeholder {
  font-size: var(--text-sm);
  color: rgba(255,255,255,0.3);
}

/* Contact column */
.footer-contact-item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  font-size: var(--text-sm);
  color: rgba(255,255,255,0.55);
  margin-bottom: var(--space-3);
}
.footer-contact-item i {
  width: 16px;
  color: var(--color-accent);
  flex-shrink: 0;
}
.footer-contact-item a {
  color: rgba(255,255,255,0.55);
  transition: color var(--transition-fast);
}
.footer-contact-item a:hover { color: #fff; }

.footer-cta-btn {
  margin-top: var(--space-5);
  width: 100%;
  justify-content: center;
}

/* Bottom bar */
.footer-bottom {
  border-top: 1px solid rgba(255,255,255,0.08);
  padding: var(--space-5) 0;
}
.footer-bottom-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  flex-wrap: wrap;
}
.footer-bottom small {
  font-size: var(--text-xs);
  color: rgba(255,255,255,0.35);
}
.footer-bottom-links {
  display: flex;
  gap: var(--space-6);
}
.footer-bottom-links a {
  font-size: var(--text-xs);
  color: rgba(255,255,255,0.35);
  transition: color var(--transition-fast);
}
.footer-bottom-links a:hover { color: rgba(255,255,255,0.7); }

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 1024px) {
  .footer-top-inner { grid-template-columns: 1fr 1fr; }
  .footer-brand { grid-column: 1 / -1; }
}

@media (max-width: 600px) {
  .footer-top-inner { grid-template-columns: 1fr; gap: var(--space-8); }
  .footer-tagline { max-width: 100%; }
  .footer-bottom-inner { flex-direction: column; align-items: flex-start; }
}
</style>

<script>
// Populate footer categories from API
(function () {
  fetch('<?php echo public_url('api/projects.php?action=get_categories'); ?>')
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(data => {
      if (data.success && Array.isArray(data.data)) {
        const list = document.getElementById('footerCategories');
        if (!list) return;
        const items = data.data.slice(0, 6);
        list.innerHTML = items.length
          ? items.map(c =>
              `<li><a href="<?php echo public_url('projects/?category='); ?>${encodeURIComponent(c.category_slug)}">${c.category_name}</a></li>`
            ).join('')
          : '<li><span class="footer-placeholder">No categories yet</span></li>';
      }
    })
    .catch(() => {
      const list = document.getElementById('footerCategories');
      if (list) list.innerHTML = '<li><span class="footer-placeholder">—</span></li>';
    });
})();
</script>
