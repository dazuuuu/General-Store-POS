<?php
// public/super/settings/index.php — business details shown on receipts:
// name, logo, location, VAT, loyalty, and receipt footer.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SETTINGS_MANAGE);

$pdo = Database::pdo();
$tenantId = TenantContext::tenantId();
$tenantModel = new Models\TenantModel($pdo);
$tenantModel->ensureShopSchema();

$defaultFooter = implode("\n", ReceiptFooter::DEFAULT_LINES);
$defaultPaymentCredentials = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'settings';
    if ($action === 'reset_shop_data') {
        $confirm = trim((string) ($_POST['reset_confirm'] ?? ''));
        $resetGroups = array_values(array_filter(array_map('strval', $_POST['reset_groups'] ?? [])));
        if ($confirm !== 'RESET') {
            $_SESSION['flash']['error'] = 'Type RESET to confirm the shop data reset.';
        } elseif (!$resetGroups) {
            $_SESSION['flash']['error'] = 'Choose at least one data group to reset.';
        } else {
            $reset = (new TenantResetService($pdo))->resetShopData((int) $tenantId, $resetGroups);
            if ($reset['ok']) {
                $count = array_sum($reset['deleted']);
                $_SESSION['flash']['success'] = 'Selected shop data reset complete. Removed ' . number_format($count) . ' record' . ($count === 1 ? '' : 's') . ' while keeping users and receipt settings.';
            } else {
                $_SESSION['flash']['error'] = $reset['error'] ?? 'Could not reset shop data.';
            }
        }
        header('Location: ' . public_url('super/settings/'));
        exit;
    }

    $data = [
        'name'                    => trim($_POST['name'] ?? ReceiptFooter::SHOP_NAME),
        'phone'                   => trim($_POST['phone'] ?? ReceiptFooter::SHOP_PHONE),
        'address'                 => trim($_POST['address'] ?? ReceiptFooter::SHOP_LOCATION),
        'po_box'                  => trim($_POST['po_box'] ?? ReceiptFooter::SHOP_BOX),
        'business_email'          => trim($_POST['business_email'] ?? ReceiptFooter::SHOP_EMAIL),
        'currency'                => trim($_POST['currency'] ?? 'KES'),
        'receipt_footer'          => trim($_POST['receipt_footer'] ?? ''),
        'kra_pin'                 => trim($_POST['kra_pin'] ?? ''),
        'payment_credentials'     => trim($_POST['payment_credentials'] ?? $defaultPaymentCredentials),
        'payment_methods_json'    => json_encode(array_values(array_filter(array_map('strval', $_POST['payment_methods'] ?? []))) ?: PaymentOptions::defaultEnabledMethods()),
        'vat_rate'                => max(0, round((float) ($_POST['vat_rate'] ?? 0), 2)),
        'vat_inclusive'           => !empty($_POST['vat_inclusive']) ? 1 : 0,
        'loyalty_points_per_kes'  => max(0, round((float) ($_POST['loyalty_points_per_kes'] ?? 1), 2)),
        'loyalty_kes_per_point'   => max(0, round((float) ($_POST['loyalty_kes_per_point'] ?? 0.01), 4)),
        'low_stock_alert_enabled' => !empty($_POST['low_stock_alert_enabled']) ? 1 : 0,
    ];

    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $logo = save_tenant_logo($_FILES['logo'], $tenantId);
        if ($logo['ok']) {
            $data['logo_path'] = $logo['path'];
        } else {
            $_SESSION['flash']['error'] = $logo['error'];
        }
    }

    if ($data['name'] === '') {
        $_SESSION['flash']['error'] = 'Business name is required.';
    } else {
        $tenantModel->updateSettings($tenantId, $data);
        if (empty($_SESSION['flash']['error'])) {
            $_SESSION['flash']['success'] = 'Settings updated.';
        }
    }
    header('Location: ' . public_url('super/settings/'));
    exit;
}

function save_tenant_logo(array $file, int $tenantId): array
{
    if ($file['error'] !== UPLOAD_ERR_OK)               return ['ok' => false, 'error' => 'Upload failed.'];
    if ($file['size'] > 2 * 1024 * 1024)                return ['ok' => false, 'error' => 'Logo must be under 2MB.'];
    $info = @getimagesize($file['tmp_name']);
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!$info || !isset($allowed[$info['mime']]))      return ['ok' => false, 'error' => 'Logo must be PNG, JPG or WEBP.'];

    $dir = ROOT_PATH . '/public/uploads/branding';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'tenant_' . $tenantId . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the logo.'];
    }
    return ['ok' => true, 'path' => 'uploads/branding/' . $name];
}

$__tenant = $tenantModel->find($tenantId);
$page_title = 'Settings';

ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Business details</h2>
        <p class="text-muted small mb-3">Shown on every printed and emailed receipt and invoice.</p>
        <form method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label fw-semibold">Business name</label>
            <input type="text" name="name" class="form-control" required
                   value="<?php echo htmlspecialchars($__tenant['name'] ?? ReceiptFooter::SHOP_NAME); ?>">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Phone</label>
              <input type="text" name="phone" class="form-control"
                     value="<?php echo htmlspecialchars($__tenant['phone'] ?? ReceiptFooter::SHOP_PHONE); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Currency</label>
              <input type="text" name="currency" class="form-control" maxlength="8"
                     value="<?php echo htmlspecialchars($__tenant['currency'] ?? 'KES'); ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Business location</label>
            <input type="text" name="address" class="form-control" placeholder="e.g. Kitengela, St. Monica's Rd"
                   value="<?php echo htmlspecialchars($__tenant['address'] ?? ReceiptFooter::SHOP_LOCATION); ?>">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">P.O. Box</label>
              <input type="text" name="po_box" class="form-control" placeholder="e.g. P.O.BOX 631-00610, NAIROBI"
                     value="<?php echo htmlspecialchars($__tenant['po_box'] ?? ReceiptFooter::SHOP_BOX); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Business email</label>
              <input type="email" name="business_email" class="form-control" placeholder="shop@example.com"
                     value="<?php echo htmlspecialchars($__tenant['business_email'] ?? ReceiptFooter::SHOP_EMAIL); ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">KRA PIN <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" name="kra_pin" class="form-control" placeholder="e.g. PA006734580F"
                   value="<?php echo htmlspecialchars($__tenant['kra_pin'] ?? ''); ?>">
            <div class="form-text">Only printed on receipts when you fill this in.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Payment credentials <span class="text-muted fw-normal">(optional)</span></label>
            <textarea name="payment_credentials" class="form-control" rows="4"
                      placeholder="Paybill/Till, bank account, account name, or payment instructions"><?php echo htmlspecialchars($__tenant['payment_credentials'] ?? $defaultPaymentCredentials); ?></textarea>
            <div class="form-text">Shown on emailed invoices and bulk-sale notes so customers know how to pay.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Payment modes (priority order)</label>
            <div class="form-text mb-2">Tick the modes staff can use on the Payments desk. Order below is the display priority.</div>
            <?php
              $allMethods = PaymentOptions::settleMethods();
              $enabledMethods = array_keys(PaymentOptions::enabledSettleMethods($__tenant));
              $i = 0;
              foreach ($allMethods as $mid => $mlabel):
                $checked = in_array($mid, $enabledMethods, true);
            ?>
              <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" name="payment_methods[]" value="<?php echo htmlspecialchars($mid); ?>" id="pm_<?php echo htmlspecialchars($mid); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                <label class="form-check-label" for="pm_<?php echo htmlspecialchars($mid); ?>">
                  <?php echo (++$i) . '. ' . htmlspecialchars($mlabel); ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>

          <hr class="my-4">
          <h3 class="h6 fw-bold mb-3">VAT &amp; pricing</h3>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">VAT rate (%)</label>
              <input type="number" step="0.01" min="0" name="vat_rate" class="form-control"
                     value="<?php echo htmlspecialchars((string) ($__tenant['vat_rate'] ?? '0')); ?>">
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="vat_inclusive" value="1" id="vatInc"
                       <?php echo !empty($__tenant['vat_inclusive']) || !isset($__tenant['vat_inclusive']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="vatInc">Prices include VAT</label>
              </div>
            </div>
          </div>

          <hr class="my-4">
          <h3 class="h6 fw-bold mb-3">Loyalty</h3>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Points per KES spent</label>
              <input type="number" step="0.01" min="0" name="loyalty_points_per_kes" class="form-control"
                     value="<?php echo htmlspecialchars((string) ($__tenant['loyalty_points_per_kes'] ?? '1')); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">KES value per point</label>
              <input type="number" step="0.0001" min="0" name="loyalty_kes_per_point" class="form-control"
                     value="<?php echo htmlspecialchars((string) ($__tenant['loyalty_kes_per_point'] ?? '0.01')); ?>">
            </div>
          </div>

          <hr class="my-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Receipt footer</label>
            <textarea name="receipt_footer" class="form-control" rows="4"
                      placeholder="<?php echo htmlspecialchars($defaultFooter); ?>"><?php echo htmlspecialchars($__tenant['receipt_footer'] ?? $defaultFooter); ?></textarea>
            <div class="form-text">Printed at the bottom of every receipt and invoice. Include your return policy and welcome line.</div>
          </div>
          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" name="low_stock_alert_enabled" value="1" id="lowStock"
                   <?php echo !isset($__tenant['low_stock_alert_enabled']) || !empty($__tenant['low_stock_alert_enabled']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="lowStock">Enable low stock alerts</label>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Logo</label>
            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
            <div class="form-text">Shown on your dashboard, your staff's dashboard, and receipts. PNG/JPG/WEBP, under 2MB.</div>
          </div>
          <button class="btn btn-primary">Save changes</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Data export</h2>
        <p class="text-muted small mb-3">Download Excel files for products, sales, and profit margins by product.</p>
        <form method="get" action="<?php echo public_url('super/data/export.php'); ?>">
          <label class="form-label small fw-semibold">Export file</label>
          <select name="type" class="form-select mb-3">
            <option value="all">All data workbook</option>
            <option value="products">Products only</option>
            <option value="sales">Sales only</option>
            <option value="profit">Profit margins by product</option>
          </select>
          <label class="form-label small fw-semibold">Period</label>
          <select name="period" class="form-select mb-3">
            <option value="all">All time</option>
            <option value="today">Today</option>
            <option value="week">Last 7 days</option>
            <option value="month">Last 30 days</option>
          </select>
          <button class="btn btn-primary w-100"><i class="fas fa-file-excel me-1"></i>Export Excel</button>
        </form>
      </div>
    </div>
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body text-center p-4">
        <div class="text-muted small text-uppercase mb-2">Current logo</div>
        <img src="<?php echo htmlspecialchars(Branding::tenantLogo($__tenant)); ?>"
             alt="Logo" style="max-height:90px;max-width:100%;object-fit:contain;">
        <div class="text-muted small mt-3">This logo is shown on the login screen, your dashboard, and receipts.</div>
      </div>
    </div>
    <div class="card border-danger shadow-sm mt-4" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1 text-danger">Reset shop data</h2>
        <p class="text-muted small mb-3">
          Choose exactly which shop data to clear.
          Users, passwords, admin accounts, business details, logo, and receipt settings are kept.
        </p>
        <form method="post" onsubmit="return confirm('This will permanently delete shop data for this tenant. Continue?');">
          <input type="hidden" name="action" value="reset_shop_data">
          <div class="border rounded-3 p-3 mb-3">
            <?php foreach (TenantResetService::GROUPS as $key => $label): ?>
              <div class="form-check text-start mb-2">
                <input class="form-check-input" type="checkbox" name="reset_groups[]" value="<?php echo htmlspecialchars($key); ?>" id="reset_<?php echo htmlspecialchars($key); ?>">
                <label class="form-check-label small" for="reset_<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></label>
              </div>
            <?php endforeach; ?>
          </div>
          <label class="form-label small fw-semibold">Type RESET to confirm</label>
          <input type="text" name="reset_confirm" class="form-control mb-3" autocomplete="off" placeholder="RESET">
          <button class="btn btn-outline-danger w-100">Reset shop data</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
