<?php
// public/components/tenants/share_modal.php
// Reusable "Share Product Catalogue" modal.
// Expects these variables to be set before including:
//   $catalogueUrl  (string) — full shareable URL
//   $shopName      (string) — tenant's business name
// Also expects Bootstrap 5 JS to already be on the page.
?>
<!-- ========== SHARE CATALOGUE MODAL ========== -->
<div class="modal fade" id="shareCatalogueModal" tabindex="-1" aria-labelledby="shareCatalogueTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden;">

      <!-- Header -->
      <div class="modal-header border-0 px-4 pt-4 pb-2" style="background:linear-gradient(135deg,#0f172a,#1e3a5f);">
        <div>
          <h2 class="h5 mb-1 text-white fw-bold" id="shareCatalogueTitle">
            <i class="fas fa-share-nodes me-2" style="color:#60a5fa;"></i>Share Product Catalogue
          </h2>
          <p class="mb-0 small" style="color:rgba(255,255,255,.6);">
            Let customers browse your products anywhere
          </p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body px-4 py-4">

        <!-- ---- Shareable Link ---- -->
        <div class="mb-4">
          <label class="form-label fw-semibold small text-uppercase" style="letter-spacing:.06em;color:#64748b;">
            <i class="fas fa-link me-1"></i>Shareable Link
          </label>
          <div class="input-group">
            <input type="text" id="shareLinkInput" class="form-control" readonly
                   value="<?php echo htmlspecialchars($catalogueUrl ?? ''); ?>"
                   style="border-radius:10px 0 0 10px;font-size:.82rem;background:#f8fafc;">
            <button class="btn btn-outline-secondary" type="button" id="copyLinkBtn"
                    style="border-radius:0 10px 10px 0;" title="Copy link">
              <i class="fas fa-copy" id="copyIcon"></i>
            </button>
          </div>
          <div id="copySuccess" class="small text-success mt-1" style="display:none;">
            <i class="fas fa-check me-1"></i>Link copied to clipboard!
          </div>
        </div>

        <div class="d-flex align-items-center gap-3 mb-4">
          <hr class="flex-grow-1 m-0">
          <span class="small text-muted fw-semibold">OR SHARE VIA</span>
          <hr class="flex-grow-1 m-0">
        </div>

        <!-- ---- WhatsApp ---- -->
        <div class="mb-4">
          <label class="form-label fw-semibold small text-uppercase" style="letter-spacing:.06em;color:#64748b;">
            <i class="fab fa-whatsapp me-1" style="color:#25d366;"></i>WhatsApp
          </label>
          <div class="d-flex gap-2">
            <div class="input-group">
              <span class="input-group-text" style="font-size:.85rem;background:#f8fafc;">+</span>
              <input type="tel" id="waPhone" class="form-control" placeholder="2547XXXXXXXX (with country code)"
                     style="border-radius:0 10px 10px 0;">
            </div>
            <button type="button" id="waShareBtn" class="btn btn-success flex-shrink-0"
                    style="border-radius:10px;background:#25d366;border-color:#25d366;font-weight:600;white-space:nowrap;">
              <i class="fab fa-whatsapp me-1"></i>Send
            </button>
          </div>
          <small class="text-muted">Or <a href="#" id="waDirectLink" target="_blank" rel="noopener" style="color:#25d366;">open WhatsApp with a pre-written message</a></small>
        </div>

        <!-- ---- Email ---- -->
        <div>
          <label class="form-label fw-semibold small text-uppercase" style="letter-spacing:.06em;color:#64748b;">
            <i class="fas fa-envelope me-1" style="color:#2563eb;"></i>Email
          </label>
          <div class="d-flex gap-2">
            <input type="email" id="shareEmail" class="form-control" placeholder="customer@example.com"
                   style="border-radius:10px;">
            <button type="button" id="emailShareBtn" class="btn btn-primary flex-shrink-0"
                    style="border-radius:10px;font-weight:600;white-space:nowrap;">
              <i class="fas fa-paper-plane me-1"></i>Send
            </button>
          </div>
          <div id="emailFeedback" class="small mt-1" style="display:none;"></div>
        </div>

      </div>
    </div>
  </div>
</div>

<style>
#shareCatalogueModal .modal-content { animation: scaleIn .22s ease; }
@keyframes scaleIn { from { transform: scale(.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
#shareCatalogueModal .input-group-text { border-left:1px solid #dee2e6;border-radius:10px 0 0 10px; }
</style>

<script>
(function () {
    var catalogueUrl = <?php echo json_encode($catalogueUrl ?? ''); ?>;
    var shopName     = <?php echo json_encode($shopName ?? 'Our Shop'); ?>;
    var apiEndpoint  = '/Modern/public/api/share-catalogue.php';

    // ---- Copy link ----
    var copyBtn  = document.getElementById('copyLinkBtn');
    var copyIcon = document.getElementById('copyIcon');
    var copyOk   = document.getElementById('copySuccess');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(catalogueUrl).then(function () {
                copyIcon.className = 'fas fa-check';
                copyBtn.classList.replace('btn-outline-secondary', 'btn-success');
                if (copyOk) { copyOk.style.display = 'block'; }
                setTimeout(function () {
                    copyIcon.className = 'fas fa-copy';
                    copyBtn.classList.replace('btn-success', 'btn-outline-secondary');
                    if (copyOk) { copyOk.style.display = 'none'; }
                }, 2500);
            }).catch(function () {
                // Fallback for older browsers
                var inp = document.getElementById('shareLinkInput');
                if (inp) { inp.select(); document.execCommand('copy'); }
            });
        });
    }

    // ---- WhatsApp direct link (pre-filled) ----
    var waMsg = encodeURIComponent(
        'Hi! Check out our product catalogue from ' + shopName + ':\n' + catalogueUrl
    );
    var waDirectLink = document.getElementById('waDirectLink');
    if (waDirectLink) {
        waDirectLink.href = 'https://wa.me/?text=' + waMsg;
    }

    // ---- WhatsApp with phone number ----
    var waBtn   = document.getElementById('waShareBtn');
    var waPhone = document.getElementById('waPhone');
    if (waBtn && waPhone) {
        waBtn.addEventListener('click', function () {
            var phone = waPhone.value.trim().replace(/\D/g, '');
            if (!phone) { waPhone.focus(); waPhone.classList.add('is-invalid'); return; }
            waPhone.classList.remove('is-invalid');
            window.open('https://wa.me/' + phone + '?text=' + waMsg, '_blank');
        });
        waPhone.addEventListener('input', function () { this.classList.remove('is-invalid'); });
    }

    // ---- Email share ----
    var emailBtn      = document.getElementById('emailShareBtn');
    var emailInput    = document.getElementById('shareEmail');
    var emailFeedback = document.getElementById('emailFeedback');

    function setFeedback(msg, ok) {
        if (!emailFeedback) return;
        emailFeedback.style.display = 'block';
        emailFeedback.className = 'small mt-1 ' + (ok ? 'text-success' : 'text-danger');
        emailFeedback.innerHTML = (ok ? '<i class="fas fa-check me-1"></i>' : '<i class="fas fa-exclamation-circle me-1"></i>') + msg;
    }

    if (emailBtn && emailInput) {
        emailBtn.addEventListener('click', function () {
            var email = emailInput.value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                emailInput.focus(); emailInput.classList.add('is-invalid'); return;
            }
            emailInput.classList.remove('is-invalid');
            emailBtn.disabled = true;
            emailBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending…';
            if (emailFeedback) emailFeedback.style.display = 'none';

            fetch(apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, catalogue_url: catalogueUrl, shop_name: shopName })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                emailBtn.disabled = false;
                emailBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send';
                if (data.ok) {
                    setFeedback('Email sent to ' + email + '!', true);
                    emailInput.value = '';
                } else {
                    setFeedback(data.error || 'Could not send. Please try again.', false);
                }
            })
            .catch(function () {
                emailBtn.disabled = false;
                emailBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send';
                setFeedback('Network error. Please try again.', false);
            });
        });
        emailInput.addEventListener('input', function () { this.classList.remove('is-invalid'); });
    }
})();
</script>
