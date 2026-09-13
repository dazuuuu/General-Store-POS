<?php
// public/staff/bulk/index.php — bulk/wholesale credit sale for businesses,
// individuals and companies. Creates an unpaid invoice, deducts stock, then
// can email invoice/delivery/thank-you/remembrance notes from shop settings.
require_once __DIR__ . '/../../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/order_invoice_email.php';
require_once ROOT_PATH . '/app/services/emails/order_delivery_note_email.php';
require_once ROOT_PATH . '/app/services/emails/order_thank_you_email.php';
require_once ROOT_PATH . '/app/services/emails/order_remembrance_email.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$isSuperShop = TenantContext::role() !== 'staff';
$layoutName = $isSuperShop ? 'tenants' : 'staff';
$orderViewBase = $isSuperShop ? public_url('super/orders/view.php') : public_url('staff/orders/view.php');
$P = new Models\ProductModel($pdo);
$O = new Models\OrderModel($pdo);
$tenantModel = new Models\TenantModel($pdo);
$tenantModel->ensureShopSchema();
$tenant = $tenantModel->find(TenantContext::tenantId()) ?: [];
$products = $P->sellable();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerType = in_array($_POST['customer_type'] ?? '', ['business', 'company', 'individual'], true) ? $_POST['customer_type'] : 'business';
    $customerName = trim((string) ($_POST['customer_name'] ?? ''));
    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $email = trim((string) ($_POST['customer_email'] ?? ''));
    $phone = trim((string) ($_POST['customer_phone'] ?? ''));
    $creditDurationDays = max(0, (int) ($_POST['credit_duration_days'] ?? 14));
    $sendDocs = $_POST['send_docs'] ?? [];
    $sendDocs = is_array($sendDocs) ? $sendDocs : [];

    $invoiceName = $businessName !== '' ? $businessName : $customerName;
    if ($invoiceName === '') {
        $error = 'Enter the business, company, or customer name.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address for the invoice.';
    } else {
        $items = [];
        foreach ($_POST['qty'] ?? [] as $productId => $qty) {
            $qty = (float) $qty;
            if ($qty > 0) {
                $items[] = ['product_id' => (int) $productId, 'quantity' => $qty];
            }
        }
        if (!$items) {
            $error = 'Add at least one product quantity.';
        } else {
            $res = $O->open([
                'table_name' => $invoiceName,
                'opened_by' => TenantContext::userId(),
                'items' => $items,
                'channel' => 'tab',
                'sale_type' => 'wholesale',
                'discount_amount' => max(0, round((float) ($_POST['discount_amount'] ?? 0), 2)),
                'credit_override_amount' => $_POST['credit_override_amount'] ?? 0,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'credit_duration_days' => $creditDurationDays,
            ]);

            if (!$res['ok']) {
                $error = $res['errors']['_'] ?? 'Could not create the bulk sale.';
            } else {
                $orderId = (int) $res['order_id'];
                $order = $O->find($orderId);
                $items = $O->items($orderId);
                $shop = [
                    'name' => $tenant['name'] ?? 'the shop',
                    'phone' => $tenant['phone'] ?? '',
                    'po_box' => $tenant['po_box'] ?? '',
                    'email' => $tenant['business_email'] ?? '',
                    'address' => $tenant['address'] ?? '',
                    'kra_pin' => $tenant['kra_pin'] ?? '',
                    'logo' => Branding::tenantLogo($tenant),
                    'payment_credentials' => $tenant['payment_credentials'] ?? '',
                ];
                $mailer = new MailService();
                $sent = [];
                $failed = [];
                $builders = [
                    'invoice' => ['label' => 'Invoice', 'fn' => 'build_order_invoice_email', 'mark' => 'markInvoiceSent'],
                    'delivery' => ['label' => 'Delivery note', 'fn' => 'build_order_delivery_note_email', 'mark' => 'markDeliveryNoteSent'],
                    'thanks' => ['label' => 'Thank-you note', 'fn' => 'build_order_thank_you_email', 'mark' => 'markThankYouSent'],
                    'reminder' => ['label' => 'Remembrance note', 'fn' => 'build_order_remembrance_email', 'mark' => 'markRemembranceSent'],
                ];
                foreach ($builders as $key => $meta) {
                    if (!in_array($key, $sendDocs, true)) { continue; }
                    $msg = $meta['fn']($order, $items, $shop);
                    if ($mailer->send($email, $msg['subject'], $msg['html'], $msg['text'])) {
                        $O->{$meta['mark']}($orderId);
                        $sent[] = $meta['label'];
                    } else {
                        $failed[] = $meta['label'];
                    }
                }
                $notice = 'Bulk invoice opened — ' . $res['receipt_number'] . '.';
                if ($sent) { $notice .= ' Sent: ' . implode(', ', $sent) . '.'; }
                if ($failed) { $notice .= ' Some emails failed: ' . implode(', ', $failed) . ' (' . (MailService::lastError() ?: 'mail error') . ').'; }
                $_SESSION['flash']['success'] = $notice;
                header('Location: ' . $orderViewBase . '?id=' . $orderId);
                exit;
            }
        }
    }
}

$page_title = 'Bulk Sales';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" id="bulkForm">
  <div class="bulk-grid">
    <section class="bulk-main">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-md-4">
          <div class="row g-3">
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Customer type</label>
              <select name="customer_type" class="form-select">
                <option value="business">Business</option>
                <option value="company">Company</option>
                <option value="individual">Individual</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Business / company name</label>
              <input type="text" name="business_name" class="form-control" placeholder="Optional for individuals">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold">Contact person</label>
              <input type="text" name="customer_name" class="form-control" placeholder="Name">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small fw-semibold">Email address</label>
              <input type="email" name="customer_email" class="form-control" required placeholder="customer@email.com">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small fw-semibold">Phone</label>
              <input type="text" name="customer_phone" class="form-control" placeholder="Optional">
            </div>
          </div>
        </div>
      </div>

      <div class="bulk-search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" id="productSearch" placeholder="Search bulk products, offers, opening stock…" autocomplete="off">
      </div>

      <div class="bulk-list">
        <?php foreach ($products as $p):
            $retail = (float) ($p['retail_price'] ?: $p['selling_price']);
            $wholesale = (float) ($p['wholesale_price'] ?: $retail);
            $stock = (float) $p['quantity'];
            $unit = $p['unit'] ?: 'piece';
            $unitsPerPack = max(1, (float) ($p['units_per_pack'] ?? 1));
            $packUnit = (string) ($p['pack_unit'] ?? '');
            $packPrice = ($p['pack_price'] ?? '') !== '' && $p['pack_price'] !== null ? (float) $p['pack_price'] : 0;
            $byPack = $packUnit !== '' && $unitsPerPack > 1 && $packPrice > 0;
            $saleStock = $byPack ? floor(($stock / $unitsPerPack) * 100) / 100 : $stock;
            $salePrice = $byPack ? $packPrice : $wholesale;
            $saleUnit = $byPack ? $packUnit : $unit;
            $onOffer = !empty($p['on_offer']);
        ?>
          <div class="bulk-row" data-name="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . ($p['category_name'] ?? '') . ' ' . ($p['brand_name'] ?? '')), ENT_QUOTES); ?>"
               data-price="<?php echo $salePrice; ?>" data-stock="<?php echo $saleStock; ?>" data-units-per-pack="<?php echo $byPack ? $unitsPerPack : 1; ?>">
            <div class="bulk-item">
              <div class="bulk-img">
                <?php if (!empty($p['image_path'])): ?><img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
                <?php else: ?><i class="fas fa-box"></i><?php endif; ?>
              </div>
              <div>
                <div class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?> <?php if ($onOffer): ?><span class="badge bg-warning text-dark">Offer</span><?php endif; ?></div>
                <div class="text-muted small">Wholesale KES <?php echo number_format($salePrice, 2); ?><?php echo $byPack ? ' / ' . htmlspecialchars($saleUnit) : ''; ?> · stock <?php echo rtrim(rtrim(number_format($saleStock, 2), '0'), '.'); ?> <?php echo htmlspecialchars($saleUnit); ?></div>
              </div>
            </div>
            <div class="bulk-qty">
              <input type="hidden" name="qty[<?php echo (int) $p['id']; ?>]" class="stock-qty" value="">
              <input type="number" min="0" max="<?php echo $saleStock; ?>" step="0.01" class="form-control qty" placeholder="0">
            </div>
            <div class="bulk-line text-end fw-bold">KES 0</div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <aside class="bulk-side">
      <h2 class="h6 fw-bold mb-3">Invoice summary</h2>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted">Items</span><span id="itemCount">0</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span id="subtotalOut">KES 0</span></div>
      <div class="mb-2">
        <label class="form-label small mb-1">Discount</label>
        <input type="number" min="0" step="0.01" name="discount_amount" id="discountInput" class="form-control" value="0">
      </div>
      <div class="mb-3">
        <label class="form-label small mb-1">Loyal customer credit override</label>
        <input type="number" min="0" step="0.01" name="credit_override_amount" class="form-control" placeholder="Optional">
      </div>
      <div class="mb-3">
        <label class="form-label small mb-1">Credit duration</label>
        <select name="credit_duration_days" class="form-select">
          <?php foreach ([2 => '2 days', 7 => '1 week', 14 => '2 weeks', 30 => '1 month', 45 => '45 days', 60 => '2 months'] as $days => $label): ?>
            <option value="<?php echo $days; ?>" <?php echo $days === 14 ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="d-flex justify-content-between fs-5 fw-bold border-top pt-3 mb-3"><span>Total</span><span id="totalOut">KES 0</span></div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Email after creating</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="send_docs[]" value="invoice" checked> Invoice</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="send_docs[]" value="delivery" checked> Delivery note</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="send_docs[]" value="thanks"> Thank-you note</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="send_docs[]" value="reminder"> Remembrance note</label>
      </div>
      <button class="btn btn-primary btn-lg w-100" id="createBtn" disabled><i class="fas fa-file-invoice me-1"></i>Create bulk invoice</button>
      <div class="text-muted small mt-2">Stock is deducted when the invoice is created. Payment is settled later from Payments.</div>
    </aside>
  </div>
</form>

<style>
.bulk-grid{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;}
.bulk-main,.bulk-side{min-width:0;}
.bulk-side{position:sticky;top:20px;background:#fff;border:1px solid #eef0f4;border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(16,24,40,.04);}
.bulk-search{position:relative;margin-bottom:12px;}
.bulk-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#b7bac3;}
.bulk-search input{width:100%;padding:12px 14px 12px 40px;border:1px solid #eef0f4;border-radius:12px;background:#fff;}
.bulk-list{display:grid;gap:10px;}
.bulk-row{display:grid;grid-template-columns:1fr 120px 110px;gap:12px;align-items:center;background:#fff;border:1px solid #eef0f4;border-radius:12px;padding:12px;}
.bulk-item{display:flex;align-items:center;gap:12px;min-width:0;}
.bulk-img{width:44px;height:44px;border-radius:10px;background:#f7f7fb;color:#b7bac3;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;}
.bulk-img img{width:100%;height:100%;object-fit:cover;}
.bulk-qty input{text-align:right;}
@media (max-width:900px){
  .bulk-grid{grid-template-columns:1fr;}
  .bulk-side{position:static;order:-1;}
  .bulk-row{grid-template-columns:1fr;}
  .bulk-line{text-align:left!important;}
}
</style>
<script>
(function(){
  var rows = Array.prototype.slice.call(document.querySelectorAll('.bulk-row'));
  var search = document.getElementById('productSearch');
  var discount = document.getElementById('discountInput');
  function money(n){ return 'KES ' + n.toLocaleString('en-KE', {maximumFractionDigits:0}); }
  function refresh(){
    var subtotal = 0, items = 0;
    rows.forEach(function(row){
      var input = row.querySelector('.qty');
      var qty = parseFloat(input.value) || 0;
      var stock = parseFloat(row.dataset.stock) || 0;
      if (qty > stock) { qty = stock; input.value = stock; }
      var line = qty * (parseFloat(row.dataset.price) || 0);
      var stockQty = row.querySelector('.stock-qty');
      if (stockQty) stockQty.value = qty > 0 ? (qty * (parseFloat(row.dataset.unitsPerPack) || 1)).toFixed(2) : '';
      if (qty > 0) items += 1;
      subtotal += line;
      row.querySelector('.bulk-line').textContent = money(line);
    });
    var disc = Math.max(0, parseFloat(discount.value) || 0);
    if (disc > subtotal) { disc = subtotal; discount.value = subtotal.toFixed(2); }
    document.getElementById('itemCount').textContent = items;
    document.getElementById('subtotalOut').textContent = money(subtotal);
    document.getElementById('totalOut').textContent = money(subtotal - disc);
    document.getElementById('createBtn').disabled = items === 0;
  }
  rows.forEach(function(row){ row.querySelector('.qty').addEventListener('input', refresh); });
  discount.addEventListener('input', refresh);
  search.addEventListener('input', function(){
    var q = search.value.toLowerCase().trim();
    rows.forEach(function(row){ row.style.display = !q || row.dataset.name.indexOf(q) !== -1 ? '' : 'none'; });
  });
  refresh();
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/' . $layoutName . '/layout.php';
