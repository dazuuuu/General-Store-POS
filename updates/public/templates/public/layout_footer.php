<?php
// public/templates/public/layout_footer.php
// Closing half of the public layout. Include at the end of a page.
?>
<!-- ===== page content ends ===== -->

<?php include __DIR__ . '/../../components/public/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Scroll reveal (matches the rest of the site)
(function () {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
    });
  }, { threshold: 0.08 });
  els.forEach(el => io.observe(el));
})();
</script>
</body>
</html>