<?php
// public/staff/dashboard/index.php — Home: walk-in POS. Build a cart and
// either Hold it, or Checkout → Pay Now (paid immediately, no invoice/tab —
// that's what Orders is for, for customers staying to drink).
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$canSell = TenantContext::can(Capabilities::SALES_RECORD);
$isSuperShop = TenantContext::role() !== 'staff';
$shopUrl = $isSuperShop ? public_url('super/shop/') : public_url('staff/dashboard/');
$receiptBase = $isSuperShop ? public_url('super/orders/receipt.php') : public_url('staff/orders/receipt.php');
$ordersBase = $isSuperShop ? public_url('super/orders/') : public_url('staff/orders/');
$paymentsUrl = $isSuperShop ? public_url('super/payments/') : public_url('staff/payments/');
$bulkUrl = $isSuperShop ? public_url('super/bulk/') : public_url('staff/bulk/');
$documentsUrl = $isSuperShop ? public_url('super/documents/') : public_url('staff/documents/');
$layoutName = $isSuperShop ? 'tenants' : 'staff';

if (!$canSell) {
    // No selling permission — a light landing page instead of the POS screen.
    $__tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
    $page_title = $isSuperShop ? 'Shop' : 'Home';
    $who = $_SESSION['username'] ?? 'there';
    ob_start();
    ?>
    <div class="card border-0 shadow-sm" style="border-radius:16px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Hi <?php echo htmlspecialchars($who); ?></h2>
        <p class="text-muted mb-0">
          You're signed in at <strong><?php echo htmlspecialchars($__tenant['name'] ?? 'your shop'); ?></strong>.
          <?php if (TenantContext::can(Capabilities::PAYMENTS_PROCESS)): ?>
            Use <a href="<?php echo $paymentsUrl; ?>">Payments</a> to settle invoices.
          <?php endif; ?>
        </p>
      </div>
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../../templates/' . $layoutName . '/layout.php';
    exit;
}

$P  = new Models\ProductModel($pdo);
$C  = new Models\CategoryModel($pdo);
$BA = new Models\BookAttributeModel($pdo);
$HO = new Models\HeldOrderModel($pdo);
$OR = new Models\OrderModel($pdo);
$tenantRow = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
(new Models\TenantModel($pdo))->ensureShopSchema();
$tenantRow = (new Models\TenantModel($pdo))->find(TenantContext::tenantId()) ?: $tenantRow;
$vatRate = (float) ($tenantRow['vat_rate'] ?? 0);
$vatInclusive = (int) ($tenantRow['vat_inclusive'] ?? 1) === 1;
$products   = $P->sellable();
$categories = $C->all(['type' => 'product'], 'name ASC');
if (!$categories) { $categories = $C->all(['type' => 'subject'], 'name ASC'); }
$brands     = $BA->all(['type' => 'brand'], 'name ASC');
if (!$brands) { $brands = $BA->all(['type' => 'publisher'], 'name ASC'); }
$customerSearchUrl = public_url('api/customers/search.php');
$cardTypes  = PaymentOptions::cardTypes();
$banks      = PaymentOptions::kenyaBanks();
$saccos     = PaymentOptions::kenyaSaccos();
$byId = [];
foreach ($products as $p) { $byId[(int) $p['id']] = $p; }

$error = '';
$cartJson = '[]';
$customerName = '';
$customerId = 0;
$heldOrderId = 0;

$resumeId = (int) ($_GET['resume'] ?? 0);
if ($resumeId > 0) {
    $held = $HO->find($resumeId);
    if ($held) {
        $customerName = $held['customer_name'];
        $heldOrderId  = $resumeId;
        $cart = [];
        foreach ($HO->items($resumeId) as $it) {
            if ($it['product_id'] && isset($byId[(int) $it['product_id']])) {
                $product = $byId[(int) $it['product_id']];
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
    $action = $_POST['action'] ?? 'pay';
    $cart = json_decode($_POST['cart'] ?? '[]', true);
    $cartJson = $_POST['cart'] ?? '[]';
    $customerName = trim((string) ($_POST['table_name'] ?? ''));
    $customerId = (int) ($_POST['customer_id'] ?? 0);
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
        $res = $HO->hold(['customer_name' => $customerName, 'staff_id' => TenantContext::userId(), 'items' => $items]);
        if ($res['ok']) {
            if ($heldOrderId > 0) { $HO->discard($heldOrderId); }
            $_SESSION['flash']['success'] = 'Sale held' . ($customerName !== '' ? ' for ' . $customerName : '') . '.';
            header('Location: ' . public_url('staff/orders/held.php'));
            exit;
        }
        $error = $res['errors']['_'] ?? ($res['errors']['customer_name'] ?? 'Could not hold this sale.');

    } else { // pay — walk-in, paid immediately
        // Recompute the total server-side from real prices so we can validate
        // cash tendered BEFORE touching stock — a rejected payment shouldn't
        // leave a stray unpaid tab behind.
        $subtotal = 0.0;
        foreach ($items as $it) {
            $prod = $byId[$it['product_id']] ?? null;
            $lineSaleType = (($it['price_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail';
            if ($prod) { $subtotal += Pricing::unitPriceForQty($prod, (float) $it['quantity'], $lineSaleType) * $it['quantity']; }
        }
        $subtotal = round($subtotal, 2);
        // Negotiated discount — clamp so a typo can't produce a negative total.
        $discount = min(max(round((float) ($_POST['discount_amount'] ?? 0), 2), 0), $subtotal);
        $additionalCharges = max(0, round((float) ($_POST['additional_charges'] ?? 0), 2));
        $additionalNote = trim((string) ($_POST['additional_charges_note'] ?? ''));
        $postVatRate = max(0, round((float) ($_POST['vat_rate'] ?? $vatRate), 2));
        $postVatInc = array_key_exists('vat_inclusive', $_POST) ? (bool) (int) $_POST['vat_inclusive'] : $vatInclusive;
        $priced = Pricing::totals($subtotal, $discount, $postVatRate, $postVatInc, $additionalCharges);
        $total = $priced['total'];
        $method = $_POST['payment_method'] ?? '';
        $tendered = round((float) ($_POST['amount_tendered'] ?? 0), 2);
        $cashAmt  = round((float) ($_POST['cash_amount'] ?? 0), 2);
        $mpesaAmt = round((float) ($_POST['mpesa_amount'] ?? 0), 2);
        $provider = trim((string) ($_POST['payment_provider'] ?? ''));
        $accountName = trim((string) ($_POST['payment_account_name'] ?? ''));
        $reference = trim((string) ($_POST['payment_reference'] ?? ''));
        $allowedPay = ['cash', 'mpesa', 'split', 'card', 'bank', 'sacco', 'credit'];

        if (!$items) {
            $error = 'Add at least one item.';
        } elseif (!in_array($method, $allowedPay, true)) {
            $error = 'Choose how the customer paid.';
        } elseif ($method === 'cash' && $tendered + 0.0001 < $total) {
            $error = 'Cash given is less than the total.';
        } elseif ($method === 'split' && abs(($cashAmt + $mpesaAmt) - $total) > 0.01) {
            $error = 'Cash and M-Pesa amounts must add up to the total.';
        } elseif ($method === 'split' && $cashAmt > 0 && $tendered + 0.0001 < $cashAmt) {
            $error = 'Cash given is less than the cash portion.';
        } else {
            $openRes = $OR->open([
                'table_name' => $customerName,
                'opened_by' => TenantContext::userId(),
                'items' => $items,
                'channel' => 'walkin',
                'discount_amount' => $discount,
                'additional_charges' => $additionalCharges,
                'additional_charges_note' => $additionalNote,
                'vat_rate' => $postVatRate,
                'vat_inclusive' => $postVatInc,
                'sale_type' => 'retail',
                'customer_id' => $customerId,
            ]);
            if (!$openRes['ok']) {
                $error = $openRes['errors']['_'] ?? 'Could not record this sale.';
            } else {
                $payRes = $OR->markPaid($openRes['order_id'], [
                    'method' => $method,
                    'cash_amount' => $cashAmt,
                    'mpesa_amount' => $mpesaAmt,
                    'amount_tendered' => $tendered,
                    'provider' => $provider,
                    'account_name' => $accountName,
                    'reference' => $reference,
                ], TenantContext::userId());
                if ($payRes['ok']) {
                    if ($heldOrderId > 0) { $HO->discard($heldOrderId); }
                    if ($isSuperShop) {
                        header('Location: ' . $receiptBase . '?id=' . (int) $openRes['order_id'] . '&print=1&return=shop');
                        exit;
                    }
                    $_SESSION['flash']['sale_success'] = [
                        'receipt' => $openRes['receipt_number'],
                        'order_id' => (int) $openRes['order_id'],
                    ];
                    header('Location: ' . $shopUrl);
                    exit;
                }
                $OR->void((int) $openRes['order_id'], TenantContext::userId());
                $error = $payRes['error'] ?? 'Could not complete the payment. No stock was deducted.';
            }
        }
    }
}

$page_title = $isSuperShop ? 'Shop' : 'Home';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($isSuperShop): ?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-sm btn-outline-primary" href="<?php echo $bulkUrl; ?>"><i class="fas fa-boxes-stacked me-1"></i>Bulk sale</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $ordersBase; ?>"><i class="fas fa-file-invoice-dollar me-1"></i>Credit sales</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $documentsUrl; ?>"><i class="fas fa-file-lines me-1"></i>Documents</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/inventory/'); ?>"><i class="fas fa-warehouse me-1"></i>Inventory</a>
  <?php if (TenantContext::can(Capabilities::STOCK_ENTER)): ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/stationery/new.php'); ?>"><i class="fas fa-box-open me-1"></i>Record product</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/stock/new.php'); ?>"><i class="fas fa-boxes-stacked me-1"></i>Record stock in bulk</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$products): ?>
  <div class="alert alert-warning">No products in stock to sell. Ask the owner to record stock first.</div>
<?php else: ?>
<form method="post" id="orderForm">
<input type="hidden" name="action" id="formAction" value="pay">
<input type="hidden" name="cart" id="cartInput" value="">
<input type="hidden" name="held_order_id" value="<?php echo (int) $heldOrderId; ?>">
<input type="hidden" name="payment_method" id="paymentMethod" value="cash">
<input type="hidden" name="amount_tendered" id="amountTendered" value="">
<input type="hidden" name="cash_amount" id="cashAmount" value="">
<input type="hidden" name="mpesa_amount" id="mpesaAmount" value="">
<input type="hidden" name="payment_provider" id="paymentProvider" value="">
<input type="hidden" name="payment_account_name" id="paymentAccountName" value="">
<input type="hidden" name="payment_reference" id="paymentReference" value="">
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
             data-price="<?php echo $price; ?>" data-wholesale="<?php echo $wholesale; ?>"
             data-stock="<?php echo (float) $p['quantity']; ?>"
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
            <?php if (!empty($p['image_path'])): ?><img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
            <?php else: ?><i class="fas fa-box"></i><?php endif; ?>
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

  <aside class="pos-side" id="posSide">
    <div class="pos-side-head">
      <h2 class="pos-side-title">Sale Details</h2>
      <div class="pos-customer">
        <div class="pos-customer-icon"><i class="fas fa-user"></i></div>
        <input type="text" name="table_name" id="customerName" class="pos-customer-input" value="<?php echo htmlspecialchars($customerName); ?>" autocomplete="off" placeholder="Search customer">
        <div class="customer-suggest-menu" id="customerSuggestMenu"></div>
      </div>
    </div>

    <div class="pos-cart-wrap">
      <div class="pos-cart" id="cartRows"><div class="text-muted small text-center py-4">Tap a product to add it.</div></div>
      <button type="button" class="pos-cart-more" id="cartViewAll" style="display:none;"></button>
    </div>

    <div class="pos-side-foot" id="posSideFoot">
      <div class="pos-totals">
        <div class="d-flex justify-content-between"><span>Sub Total</span><span id="subtotalOut">KES 0</span></div>
        <div class="d-flex justify-content-between align-items-center py-1">
          <span>Discount <span class="text-muted small">(if they negotiate)</span></span>
          <input type="number" step="0.01" min="0" id="discountInput" name="discount_amount" class="form-control form-control-sm" style="width:100px;text-align:right;" placeholder="0" value="0">
        </div>
        <div class="d-flex justify-content-between align-items-center py-1 gap-2">
          <span>Extra charge <span class="text-muted small">(delivery, packing…)</span></span>
          <input type="number" step="0.01" min="0" id="extraChargeInput" name="additional_charges" class="form-control form-control-sm" style="width:100px;text-align:right;" placeholder="0" value="0">
        </div>
        <div class="py-1">
          <input type="text" id="extraChargeNoteInput" name="additional_charges_note" class="form-control form-control-sm" placeholder="Charge note (optional)">
        </div>
        <div class="d-flex justify-content-between align-items-center py-1">
          <span>VAT <span class="text-muted small" id="vatRateLabel"></span></span>
          <span id="vatOut">KES 0</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1">
          <label class="form-check-label small" for="vatEnabledInput">Apply VAT</label>
          <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" id="vatEnabledInput" <?php echo $vatRate > 0 ? 'checked' : ''; ?>>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1">
          <span>Tap Add adds to</span>
          <select name="sale_type" id="saleType" class="form-select form-select-sm" style="width:150px;">
            <option value="retail">Retail (items)</option>
            <option value="wholesale">Wholesale (packs)</option>
          </select>
        </div>
        <input type="hidden" name="vat_rate" id="vatRateInput" value="0">
        <input type="hidden" name="vat_inclusive" id="vatInclusiveInput" value="1">
        <div class="d-flex justify-content-between pos-total-line"><span>Total</span><span id="totalOut">KES 0</span></div>
      </div>

      <div class="pos-actions" id="cartButtons">
        <button type="submit" class="pos-btn pos-btn-outline" id="holdBtn" disabled>Hold Sale</button>
        <button type="button" class="pos-btn pos-btn-primary" id="checkoutBtn" disabled>Checkout</button>
      </div>

      <div id="payPanel" style="display:none;">
        <hr>
        <div class="btn-group w-100 mb-2 flex-wrap" role="group">
          <input type="radio" class="btn-check" name="pm" id="pmCash" value="cash" checked>
          <label class="btn btn-outline-primary btn-sm" for="pmCash"><i class="fas fa-money-bill-wave me-1"></i>Cash</label>
          <input type="radio" class="btn-check" name="pm" id="pmMpesa" value="mpesa">
          <label class="btn btn-outline-success btn-sm" for="pmMpesa"><i class="fas fa-mobile-screen me-1"></i>M-Pesa</label>
          <input type="radio" class="btn-check" name="pm" id="pmCard" value="card">
          <label class="btn btn-outline-dark btn-sm" for="pmCard"><i class="fas fa-credit-card me-1"></i>Card</label>
          <input type="radio" class="btn-check" name="pm" id="pmBank" value="bank">
          <label class="btn btn-outline-secondary btn-sm" for="pmBank"><i class="fas fa-building-columns me-1"></i>Bank</label>
          <input type="radio" class="btn-check" name="pm" id="pmSacco" value="sacco">
          <label class="btn btn-outline-secondary btn-sm" for="pmSacco"><i class="fas fa-landmark me-1"></i>SACCO</label>
          <input type="radio" class="btn-check" name="pm" id="pmSplit" value="split">
          <label class="btn btn-outline-secondary btn-sm" for="pmSplit"><i class="fas fa-divide me-1"></i>Split</label>
        </div>
      <div id="cashBox" class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small mb-1">Cash given</label><input type="number" step="0.01" min="0" id="cashGivenInput" class="form-control form-control-sm"></div>
        <div class="col-6"><label class="form-label small mb-1">Balance</label><div class="form-control form-control-sm bg-light fw-semibold" id="balanceOut">KES 0</div></div>
      </div>
      <div id="splitBox" style="display:none;" class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small mb-1">Cash portion</label><input type="number" step="0.01" min="0" id="cashPortionInput" class="form-control form-control-sm"></div>
        <div class="col-6"><label class="form-label small mb-1">M-Pesa portion</label><input type="number" step="0.01" min="0" id="mpesaPortionInput" class="form-control form-control-sm"></div>
      </div>
      <div id="mpesaBox" style="display:none;" class="row g-2 mb-2">
        <div class="col-12">
          <label class="form-label small mb-1">Name shown on M-Pesa</label>
          <input type="text" id="mpesaNameInput" class="form-control form-control-sm" placeholder="Optional">
        </div>
      </div>
      <div id="cardBox" style="display:none;" class="row g-2 mb-2">
        <div class="col-12">
          <label class="form-label small mb-1">Card type</label>
          <select id="cardTypeInput" class="form-select form-select-sm">
            <option value="">Choose card type</option>
            <?php foreach ($cardTypes as $type): ?><option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div id="bankBox" style="display:none;" class="row g-2 mb-2">
        <div class="col-12">
          <label class="form-label small mb-1">Bank</label>
          <input type="text" id="bankInput" class="form-control form-control-sm" list="kenyaBanks" placeholder="Choose or type bank">
        </div>
      </div>
      <div id="saccoBox" style="display:none;" class="row g-2 mb-2">
        <div class="col-12">
          <label class="form-label small mb-1">SACCO</label>
          <input type="text" id="saccoInput" class="form-control form-control-sm" list="kenyaSaccos" placeholder="Choose or type SACCO">
        </div>
      </div>
      <div id="referenceBox" style="display:none;" class="row g-2 mb-2">
        <div class="col-12">
          <label class="form-label small mb-1">Transaction reference</label>
          <input type="text" id="referenceInput" class="form-control form-control-sm" placeholder="Optional">
        </div>
      </div>
      <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
        <span class="text-muted small">Total Payable</span>
        <span class="fw-bold fs-5" id="payableOut">KES 0</span>
      </div>
      <button type="submit" class="pos-btn pos-btn-primary w-100">Pay Now</button>
      </div>
    </div>
  </aside>
</div>
</form>

<datalist id="kenyaBanks">
  <?php foreach ($banks as $bank): ?><option value="<?php echo htmlspecialchars($bank); ?>"></option><?php endforeach; ?>
</datalist>
<datalist id="kenyaSaccos">
  <?php foreach ($saccos as $sacco): ?><option value="<?php echo htmlspecialchars($sacco); ?>"></option><?php endforeach; ?>
</datalist>

<?php if (!empty($_SESSION['flash']['sale_success'])):
    $saleFlash = $_SESSION['flash']['sale_success'];
    unset($_SESSION['flash']['sale_success']);
    $saleOrderId = (int) ($saleFlash['order_id'] ?? 0);
    $receiptUrl = $receiptBase . '?id=' . $saleOrderId;
    $printReceiptUrl = $receiptUrl . '&print=1&return=shop';
?>
<div class="modal fade" id="saleSuccessModal" tabindex="-1" aria-hidden="true" data-print-url="<?php echo htmlspecialchars($printReceiptUrl, ENT_QUOTES); ?>">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:14px;">
      <div class="modal-body text-center p-4">
        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;border-radius:50%;background:var(--pos-green-light);color:var(--pos-green);"><i class="fas fa-check"></i></div>
        <h2 class="h6 fw-bold mb-1">Sale recorded</h2>
        <p class="text-muted small mb-3"><?php echo htmlspecialchars($saleFlash['receipt'] ?? 'Receipt'); ?></p>
        <a id="printReceiptBtn" class="btn btn-sm btn-primary w-100 mb-2" href="<?php echo htmlspecialchars($printReceiptUrl); ?>" target="_blank" rel="noopener"><i class="fas fa-print me-1"></i>Print receipt</a>
        <a class="btn btn-sm btn-outline-secondary w-100 mb-2" href="<?php echo htmlspecialchars($receiptUrl); ?>" target="_blank" rel="noopener">Open receipt</a>
        <button type="button" class="btn btn-sm btn-success w-100" data-bs-dismiss="modal">Continue selling</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
.pos-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:20px;align-items:start;padding-right:min(380px,38vw);}
.pos-main{min-width:0;}
.pos-search{position:relative;margin-bottom:14px;}
.pos-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#b7bac3;}
.pos-search input{width:100%;padding:12px 14px 12px 40px;border:1px solid #eef0f4;border-radius:12px;background:#fff;font-size:.92rem;}
.pos-search input:focus{outline:none;border-color:var(--pos-green);box-shadow:0 0 0 .2rem rgba(75,0,110,.14);}
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

/* Fixed Sale Details rail — stays visible like the left navbar. */
.pos-side{
  position:fixed; top:0; right:0; bottom:0;
  width:min(360px,38vw); z-index:40;
  background:#fff; border-left:1px solid #eef0f4;
  padding:16px 16px 12px; border-radius:0;
  display:flex; flex-direction:column; gap:0;
  overflow:hidden; box-shadow:-6px 0 24px rgba(16,24,40,.06);
}
.pos-side-head{flex:0 0 auto; padding-bottom:8px;}
.pos-side-title{font-size:1.05rem;font-weight:800;margin:0 0 12px;}
.pos-customer{display:flex;align-items:center;gap:10px;background:#f7f7fb;border-radius:12px;padding:10px 12px;margin-bottom:0;position:relative;}
.pos-customer-icon{width:34px;height:34px;border-radius:50%;background:var(--pos-green-light);color:var(--pos-green);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.pos-customer-input{border:0;background:transparent;flex:1;font-weight:600;font-size:.9rem;}
.pos-customer-input:focus{outline:none;}
.customer-suggest-menu{position:absolute;left:12px;right:12px;top:calc(100% + 4px);z-index:70;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 12px 28px rgba(15,23,42,.14);display:none;max-height:240px;overflow:auto;}
.customer-suggest-menu.show{display:block;}
.customer-suggest-menu button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:.55rem .7rem;font-size:.85rem;}
.customer-suggest-menu button:hover{background:#f8fafc;}
.customer-suggest-menu .meta{display:block;color:#64748b;font-size:.75rem;margin-top:1px;}
.pos-cart-wrap{flex:1 1 auto;min-height:0;display:flex;flex-direction:column;margin:10px 0 8px;border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;}
.pos-cart{flex:1 1 auto;min-height:0;overflow-y:auto;margin:0;padding:6px 2px 8px;-webkit-overflow-scrolling:touch;}
.pos-cart-more{flex:0 0 auto;border:0;background:#f8fafc;color:var(--pos-green);font-weight:700;font-size:.82rem;padding:8px 10px;border-radius:10px;margin:0 0 8px;text-align:left;}
.pos-cart-more:hover{background:var(--pos-green-light);}
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
.pos-side-foot{flex:0 0 auto;max-height:52vh;overflow-y:auto;padding-top:4px;-webkit-overflow-scrolling:touch;}
.pos-side.pay-open .pos-side-foot{max-height:62vh;}
.pos-side.pay-open .pos-cart-wrap{flex:0 1 28%;}
.pos-totals{border-top:0;padding-top:4px;font-size:.9rem;color:#5b6070;}
.pos-total-line{font-weight:800;font-size:1.05rem;color:#1f2330;margin-top:6px;}
.pos-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;position:sticky;bottom:0;background:#fff;padding-top:8px;padding-bottom:4px;z-index:2;}
.pos-btn{border-radius:12px;padding:12px 0;font-weight:700;font-size:.9rem;border:1px solid #eef0f4;}
.pos-btn-outline{background:#fff;color:#5b6070;}
.pos-btn-primary{background:var(--pos-green);border-color:var(--pos-green);color:#fff;}
.pos-btn:disabled{opacity:.5;}
@media (max-width:900px){
  .pos-grid{padding-right:0;padding-bottom:min(48vh,420px);}
  .pos-side{
    top:auto; left:0; right:0; bottom:0;
    width:100%; height:auto; max-height:min(58vh,520px);
    border-left:0; border-top:1px solid #eef0f4;
    border-radius:16px 16px 0 0; z-index:1050;
    box-shadow:0 -8px 28px rgba(16,24,40,.14);
  }
  .pos-side-foot{max-height:none;}
  .pos-side.pay-open{max-height:min(78vh,680px);}
  .pos-side.pay-open .pos-side-foot{max-height:none;flex:1 1 auto;min-height:0;}
  .pos-actions{grid-template-columns:1fr;}
  #payPanel .btn-group{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));}
  #payPanel .btn-group>.btn{border-radius:8px!important;margin:0!important;}
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
var cartExpanded = false;
var CART_PREVIEW_LIMIT = 3;
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
function subtotal() {
    var t = 0;
    Object.keys(cart).forEach(function (id) {
        var p = PRODUCTS[id], c = cart[id];
        if (!p || !c) return;
        if (c.retail > 0) t += productPrice(p, 'retail') * c.retail;
        if (c.wholesale > 0) t += productPrice(p, 'wholesale') * c.wholesale;
    });
    return t;
}
var CUSTOMER_SEARCH_URL = <?php echo json_encode($customerSearchUrl); ?>;
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
                hidden.value = c.id || '';
                pickedName = c.name || '';
                input.value = pickedName;
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
attachCustomerLookup();

function total() {
    var sub = subtotal();
    var d = parseFloat(document.getElementById('discountInput').value) || 0;
    if (d < 0) d = 0;
    if (d > sub) d = sub;
    var extra = parseFloat(document.getElementById('extraChargeInput').value) || 0;
    if (extra < 0) extra = 0;
    var net = sub - d;
    var rate = parseFloat(document.getElementById('vatRateInput').value) || 0;
    var inclusive = document.getElementById('vatInclusiveInput').value === '1';
    var vat = 0;
    if (rate > 0) {
        vat = inclusive ? (net - (net / (1 + rate / 100))) : (net * rate / 100);
        if (!inclusive) net = net + vat;
    }
    return { total: Math.round((net + extra) * 100) / 100, vat: Math.round(vat * 100) / 100, extra: Math.round(extra * 100) / 100 };
}
function updateTotals() {
    var t = total();
    document.getElementById('subtotalOut').textContent = money(subtotal());
    var vatOut = document.getElementById('vatOut');
    if (vatOut) vatOut.textContent = money(t.vat);
    document.getElementById('totalOut').textContent = money(t.total);
    document.getElementById('payableOut').textContent = money(t.total);
    updatePayFields();
}

function render() {
    var wrap = document.getElementById('cartRows'), ids = Object.keys(cart);
    var moreBtn = document.getElementById('cartViewAll');
    wrap.innerHTML = ids.length ? '' : '<div class="text-muted small text-center py-4">Tap a product to add it. Type qty for retail items and/or wholesale packs.</div>';
    var visibleIds = ids;
    if (ids.length > CART_PREVIEW_LIMIT && !cartExpanded) {
        visibleIds = ids.slice(0, CART_PREVIEW_LIMIT);
    }
    visibleIds.forEach(function (id) {
        var p = PRODUCTS[id], c = cart[id];
        if (!p || !c) return;
        var retailMax = Math.max(c.retail || 0, maxRetail(id));
        var wholesaleMax = Math.max(c.wholesale || 0, maxWholesale(id));
        var wLabel = wholesaleUnitLabel(p);
        var lineTotal = (c.retail || 0) * productPrice(p, 'retail') + (c.wholesale || 0) * productPrice(p, 'wholesale');
        var line = document.createElement('div');
        line.className = 'pos-cart-line pos-cart-line-dual';
        line.innerHTML = (p.img ? '<img src="' + p.img + '">' : '<div class="ph"><i class="fas fa-box"></i></div>')
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
    if (moreBtn) {
        if (ids.length > CART_PREVIEW_LIMIT) {
            moreBtn.style.display = 'block';
            moreBtn.textContent = cartExpanded
                ? 'Show fewer items'
                : ('View all details (' + ids.length + ' items)');
        } else {
            moreBtn.style.display = 'none';
            cartExpanded = false;
        }
    }
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
document.getElementById('vatEnabledInput').addEventListener('change', function () {
    var enabled = document.getElementById('vatEnabledInput').checked;
    document.getElementById('vatRateInput').value = enabled ? (window.SHOP_VAT_RATE || 0) : 0;
    updateVatLabel();
    updateTotals();
});

document.querySelectorAll('.pos-card .pos-add').forEach(function (b) { b.addEventListener('click', function () { add(b.closest('.pos-card').dataset.id); }); });
document.querySelectorAll('.pos-card .pos-add-half').forEach(function (b) { b.addEventListener('click', function () { addHalf(b.closest('.pos-card').dataset.id); }); });
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
    // Live update totals while typing without wiping the focused field via full re-render.
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

document.getElementById('holdBtn').addEventListener('click', function () { document.getElementById('formAction').value = 'hold'; document.getElementById('orderForm').submit(); });
var cartViewAll = document.getElementById('cartViewAll');
if (cartViewAll) {
    cartViewAll.addEventListener('click', function () {
        cartExpanded = !cartExpanded;
        render();
        if (cartExpanded) {
            var cartEl = document.getElementById('cartRows');
            if (cartEl) cartEl.scrollTop = 0;
        }
    });
}
document.getElementById('checkoutBtn').addEventListener('click', function () {
    document.getElementById('formAction').value = 'pay';
    document.getElementById('cartButtons').style.display = 'none';
    document.getElementById('payPanel').style.display = 'block';
    var side = document.getElementById('posSide') || document.querySelector('.pos-side');
    if (side) {
        side.classList.add('pay-open');
        var foot = document.getElementById('posSideFoot');
        if (foot) foot.scrollTop = foot.scrollHeight;
    }
});

function payMethod() { return document.querySelector('input[name=pm]:checked').value; }
function updatePayFields() {
    var m = payMethod(), t = total().total;
    var needsCash = (m === 'cash' || m === 'split');
    document.getElementById('cashBox').style.display = needsCash ? 'flex' : 'none';
    document.getElementById('splitBox').style.display = m === 'split' ? 'flex' : 'none';
    document.getElementById('mpesaBox').style.display = (m === 'mpesa' || m === 'split') ? 'flex' : 'none';
    document.getElementById('cardBox').style.display = m === 'card' ? 'flex' : 'none';
    document.getElementById('bankBox').style.display = m === 'bank' ? 'flex' : 'none';
    document.getElementById('saccoBox').style.display = m === 'sacco' ? 'flex' : 'none';
    document.getElementById('referenceBox').style.display = (m === 'cash' || m === 'credit') ? 'none' : 'flex';
    var due = m === 'split' ? (parseFloat(document.getElementById('cashPortionInput').value) || 0) : t;
    var given = parseFloat(document.getElementById('cashGivenInput').value) || 0;
    document.getElementById('balanceOut').textContent = needsCash ? (given >= due ? money(given - due) : 'short') : '—';

    document.getElementById('paymentMethod').value = m;
    var provider = '';
    var accountName = '';
    if (m === 'mpesa' || m === 'split') { accountName = document.getElementById('mpesaNameInput').value || ''; provider = m === 'split' ? 'Cash + M-Pesa' : 'M-Pesa'; }
    if (m === 'card') { provider = document.getElementById('cardTypeInput').value || ''; }
    if (m === 'bank') { provider = document.getElementById('bankInput').value || ''; }
    if (m === 'sacco') { provider = document.getElementById('saccoInput').value || ''; }
    document.getElementById('paymentProvider').value = provider;
    document.getElementById('paymentAccountName').value = accountName;
    document.getElementById('paymentReference').value = document.getElementById('referenceInput').value || '';
    if (m === 'cash') { document.getElementById('amountTendered').value = given; document.getElementById('cashAmount').value = ''; document.getElementById('mpesaAmount').value = ''; }
    else if (m === 'split') {
        document.getElementById('cashAmount').value = document.getElementById('cashPortionInput').value || 0;
        document.getElementById('mpesaAmount').value = document.getElementById('mpesaPortionInput').value || 0;
        document.getElementById('amountTendered').value = document.getElementById('cashGivenInput').value || 0;
    } else {
        document.getElementById('amountTendered').value = '';
        document.getElementById('cashAmount').value = '';
        document.getElementById('mpesaAmount').value = '';
    }
}
document.querySelectorAll('input[name=pm]').forEach(function (r) { r.addEventListener('change', updatePayFields); });
['cashGivenInput', 'cashPortionInput', 'mpesaPortionInput', 'mpesaNameInput', 'cardTypeInput', 'bankInput', 'saccoInput', 'referenceInput'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', updatePayFields);
    document.getElementById(id).addEventListener('change', updatePayFields);
});

document.getElementById('orderForm').addEventListener('submit', function (e) {
    if (!cartHasItems()) { e.preventDefault(); alert('Add at least one product.'); return; }
    if (document.getElementById('formAction').value === 'pay') {
        var m = payMethod(), t = total().total;
        if (m === 'cash' && (parseFloat(document.getElementById('cashGivenInput').value) || 0) < t) { e.preventDefault(); alert('Cash given is less than the total.'); return; }
        if (m === 'split') {
            var cp = parseFloat(document.getElementById('cashPortionInput').value) || 0, mp = parseFloat(document.getElementById('mpesaPortionInput').value) || 0;
            if (Math.abs(cp + mp - t) > 0.01) { e.preventDefault(); alert('Cash and M-Pesa portions must add up to the total.'); return; }
        }
    }
});

// Shop VAT settings from the owner Settings page
(function () {
    var rate = <?php echo json_encode($vatRate); ?>;
    var inclusive = <?php echo $vatInclusive ? 'true' : 'false'; ?>;
    window.SHOP_VAT_RATE = rate;
    document.getElementById('vatRateInput').value = document.getElementById('vatEnabledInput').checked ? rate : 0;
    document.getElementById('vatInclusiveInput').value = inclusive ? '1' : '0';
    updateVatLabel();
})();

function updateVatLabel() {
    var activeRate = parseFloat(document.getElementById('vatRateInput').value) || 0;
    var inclusive = document.getElementById('vatInclusiveInput').value === '1';
    var label = document.getElementById('vatRateLabel');
    if (label) label.textContent = activeRate > 0 ? '(' + activeRate + '%' + (inclusive ? ' incl.' : ' excl.') + ')' : '(off)';
}

var barcodeScan = document.getElementById('barcodeScan');
var scanMsg = document.getElementById('scanMsg');
function flashScan(text, ok) {
    scanMsg.textContent = text;
    scanMsg.style.display = 'block';
    scanMsg.style.color = ok ? 'var(--pos-green-dark, #32004b)' : '#b91c1c';
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
    // Keep the scanner's keystrokes landing here even after other clicks,
    // as long as no other field is being typed into.
    document.addEventListener('click', function (e) {
        if (e.target === barcodeScan || e.target.closest('input, textarea, select, option, label, button, .btn-group')) { return; }
        barcodeScan.focus();
    });
}

render();
applyFilters();
var saleSuccessModal = document.getElementById('saleSuccessModal');
if (saleSuccessModal && window.bootstrap) {
    new bootstrap.Modal(saleSuccessModal).show();
    var receiptPrintUrl = saleSuccessModal.getAttribute('data-print-url');
    if (receiptPrintUrl) {
        setTimeout(function () {
            window.open(receiptPrintUrl, '_blank', 'noopener');
        }, 350);
    }
}
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/' . $layoutName . '/layout.php';
