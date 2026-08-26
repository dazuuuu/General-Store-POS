<?php
// public/staff/orders/new.php — the selling screen: search/browse products,
// build a cart, then either Hold it (save for later, no stock touched) or
// Place Order (opens a real tab and generates the invoice — payment is a
// separate step, done later on Payments by whoever has that permission).
require_once __DIR__ . '/../../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/order_invoice_email.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();

$P  = new Models\ProductModel($pdo);
$C  = new Models\CategoryModel($pdo);
$BA = new Models\BookAttributeModel($pdo);
$HO = new Models\HeldOrderModel($pdo);
$CM = new Models\CustomerModel($pdo);
$products   = $P->sellable();
$categories = $C->all(['type' => 'product'], 'name ASC');
if (!$categories) { $categories = $C->all(['type' => 'subject'], 'name ASC'); }
$brands     = $BA->all(['type' => 'brand'], 'name ASC');
if (!$brands) { $brands = $BA->all(['type' => 'publisher'], 'name ASC'); }
$loyalCustomers = $CM->search('', 200);
$customerSearchUrl = public_url('api/customers/search.php');
$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId()) ?: [];
$isStaffViewer = TenantContext::role() === 'staff';
$ordersViewBase = $isStaffViewer ? public_url('staff/orders/view.php') : public_url('super/orders/view.php');
$heldBase = $isStaffViewer ? public_url('staff/orders/held.php') : public_url('super/orders/held.php');

$error   = '';
$cartJson = '[]';
$customerName = '';
$customerId = 0;
$customerEmail = '';
$customerPhone = '';
$creditDurationDays = 14;
$heldOrderId = 0;

// Resuming a held order? Prefill the cart + customer name.
$resumeId = (int) ($_GET['resume'] ?? 0);
if ($resumeId > 0) {
    $held = $HO->find($resumeId);
    if ($held) {
        $customerName = $held['customer_name'];
        $heldOrderId  = $resumeId;
        $validIds = array_column($products, 'id');
        $productsById = [];
        foreach ($products as $p) {
            $productsById[(int) $p['id']] = $p;
        }
        $cart = [];
        foreach ($HO->items($resumeId) as $it) {
            if ($it['product_id'] && in_array((int) $it['product_id'], $validIds, true)) {
                $product = $productsById[(int) $it['product_id']];
                $priceType = (($it['price_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail';
                $quantity = (float) $it['quantity'];
                $unitsPerPack = max(1, (float) ($product['units_per_pack'] ?? 1));
                $packUnit = trim((string) ($product['pack_unit'] ?? ''));
                $packPrice = ($product['pack_price'] ?? '') !== '' && $product['pack_price'] !== null ? (float) $product['pack_price'] : 0.0;
                if ($priceType === 'wholesale' && $packUnit !== '' && $unitsPerPack > 1 && $packPrice > 0) {
                    $quantity = round($quantity / $unitsPerPack, 2);
                }
                $cart[] = [
                    'product_id' => (int) $it['product_id'],
                    'quantity' => $quantity,
                    'price_type' => $priceType,
                ];
            }
        }
        $cartJson = json_encode($cart);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'checkout';
    $cart = json_decode($_POST['cart'] ?? '[]', true);
    $cartJson = $_POST['cart'] ?? '[]';
    $customerName = $_POST['table_name'] ?? '';
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $creditDurationDays = max(0, (int) ($_POST['credit_duration_days'] ?? 0));
    $heldOrderId = (int) ($_POST['held_order_id'] ?? 0);
    if (!is_array($cart)) { $cart = []; }
    $items = [];
    foreach ($cart as $c) {
        $items[] = [
            'product_id' => (int) ($c['product_id'] ?? 0),
            'quantity' => (float) ($c['quantity'] ?? 0),
            'price_type' => (($c['price_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail',
        ];
    }

    if ($action === 'hold') {
        $res = $HO->hold([
            'customer_name' => $customerName,
            'staff_id'      => TenantContext::userId(),
            'items'         => $items,
        ]);
        if ($res['ok']) {
            // A resumed-then-re-held order becomes a new hold; drop the old one.
            if ($heldOrderId > 0) { $HO->discard($heldOrderId); }
            $_SESSION['flash']['success'] = 'Order held for ' . $customerName . '.';
            header('Location: ' . $heldBase);
            exit;
        }
        $error = $res['errors']['_'] ?? ($res['errors']['customer_name'] ?? 'Could not hold this order.');

    } else { // checkout
        $subtotal = 0.0;
        foreach ($items as $it) {
            $prod = null;
            foreach ($products as $p) { if ((int) $p['id'] === $it['product_id']) { $prod = $p; break; } }
            $lineSaleType = (($it['price_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail';
            if ($prod) { $subtotal += \Pricing::unitPriceForQty($prod, (float) $it['quantity'], $lineSaleType) * $it['quantity']; }
        }
        $discount = min(max(round((float) ($_POST['discount_amount'] ?? 0), 2), 0), round($subtotal, 2));
        $additionalCharges = max(0, round((float) ($_POST['additional_charges'] ?? 0), 2));
        $additionalNote = trim((string) ($_POST['additional_charges_note'] ?? ''));

        $res = (new Models\OrderModel($pdo))->open([
            'table_name'      => $customerName,
            'opened_by'       => TenantContext::userId(),
            'items'           => $items,
            'discount_amount' => $discount,
            'additional_charges' => $additionalCharges,
            'additional_charges_note' => $additionalNote,
            'credit_override_amount' => $_POST['credit_override_amount'] ?? 0,
            'customer_id'     => $customerId,
            'customer_email'  => $customerEmail,
            'customer_phone'  => $customerPhone,
            'credit_duration_days' => $creditDurationDays,
        ]);
        if ($res['ok']) {
            if ($heldOrderId > 0) { $HO->discard($heldOrderId); }
            $opened = (new Models\OrderModel($pdo))->find((int) $res['order_id']);
            $mailNote = '';
            if ($opened) {
                $opened['opened_by_name'] = $_SESSION['username'] ?? '';
                (new Models\NotificationModel($pdo))->creditSaleCreated($opened, TenantContext::userId());
                if (!empty($opened['customer_email'])) {
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
                    $msg = build_order_invoice_email($opened, (new Models\OrderModel($pdo))->items((int) $res['order_id']), $shop);
                    $mailNote = (new MailService())->send($opened['customer_email'], $msg['subject'], $msg['html'], $msg['text'])
                        ? ' Invoice emailed.'
                        : ' Invoice email failed: ' . (MailService::lastError() ?: 'unknown error');
                    if (strpos($mailNote, ' Invoice emailed') === 0) {
                        (new Models\OrderModel($pdo))->markInvoiceSent((int) $res['order_id']);
                    }
                }
            }
            $_SESSION['flash']['success'] = 'Credit sale opened - ' . $res['receipt_number'] . '.' . $mailNote;
            header('Location: ' . $ordersViewBase . '?id=' . $res['order_id']);
            exit;
        }
        $error = $res['errors']['_'] ?? ($res['errors']['table_name'] ?? 'Could not open this tab.');
    }
}

$page_title = 'New credit sale';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$products): ?>
  <div class="alert alert-warning">No products in stock to sell. Ask the owner to record stock first.</div>
<?php else: ?>
<form method="post" id="orderForm">
<input type="hidden" name="action" id="formAction" value="checkout">
<input type="hidden" name="cart" id="cartInput" value="">
<input type="hidden" name="held_order_id" value="<?php echo (int) $heldOrderId; ?>">
<input type="hidden" name="customer_id" id="customerIdInput" value="<?php echo (int) $customerId; ?>">

<div class="pos-grid">
  <div class="pos-main">
    <div class="pos-search">
      <i class="fas fa-magnifying-glass"></i>
      <input type="text" id="search" placeholder="Search products…" autocomplete="off">
    </div>
    <div class="pos-search pos-scan">
      <i class="fas fa-barcode"></i>
      <input type="text" id="barcodeScan" placeholder="Scan a barcode to add it…" autocomplete="off">
    </div>
    <div id="scanMsg" class="small mb-2" style="display:none;"></div>

    <?php $offerCount = count(array_filter($products, fn($p) => !empty($p['on_offer']))); ?>
    <?php if ($offerCount > 0): ?>
      <button type="button" class="pos-offer-banner" id="offerBanner">
        <i class="fas fa-tag me-1"></i><?php echo $offerCount; ?> product<?php echo $offerCount === 1 ? '' : 's'; ?> on offer right now — tap to see them
      </button>
    <?php endif; ?>

    <div class="pos-dim-tabs" id="dimTabs">
      <button type="button" class="pos-dim active" data-dim="category">By category</button>
      <button type="button" class="pos-dim" data-dim="brand">By brand</button>
      <button type="button" class="pos-dim" data-dim="offers">Offers</button>
      <button type="button" class="pos-dim" data-dim="archive">Archive</button>
    </div>

    <div class="pos-cats" id="catRow-category" data-dim-row="category">
      <button type="button" class="pos-cat active" data-cat="">
        <span class="pos-cat-img pos-cat-all"><i class="fas fa-border-all"></i></span>
        <span>All</span>
      </button>
      <?php foreach ($categories as $c): ?>
        <button type="button" class="pos-cat" data-cat="<?php echo (int) $c['id']; ?>">
          <span class="pos-cat-img">
            <?php if (!empty($c['image_path'])): ?>
              <img src="<?php echo htmlspecialchars($c['image_path']); ?>" alt="">
            <?php else: ?>
              <i class="fas fa-tag"></i>
            <?php endif; ?>
          </span>
          <span><?php echo htmlspecialchars($c['name']); ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="pos-cats" id="catRow-brand" data-dim-row="brand" style="display:none;">
      <button type="button" class="pos-cat active" data-cat="">
        <span class="pos-cat-img pos-cat-all"><i class="fas fa-border-all"></i></span>
        <span>All</span>
      </button>
      <?php foreach ($brands as $pub): ?>
        <button type="button" class="pos-cat" data-cat="<?php echo (int) $pub['id']; ?>">
          <span class="pos-cat-img"><i class="fas fa-building"></i></span>
          <span><?php echo htmlspecialchars($pub['name']); ?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="pos-prod-grid" id="productList">
      <?php foreach ($products as $p):
          $price = (float) ($p['retail_price'] ?: $p['selling_price']);
          $wholesale = (float) ($p['wholesale_price'] ?: $price);
          $unitsPerPack = max(1, (float) ($p['units_per_pack'] ?? 1));
          $packUnit = (string) ($p['pack_unit'] ?? '');
          $packPrice = ($p['pack_price'] ?? '') !== '' && $p['pack_price'] !== null ? (float) $p['pack_price'] : 0;
          $colorBits = !empty($p['colors']) ? (is_array($p['colors']) ? $p['colors'] : []) : [];
          $faulty = (float) ($p['faulty_quantity'] ?? 0);
          $sub = implode(' · ', array_filter([
              $p['brand_name'] ?? null,
              $colorBits ? implode('/', $colorBits) : null,
              !empty($p['unit']) && $p['unit'] !== 'piece' ? $p['unit'] : null,
              $faulty > 0 ? ('faulty ' . rtrim(rtrim(number_format($faulty, 2), '0'), '.')) : null,
          ]));
          $label = $p['name'] . ($sub ? " ({$sub})" : '');
      ?>
        <div class="pos-card<?php echo !empty($p['is_archived']) ? ' pos-card-archived' : ''; ?>" data-id="<?php echo (int) $p['id']; ?>" data-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
             data-price="<?php echo $price; ?>" data-wholesale="<?php echo $wholesale; ?>" data-stock="<?php echo (float) $p['quantity']; ?>"
             data-units-per-pack="<?php echo $unitsPerPack; ?>"
             data-pack-unit="<?php echo htmlspecialchars($packUnit, ENT_QUOTES); ?>"
             data-pack-price="<?php echo $packPrice; ?>"
             data-type="product"
             data-category="<?php echo (int) ($p['category_id'] ?? 0); ?>"
             data-brand="<?php echo (int) (($p['brand_id'] ?? 0) ?: ($p['publisher_id'] ?? 0)); ?>"
             data-on-offer="<?php echo !empty($p['on_offer']) ? '1' : '0'; ?>"
             data-archived="<?php echo !empty($p['is_archived']) ? '1' : '0'; ?>"
             data-barcode="<?php echo htmlspecialchars($p['barcode'] ?? '', ENT_QUOTES); ?>">
          <?php if (!empty($p['on_offer'])): ?><span class="pos-ribbon">OFFER</span><?php endif; ?>
          <?php if (!empty($p['is_archived'])): ?><span class="pos-ribbon pos-ribbon-archive">ARCHIVE</span><?php endif; ?>
          <div class="pos-card-img">
            <?php if (!empty($p['image_path'])): ?>
              <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
            <?php else: ?>
              <i class="fas fa-box"></i>
            <?php endif; ?>
          </div>
          <div class="pos-card-name"><?php echo htmlspecialchars($p['name']); ?><?php echo $sub ? '<br><small>' . htmlspecialchars($sub) . '</small>' : ''; ?></div>
          <div class="pos-card-price">
            <?php if (!empty($p['on_offer'])): ?>
              <span class="pos-card-regprice">KES <?php echo number_format((float) $p['regular_price'], 0); ?></span>
              Retail KES <?php echo number_format($price, 0); ?>
            <?php else: ?>
              Retail KES <?php echo number_format($price, 0); ?>
            <?php endif; ?>
            <?php if ($packUnit !== '' && $unitsPerPack > 1 && $packPrice > 0): ?>
              <div class="small text-muted">Wholesale KES <?php echo number_format($packPrice, 0); ?> / <?php echo htmlspecialchars($packUnit); ?> (<?php echo rtrim(rtrim(number_format($unitsPerPack, 2), '0'), '.'); ?> pcs)</div>
            <?php elseif ($wholesale > 0 && abs($wholesale - $price) > 0.001): ?>
              <div class="small text-muted">Wholesale KES <?php echo number_format($wholesale, 0); ?></div>
            <?php endif; ?>
          </div>
          <div class="pos-add-row">
            <button type="button" class="pos-add"><i class="fas fa-cart-plus me-1"></i>Add</button>
            <button type="button" class="pos-add-half" title="Add half unit only">½</button>
          </div>
        </div>
      <?php endforeach; ?>
      <div id="noMatch" class="text-muted small text-center py-4" style="display:none;grid-column:1/-1;"><i class="fas fa-search me-1"></i>No products match.</div>
    </div>
  </div>

  <aside class="pos-side">
    <h2 class="pos-side-title">Sale Details</h2>
    <div class="pos-customer">
      <div class="pos-customer-icon"><i class="fas fa-user"></i></div>
      <input type="text" name="table_name" id="customerName" class="pos-customer-input" placeholder="Customer name"
             value="<?php echo htmlspecialchars($customerName); ?>" autocomplete="off" required>
      <div class="customer-suggest-menu" id="customerSuggestMenu"></div>
    </div>
    <?php if ($loyalCustomers): ?>
    <div class="mb-2">
      <label class="form-label small mb-1">Loyal customer</label>
      <select id="loyalCustomerSelect" class="form-select form-select-sm">
        <option value="">Choose customer / type manually</option>
        <?php foreach ($loyalCustomers as $c): ?>
          <option value="<?php echo (int) $c['id']; ?>"
                  data-name="<?php echo htmlspecialchars($c['name'], ENT_QUOTES); ?>"
                  data-email="<?php echo htmlspecialchars($c['email'] ?? '', ENT_QUOTES); ?>"
                  data-phone="<?php echo htmlspecialchars($c['phone'] ?? '', ENT_QUOTES); ?>"
                  <?php echo $customerId === (int) $c['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($c['name']); ?><?php echo !empty($c['phone']) ? ' - ' . htmlspecialchars($c['phone']) : ''; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="mb-2">
      <label class="form-label small mb-1">Credit duration</label>
      <select name="credit_duration_days" id="creditDurationDays" class="form-select form-select-sm">
        <option value="0" <?php echo $creditDurationDays === 0 ? 'selected' : ''; ?>>No due date</option>
        <?php foreach ([2 => '2 days', 7 => '1 week', 14 => '2 weeks', 30 => '1 month', 45 => '45 days', 60 => '2 months'] as $days => $label): ?>
          <option value="<?php echo $days; ?>" <?php echo $creditDurationDays === $days ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">How long the customer has to clear this invoice.</div>
    </div>

    <div class="mb-2">
      <button type="button" class="btn btn-sm btn-link p-0" id="creditToggle"><i class="fas fa-envelope me-1"></i>Add email/phone to send invoice</button>
      <div id="creditFields" style="display:none;" class="row g-2 mt-1">
        <div class="col-12">
          <input type="email" name="customer_email" id="customerEmail" class="form-control form-control-sm" placeholder="Email (to send the invoice)" value="<?php echo htmlspecialchars($customerEmail); ?>">
        </div>
        <div class="col-12">
          <input type="text" name="customer_phone" id="customerPhone" class="form-control form-control-sm" placeholder="Phone (optional)" value="<?php echo htmlspecialchars($customerPhone); ?>">
        </div>
      </div>
    </div>

    <div class="pos-cart" id="cartRows">
      <div class="text-muted small text-center py-4" id="cartEmpty">Tap a product to add it. Type qty for retail items and/or wholesale packs.</div>
    </div>

    <div class="pos-totals">
      <div class="d-flex justify-content-between align-items-center py-1">
        <span>Tap Add adds to</span>
        <select name="sale_type" id="saleType" class="form-select form-select-sm" style="width:150px;">
          <option value="retail">Retail (items)</option>
          <option value="wholesale">Wholesale (packs)</option>
        </select>
      </div>
      <div class="d-flex justify-content-between"><span>Sub Total</span><span id="subtotalOut">KES 0</span></div>
      <div class="d-flex justify-content-between align-items-center py-1">
        <span>Discount</span>
        <input type="number" step="0.01" min="0" id="discountInput" name="discount_amount" class="form-control form-control-sm" style="width:100px;text-align:right;" placeholder="0" value="0">
      </div>
      <div class="d-flex justify-content-between align-items-center py-1 gap-2">
        <span>Extra charge</span>
        <input type="number" step="0.01" min="0" id="extraChargeInput" name="additional_charges" class="form-control form-control-sm" style="width:100px;text-align:right;" placeholder="0" value="0">
      </div>
      <div class="py-1">
        <input type="text" name="additional_charges_note" id="extraChargeNoteInput" class="form-control form-control-sm" placeholder="Charge note (optional)">
      </div>
      <div class="d-flex justify-content-between pos-total-line"><span>Total</span><span id="totalOut">KES 0</span></div>
    </div>

    <div class="mt-3">
      <label class="form-label small mb-1">Loyal customer credit override</label>
      <input type="number" step="0.01" min="0" name="credit_override_amount" class="form-control form-control-sm" placeholder="Optional higher product limit">
      <div class="form-text">Use only when a trusted customer is allowed above a product's credit limit.</div>
    </div>

    <div class="pos-actions">
      <button type="submit" class="pos-btn pos-btn-outline" id="holdBtn" disabled>Hold Sale</button>
      <button type="submit" class="pos-btn pos-btn-primary" id="checkoutBtn" disabled>Place Order</button>
    </div>
    <div class="text-muted small text-center mt-2">Place Order opens an unpaid invoice — settle it later on Payments.</div>
  </aside>
</div>
</form>

<style>
.pos-grid{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;}
.pos-search{position:relative;margin-bottom:14px;}
.pos-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#b7bac3;}
.pos-search input{width:100%;padding:12px 14px 12px 40px;border:1px solid #eef0f4;border-radius:12px;background:#fff;font-size:.92rem;}
.pos-search input:focus{outline:none;border-color:var(--pos-green);box-shadow:0 0 0 .2rem rgba(22,163,74,.1);}
.pos-scan input{border-color:var(--pos-green-light);background:var(--pos-green-light);}
.pos-scan i{color:var(--pos-green);}
.pos-dim-tabs{display:flex;gap:8px;margin-bottom:12px;}
.pos-dim{border:1px solid #eef0f4;background:#fff;color:#5b6070;border-radius:999px;padding:6px 14px;font-size:.8rem;font-weight:600;}
.pos-dim.active{border-color:var(--pos-green);color:var(--pos-green);background:var(--pos-green-light);}
.pos-cats{display:flex;gap:10px;overflow-x:auto;padding-bottom:8px;margin-bottom:16px;}
.pos-cat{flex:0 0 auto;width:88px;display:flex;flex-direction:column;align-items:center;gap:8px;border:1px solid #eef0f4;background:#fff;border-radius:14px;padding:12px 8px;font-size:.78rem;font-weight:600;color:#5b6070;white-space:nowrap;}
.pos-cat-img{width:44px;height:44px;border-radius:12px;background:#f7f7fb;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#b7bac3;font-size:1.1rem;}
.pos-cat-img img{width:100%;height:100%;object-fit:cover;}
.pos-cat.active{border-color:var(--pos-green);color:var(--pos-green);background:var(--pos-green-light);}
.pos-cat.active .pos-cat-img, .pos-cat.active .pos-cat-all{background:#fff;color:var(--pos-green);}
.pos-prod-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
@media (min-width:520px){ .pos-prod-grid{grid-template-columns:repeat(3,1fr);} }
@media (min-width:900px){ .pos-prod-grid{grid-template-columns:repeat(4,1fr);} }
@media (min-width:1300px){ .pos-prod-grid{grid-template-columns:repeat(5,1fr);} }
.pos-card{background:#fff;border:1px solid #eef0f4;border-radius:14px;padding:14px;text-align:center;transition:box-shadow .15s;}
.pos-card:hover{box-shadow:0 4px 16px rgba(16,24,40,.08);}
.pos-card-img{height:64px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;}
.pos-card-img img{max-height:64px;max-width:100%;object-fit:contain;}
.pos-card-img i{font-size:1.8rem;color:#d7d9df;}
.pos-card-name{font-weight:600;font-size:.85rem;color:#1f2330;margin-bottom:4px;min-height:2.2em;}
.pos-card-name small{color:#9aa0ac;font-weight:400;}
.pos-card-price{color:var(--pos-green);font-weight:700;font-size:.85rem;margin-bottom:10px;}
.pos-card{position:relative;}
.pos-card-regprice{color:#9aa0ac;font-weight:400;text-decoration:line-through;margin-right:5px;font-size:.8em;}
.pos-ribbon{position:absolute;top:8px;left:8px;background:#f59e0b;color:#fff;font-size:.62rem;font-weight:800;letter-spacing:.03em;padding:2px 7px;border-radius:999px;z-index:2;}
.pos-ribbon-archive{left:auto;right:8px;background:#475569;}
.pos-card-archived{opacity:.9;}
.pos-offer-banner{display:block;width:100%;text-align:left;border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:12px;padding:10px 14px;font-size:.85rem;font-weight:600;margin-bottom:14px;cursor:pointer;}
.pos-offer-banner:hover{background:#fef3c7;}
.pos-add{flex:1;border:0;border-radius:10px;background:var(--pos-green);color:#fff;padding:8px 0;font-weight:600;font-size:.82rem;}
.pos-add:hover{background:var(--pos-green-dark);}
.pos-add-row{display:flex;gap:6px;align-items:stretch;}
.pos-add-half{width:44px;border:1px solid var(--pos-green);border-radius:10px;background:#fff;color:var(--pos-green);font-weight:700;font-size:.95rem;line-height:1;}
.pos-add-half:hover{background:var(--pos-green-light, #e8f8ef);}

.pos-side{background:#fff;border:1px solid #eef0f4;border-radius:16px;padding:20px;position:sticky;top:20px;max-height:calc(100vh - 40px);overflow-y:auto;}
.pos-side-title{font-size:1.1rem;font-weight:800;margin-bottom:14px;}
.pos-customer{display:flex;align-items:center;gap:10px;background:#f7f7fb;border-radius:12px;padding:10px 12px;margin-bottom:6px;position:relative;}
.pos-customer-icon{width:34px;height:34px;border-radius:50%;background:var(--pos-green-light);color:var(--pos-green);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.pos-customer-input{border:0;background:transparent;flex:1;font-weight:600;font-size:.9rem;}
.pos-customer-input:focus{outline:none;}
.customer-suggest-menu{position:absolute;left:12px;right:12px;top:calc(100% + 4px);z-index:70;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 12px 28px rgba(15,23,42,.14);display:none;max-height:240px;overflow:auto;}
.customer-suggest-menu.show{display:block;}
.customer-suggest-menu button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:.55rem .7rem;font-size:.85rem;}
.customer-suggest-menu button:hover{background:#f8fafc;}
.customer-suggest-menu .meta{display:block;color:#64748b;font-size:.75rem;margin-top:1px;}
.pos-cart{max-height:320px;overflow-y:auto;margin:14px 0;}
.pos-cart-line{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f3f4f7;}
.pos-cart-line img, .pos-cart-line .ph{width:38px;height:38px;border-radius:8px;object-fit:cover;background:#f3f4f7;display:flex;align-items:center;justify-content:center;color:#d7d9df;flex-shrink:0;margin-top:2px;}
.pos-cart-name{font-weight:600;font-size:.85rem;color:#1f2330;}
.pos-cart-price{color:#9aa0ac;font-size:.76rem;}
.pos-qty{display:flex;align-items:center;gap:6px;}
.pos-qty button{width:24px;height:24px;border-radius:6px;border:1px solid #eef0f4;background:#fff;font-weight:700;line-height:1;}
.pos-qty .pos-half-btn{width:auto;min-width:28px;padding:0 6px;font-size:.78rem;color:var(--pos-green);}
.pos-qty-input{width:72px;height:28px;border:1px solid #eef0f4;border-radius:7px;text-align:center;font-weight:700;font-size:.82rem;}
.pos-dual-qty{display:flex;flex-direction:column;gap:6px;margin-top:6px;}
.pos-dual-row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0;}
.pos-dual-label{font-size:.72rem;font-weight:600;color:#5b6070;min-width:0;flex:1;}
.pos-cart-del{color:#64748b;background:none;border:0;font-size:.85rem;margin-top:4px;}
.pos-totals{border-top:1px dashed #eef0f4;padding-top:12px;font-size:.9rem;color:#5b6070;}
.pos-total-line{font-weight:800;font-size:1.05rem;color:#1f2330;margin-top:6px;}
.pos-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px;}
.pos-btn{border-radius:12px;padding:12px 0;font-weight:700;font-size:.9rem;border:1px solid #eef0f4;}
.pos-btn-outline{background:#fff;color:#5b6070;}
.pos-btn-primary{background:var(--pos-green);border-color:var(--pos-green);color:#fff;}
.pos-btn:disabled{opacity:.5;}
@media (max-width:900px){
  .pos-grid{grid-template-columns:1fr;}
  .pos-side{position:sticky;top:0;order:-1;max-height:46vh;z-index:20;box-shadow:0 6px 16px rgba(16,24,40,.1);margin-bottom:14px;}
}
</style>

<script>
var PRODUCTS = {};
var BARCODES = {};
document.querySelectorAll('.pos-card').forEach(function (el) {
    var img = el.querySelector('.pos-card-img img');
    PRODUCTS[el.dataset.id] = {
        name: el.dataset.name,
        price: parseFloat(el.dataset.price),
        wholesale: parseFloat(el.dataset.wholesale),
        stock: parseFloat(el.dataset.stock),
        unitsPerPack: parseFloat(el.dataset.unitsPerPack) || 1,
        packUnit: el.dataset.packUnit || '',
        packPrice: parseFloat(el.dataset.packPrice) || 0,
        img: img ? img.getAttribute('src') : null
    };
    if (el.dataset.barcode) { BARCODES[el.dataset.barcode] = el.dataset.id; }
});
var cart = {};
try {
    (JSON.parse(<?php echo json_encode($cartJson); ?>) || []).forEach(function (c) {
        var id = String(c.product_id);
        if (!cart[id]) cart[id] = { retail: 0, wholesale: 0 };
        var qty = parseFloat(c.quantity) || 0;
        if (c.price_type === 'wholesale') cart[id].wholesale += qty;
        else cart[id].retail += qty;
    });
} catch (e) {}
function money(n) { return 'KES ' + n.toLocaleString('en-KE', {maximumFractionDigits: 0}); }
function formatHalfQty(n) {
    n = Math.round((parseFloat(n) || 0) * 100) / 100;
    var whole = Math.floor(n + 0.0001);
    var frac = Math.round((n - whole) * 100) / 100;
    if (Math.abs(frac - 0.5) < 0.001) return (whole > 0 ? String(whole) : '') + '½';
    if (Math.abs(frac) < 0.001) return String(whole);
    return String(n);
}
function defaultSaleType() { return document.getElementById('saleType').value === 'wholesale' ? 'wholesale' : 'retail'; }
function ensureCart(id) {
    if (!cart[id]) cart[id] = { retail: 0, wholesale: 0 };
    return cart[id];
}
function isPackProduct(p) { return !!(p && p.packUnit && p.unitsPerPack > 1 && p.packPrice > 0); }
function sellsByPack(p, type) { return type === 'wholesale' && isPackProduct(p); }
function saleStockQty(p, saleQty, type) { return sellsByPack(p, type) ? saleQty * p.unitsPerPack : saleQty; }
function productPrice(p, type) {
    if (sellsByPack(p, type)) return p.packPrice;
    return type === 'wholesale' && p.wholesale > 0 ? p.wholesale : p.price;
}
function wholesaleUnitLabel(p) { return isPackProduct(p) ? p.packUnit : 'item'; }
function stockUsed(id) {
    var p = PRODUCTS[id], c = cart[id];
    if (!p || !c) return 0;
    return (c.retail || 0) + saleStockQty(p, c.wholesale || 0, 'wholesale');
}
function maxRetail(id) {
    var p = PRODUCTS[id], c = ensureCart(id);
    return Math.max(0, Math.round((p.stock - saleStockQty(p, c.wholesale || 0, 'wholesale')) * 100) / 100);
}
function maxWholesale(id) {
    var p = PRODUCTS[id], c = ensureCart(id);
    var left = Math.max(0, p.stock - (c.retail || 0));
    if (isPackProduct(p)) return Math.floor((left / p.unitsPerPack) * 100) / 100;
    return Math.round(left * 100) / 100;
}
function cartHasItems() {
    return Object.keys(cart).some(function (id) {
        return (cart[id].retail || 0) > 0 || (cart[id].wholesale || 0) > 0;
    });
}
function serializeCart() {
    var out = [];
    Object.keys(cart).forEach(function (id) {
        var p = PRODUCTS[id], c = cart[id];
        if (!p || !c) return;
        if ((c.retail || 0) > 0) {
            out.push({ product_id: parseInt(id, 10), quantity: c.retail, price_type: 'retail' });
        }
        if ((c.wholesale || 0) > 0) {
            out.push({
                product_id: parseInt(id, 10),
                quantity: saleStockQty(p, c.wholesale, 'wholesale'),
                price_type: 'wholesale'
            });
        }
    });
    return out;
}
function pruneCart(id) {
    if (!cart[id]) return;
    if ((cart[id].retail || 0) <= 0 && (cart[id].wholesale || 0) <= 0) delete cart[id];
}
function setRetailQty(id, val) {
    var p = PRODUCTS[id]; if (!p) return;
    var c = ensureCart(id);
    val = Math.round((parseFloat(val) || 0) * 100) / 100;
    var max = Math.max(0, Math.round((p.stock - saleStockQty(p, c.wholesale || 0, 'wholesale')) * 100) / 100);
    if (val > max) val = max;
    c.retail = val > 0 ? val : 0;
    pruneCart(id);
    render();
}
function setWholesaleQty(id, val) {
    var p = PRODUCTS[id]; if (!p) return;
    var c = ensureCart(id);
    val = Math.round((parseFloat(val) || 0) * 100) / 100;
    var left = Math.max(0, p.stock - (c.retail || 0));
    var max = isPackProduct(p) ? Math.floor((left / p.unitsPerPack) * 100) / 100 : Math.round(left * 100) / 100;
    if (val > max) val = max;
    c.wholesale = val > 0 ? val : 0;
    pruneCart(id);
    render();
}
function add(id) {
    var p = PRODUCTS[id]; if (!p) return;
    var type = defaultSaleType();
    var c = ensureCart(id);
    if (type === 'wholesale') setWholesaleQty(id, (c.wholesale || 0) + 1);
    else setRetailQty(id, (c.retail || 0) + 1);
}
function addHalf(id) {
    var p = PRODUCTS[id]; if (!p) return;
    var type = defaultSaleType();
    var c = ensureCart(id);
    if (type === 'wholesale') setWholesaleQty(id, (c.wholesale || 0) + 0.5);
    else setRetailQty(id, (c.retail || 0) + 0.5);
}
var CUSTOMER_SEARCH_URL = <?php echo json_encode($customerSearchUrl); ?>;
var loyalSelect = document.getElementById('loyalCustomerSelect');
function fillCustomer(c) {
    document.getElementById('customerIdInput').value = c.id || '';
    document.getElementById('customerName').value = c.name || '';
    document.getElementById('customerEmail').value = c.email || '';
    document.getElementById('customerPhone').value = c.phone || '';
    document.getElementById('creditFields').style.display = 'flex';
    if (loyalSelect) loyalSelect.value = String(c.id || '');
}
function attachCustomerLookup() {
    var input = document.getElementById('customerName');
    var menu = document.getElementById('customerSuggestMenu');
    var hidden = document.getElementById('customerIdInput');
    if (!input || !menu || !hidden) return;
    var timer = null, pickedName = '';
    function hide(){ menu.classList.remove('show'); }
    function render(items) {
        menu.innerHTML = '';
        if (!items.length) { hide(); return; }
        items.forEach(function(c){
            var b = document.createElement('button');
            b.type = 'button';
            b.innerHTML = '<strong>' + (c.name || '') + '</strong><span class="meta">' + [c.phone, c.email, c.company_name].filter(Boolean).join(' · ') + '</span>';
            b.addEventListener('mousedown', function(e){
                e.preventDefault();
                pickedName = c.name || '';
                fillCustomer(c);
                hide();
            });
            menu.appendChild(b);
        });
        menu.classList.add('show');
    }
    input.addEventListener('input', function(){
        if (pickedName && input.value !== pickedName) {
            hidden.value = '';
            pickedName = '';
            if (loyalSelect) loyalSelect.value = '';
        }
        clearTimeout(timer);
        var q = input.value.trim();
        if (!q) { hide(); return; }
        timer = setTimeout(function(){
            fetch(CUSTOMER_SEARCH_URL + '?q=' + encodeURIComponent(q) + '&limit=8')
                .then(function(r){ return r.json(); })
                .then(function(data){ render(data.items || []); })
                .catch(function(){});
        }, 180);
    });
    input.addEventListener('blur', function(){ setTimeout(hide, 160); });
}
if (loyalSelect) {
    loyalSelect.addEventListener('change', function () {
        var opt = loyalSelect.options[loyalSelect.selectedIndex];
        document.getElementById('customerIdInput').value = loyalSelect.value || '';
        if (loyalSelect.value && opt) {
            fillCustomer({id: loyalSelect.value, name: opt.dataset.name || '', email: opt.dataset.email || '', phone: opt.dataset.phone || ''});
        }
    });
    document.getElementById('customerName').addEventListener('input', function () {
        if (!loyalSelect.value) return;
        var opt = loyalSelect.options[loyalSelect.selectedIndex];
        if (opt && this.value !== (opt.dataset.name || '')) {
            loyalSelect.value = '';
            document.getElementById('customerIdInput').value = '';
        }
    });
}
attachCustomerLookup();

function updateTotals() {
    var sub = 0;
    Object.keys(cart).forEach(function (id) {
        var p = PRODUCTS[id], c = cart[id];
        if (!p || !c) return;
        if (c.retail > 0) sub += productPrice(p, 'retail') * c.retail;
        if (c.wholesale > 0) sub += productPrice(p, 'wholesale') * c.wholesale;
    });
    var d = parseFloat(document.getElementById('discountInput').value) || 0;
    if (d < 0) d = 0;
    if (d > sub) d = sub;
    var extra = parseFloat((document.getElementById('extraChargeInput') || {}).value) || 0;
    if (extra < 0) extra = 0;
    document.getElementById('subtotalOut').textContent = money(sub);
    document.getElementById('totalOut').textContent = money(sub - d + extra);
}

function render() {
    var wrap = document.getElementById('cartRows'), ids = Object.keys(cart);
    wrap.innerHTML = ids.length ? '' : '<div class="text-muted small text-center py-4" id="cartEmpty">Tap a product to add it. Type qty for retail items and/or wholesale packs.</div>';
    ids.forEach(function (id) {
        var p = PRODUCTS[id], c = cart[id];
        if (!p || !c) return;
        var retailMax = Math.max(c.retail || 0, maxRetail(id));
        var wholesaleMax = Math.max(c.wholesale || 0, maxWholesale(id));
        var wLabel = wholesaleUnitLabel(p);
        var lineTotal = (c.retail || 0) * productPrice(p, 'retail') + (c.wholesale || 0) * productPrice(p, 'wholesale');
        var line = document.createElement('div');
        line.className = 'pos-cart-line pos-cart-line-dual';
        line.innerHTML =
            (p.img ? '<img src="' + p.img + '">' : '<div class="ph"><i class="fas fa-box"></i></div>')
          + '<div class="flex-grow-1">'
          +   '<div class="pos-cart-name">' + p.name + '</div>'
          +   '<div class="pos-dual-qty">'
          +     '<label class="pos-dual-row"><span class="pos-dual-label">Retail <span class="text-muted">(' + money(productPrice(p, 'retail')) + '/item)</span></span>'
          +       '<span class="pos-qty"><button type="button" data-dec-retail="' + id + '">−</button>'
          +       '<button type="button" class="pos-half-btn" data-half-retail="' + id + '" title="Add half">½</button>'
          +       '<input type="number" step="0.5" min="0" max="' + retailMax + '" class="pos-qty-input" data-retail-qty="' + id + '" value="' + (c.retail || 0) + '" inputmode="decimal">'
          +       '<button type="button" data-inc-retail="' + id + '">+</button></span></label>'
          +     '<label class="pos-dual-row"><span class="pos-dual-label">Wholesale <span class="text-muted">(' + money(productPrice(p, 'wholesale')) + '/' + wLabel + ')</span></span>'
          +       '<span class="pos-qty"><button type="button" data-dec-wholesale="' + id + '">−</button>'
          +       '<button type="button" class="pos-half-btn" data-half-wholesale="' + id + '" title="Add half">½</button>'
          +       '<input type="number" step="0.5" min="0" max="' + wholesaleMax + '" class="pos-qty-input" data-wholesale-qty="' + id + '" value="' + (c.wholesale || 0) + '" inputmode="decimal">'
          +       '<button type="button" data-inc-wholesale="' + id + '">+</button></span></label>'
          +   '</div>'
          +   '<div class="pos-cart-price mt-1">Line ' + money(lineTotal)
          +     (c.retail > 0 && Math.abs((c.retail % 1) - 0.5) < 0.001 ? ' · retail ' + formatHalfQty(c.retail) : '')
          +     (c.wholesale > 0 && Math.abs((c.wholesale % 1) - 0.5) < 0.001 ? ' · wholesale ' + formatHalfQty(c.wholesale) : '')
          +     (isPackProduct(p) ? ' <span class="text-muted small">· stock used ' + stockUsed(id) + ' items</span>' : '') + '</div>'
          + '</div>'
          + '<button type="button" class="pos-cart-del" data-del="' + id + '"><i class="fas fa-trash"></i></button>';
        wrap.appendChild(line);
    });
    var empty = !cartHasItems();
    document.getElementById('holdBtn').disabled = empty;
    document.getElementById('checkoutBtn').disabled = empty;
    document.getElementById('cartInput').value = JSON.stringify(serializeCart());
    updateTotals();
}
function syncTypedQty(input) {
    if (input.dataset.retailQty) {
        setRetailQty(input.dataset.retailQty, input.value);
        return;
    }
    if (input.dataset.wholesaleQty) {
        setWholesaleQty(input.dataset.wholesaleQty, input.value);
    }
}
document.getElementById('discountInput').addEventListener('input', updateTotals);
var extraChargeInput = document.getElementById('extraChargeInput');
if (extraChargeInput) extraChargeInput.addEventListener('input', updateTotals);
document.getElementById('saleType').addEventListener('change', function () {
    // Only controls what Tap Add increments; existing dual quantities stay as typed.
});
document.getElementById('creditToggle').addEventListener('click', function () {
    var box = document.getElementById('creditFields');
    box.style.display = box.style.display === 'none' ? 'flex' : 'none';
});

document.querySelectorAll('.pos-card .pos-add').forEach(function (b) {
    b.addEventListener('click', function () { add(b.closest('.pos-card').dataset.id); });
});
document.querySelectorAll('.pos-card .pos-add-half').forEach(function (b) {
    b.addEventListener('click', function () { addHalf(b.closest('.pos-card').dataset.id); });
});
document.getElementById('cartRows').addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.incRetail) setRetailQty(t.dataset.incRetail, (cart[t.dataset.incRetail] ? cart[t.dataset.incRetail].retail : 0) + 0.5);
    else if (t.dataset.decRetail) setRetailQty(t.dataset.decRetail, (cart[t.dataset.decRetail] ? cart[t.dataset.decRetail].retail : 0) - 0.5);
    else if (t.dataset.halfRetail) setRetailQty(t.dataset.halfRetail, (cart[t.dataset.halfRetail] ? cart[t.dataset.halfRetail].retail : 0) + 0.5);
    else if (t.dataset.incWholesale) setWholesaleQty(t.dataset.incWholesale, (cart[t.dataset.incWholesale] ? cart[t.dataset.incWholesale].wholesale : 0) + 0.5);
    else if (t.dataset.decWholesale) setWholesaleQty(t.dataset.decWholesale, (cart[t.dataset.decWholesale] ? cart[t.dataset.decWholesale].wholesale : 0) - 0.5);
    else if (t.dataset.halfWholesale) setWholesaleQty(t.dataset.halfWholesale, (cart[t.dataset.halfWholesale] ? cart[t.dataset.halfWholesale].wholesale : 0) + 0.5);
    else if (t.dataset.del) { delete cart[t.dataset.del]; render(); }
});
document.getElementById('cartRows').addEventListener('change', function (e) {
    var retail = e.target.closest('[data-retail-qty]');
    if (retail) { setRetailQty(retail.dataset.retailQty, retail.value); return; }
    var wholesale = e.target.closest('[data-wholesale-qty]');
    if (wholesale) setWholesaleQty(wholesale.dataset.wholesaleQty, wholesale.value);
});
document.getElementById('cartRows').addEventListener('input', function (e) {
    var input = e.target.closest('[data-retail-qty], [data-wholesale-qty]');
    if (!input) return;
    var id = input.dataset.retailQty || input.dataset.wholesaleQty;
    var p = PRODUCTS[id]; if (!p) return;
    var c = ensureCart(id);
    var val = Math.round((parseFloat(input.value) || 0) * 100) / 100;
    if (input.dataset.retailQty) {
        var maxR = Math.max(0, Math.round((p.stock - saleStockQty(p, c.wholesale || 0, 'wholesale')) * 100) / 100);
        if (val > maxR) { val = maxR; input.value = val; }
        c.retail = val > 0 ? val : 0;
    } else {
        var left = Math.max(0, p.stock - (c.retail || 0));
        var maxW = isPackProduct(p) ? Math.floor((left / p.unitsPerPack) * 100) / 100 : Math.round(left * 100) / 100;
        if (val > maxW) { val = maxW; input.value = val; }
        c.wholesale = val > 0 ? val : 0;
    }
    pruneCart(id);
    var empty = !cartHasItems();
    document.getElementById('holdBtn').disabled = empty;
    document.getElementById('checkoutBtn').disabled = empty;
    document.getElementById('cartInput').value = JSON.stringify(serializeCart());
    updateTotals();
});
document.getElementById('cartRows').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.closest('[data-retail-qty], [data-wholesale-qty]')) {
        e.preventDefault();
        e.target.blur();
    }
});

var searchInput = document.getElementById('search');
var activeDim = 'category';
function activeCatFor(dim) {
    var row = document.querySelector('.pos-cats[data-dim-row="' + dim + '"]');
    var btn = row && row.querySelector('.pos-cat.active');
    return btn ? btn.dataset.cat : '';
}
function applyFilters() {
    var q = searchInput.value.toLowerCase().trim();
    var any = false;
    document.querySelectorAll('.pos-card').forEach(function (el) {
        var matchesText = q === '' || el.dataset.name.toLowerCase().indexOf(q) !== -1;
        var matchesDim;
        if (activeDim === 'offers') {
            matchesDim = el.dataset.onOffer === '1';
        } else if (activeDim === 'archive') {
            matchesDim = el.dataset.archived === '1';
        } else {
            var activeCat = activeCatFor(activeDim);
            matchesDim = el.dataset.archived !== '1' && (activeCat === '' || el.dataset[activeDim] === activeCat);
        }
        var show = matchesText && matchesDim;
        el.style.display = show ? '' : 'none';
        if (show) any = true;
    });
    document.getElementById('noMatch').style.display = any ? 'none' : 'block';
}
function selectDim(dim) {
    var tab = document.querySelector('.pos-dim[data-dim="' + dim + '"]');
    if (!tab) return;
    document.querySelectorAll('.pos-dim').forEach(function (x) { x.classList.remove('active'); });
    tab.classList.add('active');
    activeDim = dim;
    document.querySelectorAll('.pos-cats[data-dim-row]').forEach(function (row) {
        row.style.display = row.dataset.dimRow === activeDim ? 'flex' : 'none';
    });
    applyFilters();
}
searchInput.addEventListener('input', applyFilters);
document.querySelectorAll('.pos-cats[data-dim-row]').forEach(function (row) {
    row.addEventListener('click', function (e) {
        var b = e.target.closest('.pos-cat'); if (!b) return;
        row.querySelectorAll('.pos-cat').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        applyFilters();
    });
});
document.getElementById('dimTabs').addEventListener('click', function (e) {
    var b = e.target.closest('.pos-dim'); if (!b) return;
    selectDim(b.dataset.dim);
});
var offerBanner = document.getElementById('offerBanner');
if (offerBanner) { offerBanner.addEventListener('click', function () { selectDim('offers'); }); }

document.getElementById('holdBtn').addEventListener('click', function () { document.getElementById('formAction').value = 'hold'; });
document.getElementById('checkoutBtn').addEventListener('click', function () { document.getElementById('formAction').value = 'checkout'; });

document.getElementById('orderForm').addEventListener('submit', function (e) {
    if (!cartHasItems()) { e.preventDefault(); alert('Add at least one item.'); return; }
    if (!document.getElementById('customerName').value.trim()) { e.preventDefault(); alert('Enter a customer name.'); }
});

var barcodeScan = document.getElementById('barcodeScan');
var scanMsg = document.getElementById('scanMsg');
function flashScan(text, ok) {
    scanMsg.textContent = text;
    scanMsg.style.display = 'block';
    scanMsg.style.color = ok ? 'var(--pos-green-dark, #15803d)' : '#b91c1c';
    setTimeout(function () { scanMsg.style.display = 'none'; }, 2200);
}
if (barcodeScan) {
    barcodeScan.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') { return; }
        e.preventDefault();
        var code = barcodeScan.value.trim();
        barcodeScan.value = '';
        if (!code) { return; }
        var id = BARCODES[code];
        if (!id) { flashScan('No product with that barcode.', false); return; }
        var p = PRODUCTS[id];
        if (p && stockUsed(id) >= p.stock) { flashScan(p.name + ' — no more in stock.', false); return; }
        add(id);
        flashScan((p ? p.name : 'Product') + ' added.', true);
    });
    document.addEventListener('click', function (e) {
        if (e.target === barcodeScan || e.target.closest('input, textarea, button')) { return; }
        barcodeScan.focus();
    });
}

render();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/' . ($isStaffViewer ? 'staff' : 'tenants') . '/layout.php';
