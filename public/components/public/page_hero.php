<?php
/* ============================================================
   public/components/public/page_hero.php
   Reusable interactive page header.
   Renders: image background + dark-blue brand overlay + a
   cursor-following glow and gold "spark" (the same feel as the
   homepage hero). No breadcrumbs.

   SET THESE VARS BEFORE INCLUDING (all plain text, NOT escaped —
   the component escapes them for you):
     $hero_title    (string, required) — the big H1
     $hero_sub      (string, optional) — one short line under the title
     $hero_desc     (string, optional) — small supporting paragraph
     $hero_eyebrow  (string, optional) — tiny label above the title
     $hero_image    (string, optional) — background image URL (has a default)
     $hero_align    ('left'|'center', optional, default 'left')

   USAGE EXAMPLE:
     <?php
       $hero_eyebrow = 'What we offer';
       $hero_title   = 'Our Services';
       $hero_sub     = 'Creative & technical solutions, end to end';
       $hero_desc    = 'Comprehensive digital solutions tailored to your goals.';
       $hero_image   = 'https://images.unsplash.com/photo-...';
       include __DIR__ . '/../components/public/page_hero.php';
     ?>
   ============================================================ */

$ph_title   = trim((string)($hero_title   ?? ''));
$ph_sub     = trim((string)($hero_sub     ?? ''));
$ph_desc    = trim((string)($hero_desc    ?? ''));
$ph_eyebrow = trim((string)($hero_eyebrow ?? ''));
$ph_center  = (($hero_align ?? 'left') === 'center') ? ' is-center' : '';
$ph_image   = trim((string)($hero_image ?? ''));
if ($ph_image === '') {
    $ph_image = 'https://images.unsplash.com/photo-1551434678-e076c223a692?w=1600&q=80';
}
?>
<section class="ph-hero<?php echo $ph_center; ?>" data-ph aria-label="<?php echo htmlspecialchars($ph_title); ?>">
    <div class="ph-bg" aria-hidden="true">
        <img src="<?php echo htmlspecialchars($ph_image); ?>" alt="" loading="eager">
    </div>
    <div class="ph-overlay" aria-hidden="true"></div>
    <div class="ph-glow"  aria-hidden="true"></div>
    <div class="ph-spark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" fill="#EBA94E" stroke="#EBA94E"
                  stroke-width="0.5" stroke-linejoin="round"/>
        </svg>
    </div>

    <div class="container ph-inner">
        <?php if ($ph_eyebrow !== ''): ?>
            <p class="ph-eyebrow"><?php echo htmlspecialchars($ph_eyebrow); ?></p>
        <?php endif; ?>
        <h1 class="ph-title"><?php echo htmlspecialchars($ph_title); ?></h1>
        <?php if ($ph_sub !== ''): ?>
            <p class="ph-sub"><?php echo htmlspecialchars($ph_sub); ?></p>
        <?php endif; ?>
        <?php if ($ph_desc !== ''): ?>
            <p class="ph-desc"><?php echo htmlspecialchars($ph_desc); ?></p>
        <?php endif; ?>
    </div>
</section>

<style>
/* ── Interactive page hero (brand: royal #0759F8 · sky #00A1F3 · gold #EBA94E) ── */
.ph-hero{
    position:relative; overflow:hidden; isolation:isolate;
    min-height:clamp(360px, 52vh, 520px);
    display:flex; align-items:center;
    background:var(--color-secondary,#081225);
}
.ph-bg{ position:absolute; inset:0; z-index:0; }
.ph-bg img{
    width:100%; height:100%; object-fit:cover; object-position:center;
    transform:scale(1.05);
}
.ph-overlay{
    position:absolute; inset:0; z-index:1;
    background:
        linear-gradient(105deg, rgba(8,18,37,.92) 0%, rgba(8,18,37,.68) 46%, rgba(7,89,248,.30) 100%),
        linear-gradient(to top, rgba(8,18,37,.88) 0%, transparent 58%);
}
/* cursor-following sky-blue glow */
.ph-glow{
    position:absolute; top:0; left:0; z-index:2;
    width:340px; height:340px; border-radius:50%;
    transform:translate(-50%,-50%); pointer-events:none; opacity:0;
    background:radial-gradient(circle, rgba(0,161,243,.18) 0%, rgba(0,161,243,.07) 42%, transparent 70%);
    transition:opacity .35s ease; mix-blend-mode:screen;
}
/* cursor-following gold spark */
.ph-spark{
    position:absolute; top:0; left:0; z-index:6;
    width:30px; height:30px; transform:translate(-50%,-50%);
    pointer-events:none; opacity:0; transition:opacity .25s ease;
}
.ph-spark svg{
    width:100%; height:100%;
    filter:drop-shadow(0 0 6px rgba(235,169,78,.9)) drop-shadow(0 0 14px rgba(235,169,78,.5));
    animation:ph-pulse 2.4s ease infinite;
}
@keyframes ph-pulse{
    0%,100%{ filter:drop-shadow(0 0 5px rgba(235,169,78,.9)) drop-shadow(0 0 12px rgba(235,169,78,.4)); }
    50%    { filter:drop-shadow(0 0 10px rgba(235,169,78,1)) drop-shadow(0 0 24px rgba(235,169,78,.7)); }
}

.ph-inner{ position:relative; z-index:4; width:100%; padding-block:var(--space-12,48px); }
.ph-hero.is-center .ph-inner{ text-align:center; }
.ph-hero.is-center .ph-sub,
.ph-hero.is-center .ph-desc{ margin-inline:auto; }

.ph-eyebrow{
    display:inline-flex; align-items:center; gap:10px;
    font-family:var(--font-body,sans-serif);
    font-size:.75rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase;
    color:var(--brand-accent,#EBA94E); margin-bottom:18px;
}
.ph-eyebrow::before{ content:''; width:26px; height:2px; background:var(--brand-accent,#EBA94E); display:block; }
.ph-hero.is-center .ph-eyebrow{ justify-content:center; }

.ph-title{
    font-family:var(--font-display,'EB Garamond',serif);
    font-weight:800; color:#fff; line-height:1.05; letter-spacing:-0.01em;
    font-size:clamp(2.1rem, 4.6vw, 3.6rem); margin-bottom:14px;
}
.ph-sub{
    font-family:var(--font-body,sans-serif); font-weight:500;
    font-size:clamp(1rem, 1.6vw, 1.2rem); color:#fff; opacity:.94;
    margin-bottom:14px; max-width:620px;
}
.ph-desc{
    font-family:var(--font-body,sans-serif); font-weight:300; line-height:1.8;
    font-size:.95rem; color:rgba(255,255,255,.74); max-width:560px;
}

/* Hide the OS cursor only on precise pointers (desktop). */
@media (hover:hover) and (pointer:fine){
    .ph-hero.ph-cursor{ cursor:none; }
}
/* Touch / small screens: no custom cursor, lighter hero. */
@media (max-width:768px){
    .ph-hero{ min-height:300px; }
    .ph-glow, .ph-spark{ display:none !important; }
}
@media (prefers-reduced-motion:reduce){
    .ph-spark svg{ animation:none; }
    .ph-bg img{ transform:none; }
}
</style>

<script>
(function(){
    if (window.__phHeroInit) return;   /* run the initialiser only once per page */
    window.__phHeroInit = true;

    var fine = window.matchMedia('(hover:hover) and (pointer:fine)').matches;

    document.querySelectorAll('[data-ph]').forEach(function(hero){
        var glow  = hero.querySelector('.ph-glow');
        var spark = hero.querySelector('.ph-spark');
        if (!fine || !glow || !spark) return;   /* touch devices keep the normal cursor */

        hero.classList.add('ph-cursor');
        var mx=0, my=0, sx=0, sy=0, gx=0, gy=0;

        hero.addEventListener('mouseenter', function(){ glow.style.opacity='1'; spark.style.opacity='1'; });
        hero.addEventListener('mouseleave', function(){ glow.style.opacity='0'; spark.style.opacity='0'; });
        hero.addEventListener('mousemove', function(e){
            var r = hero.getBoundingClientRect();
            mx = e.clientX - r.left;
            my = e.clientY - r.top;
        });

        (function tick(){
            sx += (mx - sx) * 0.20;  sy += (my - sy) * 0.20;   /* spark: snappy */
            gx += (mx - gx) * 0.09;  gy += (my - gy) * 0.09;   /* glow: trailing */
            spark.style.transform = 'translate(' + (sx - 15)  + 'px,' + (sy - 15)  + 'px)';
            glow.style.transform  = 'translate(' + (gx - 170) + 'px,' + (gy - 170) + 'px)';
            requestAnimationFrame(tick);
        })();
    });
})();
</script>