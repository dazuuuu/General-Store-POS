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
$productTiers = (new Models\PriceTierModel($pdo))->forProducts(array_map(static fn($p) => (int) $p['id'], $products));
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
$heldOrders = $HO->listWithItemsForTenant();
$heldCount  = count($heldOrders);

$error = '';
$cartJson = '[]';
$customerName = '';
$customerId = 0;
$heldOrderId = 0;

$normalizePriceType = static function ($type): string {
    return in_array($type, ['retail', 'retail_pack', 'wholesale'], true) ? $type : 'retail';
};

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
                $priceType = $normalizePriceType($it['price_type'] ?? 'retail');
                foreach (QtyFormat::splitCartBuckets($product, (float) $it['quantity'], $priceType) as $bucket) {
                    $cart[] = [
                        'product_id' => (int) $it['product_id'],
                        'quantity' => $bucket['quantity'],
                        'price_type' => $bucket['price_type'],
                    ];
                }
            }
        }
        $cartJson = json_encode($cart);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'pay';

    if ($action === 'discard_held') {
        $delId = (int) ($_POST['held_id'] ?? 0);
        if ($delId > 0) {
            $HO->discard($delId);
            $_SESSION['flash']['success'] = 'Held sale #' . $delId . ' discarded.';
        }
        header('Location: ' . $shopUrl);
        exit;
    }

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
            'price_type' => $normalizePriceType($c['price_type'] ?? 'retail'),
            'unit_price' => ($c['unit_price'] ?? '') !== '' ? (float)$c['unit_price'] : '',
            'serial_numbers' => array_values(array_filter(array_map('trim',(array)($c['serial_numbers']??[])))),
        ];
    }

    if ($action === 'hold') {
        if ($customerName === '') {
            $customerName = 'Walk-in #' . date('H:i');
        }
        $res = $HO->hold(['customer_name' => $customerName, 'staff_id' => TenantContext::userId(), 'items' => $items]);
        if ($res['ok']) {
            if ($heldOrderId > 0) { $HO->discard($heldOrderId); }
            $resumeAfter = (int) ($_POST['resume_after'] ?? 0);
            $_SESSION['flash']['success'] = 'Sale held' . ($customerName !== '' ? ' for ' . $customerName : '') . '. Ready for next customer.';
            $targetUrl = $resumeAfter > 0 ? ($shopUrl . '?resume=' . $resumeAfter) : $shopUrl;
            header('Location: ' . $targetUrl);
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
            $lineSaleType = $normalizePriceType($it['price_type'] ?? 'retail');
            if ($prod) { $subtotal += Pricing::lineTotal($prod, (float) $it['quantity'], $lineSaleType); }
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

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <button type="button" class="btn btn-sm btn-outline-warning fw-bold d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#heldSalesModal">
      <i class="fas fa-pause-circle"></i>
      <span>Held Sales</span>
      <span class="badge bg-warning text-dark rounded-pill" id="heldSalesBadge"><?php echo $heldCount; ?></span>
    </button>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $ordersBase; ?>"><i class="fas fa-file-invoice-dollar me-1"></i>Credit sales</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('staff/orders/held.php'); ?>"><i class="fas fa-list me-1"></i>All held list</a>
    <?php if ($isSuperShop): ?>
      <a class="btn btn-sm btn-outline-primary" href="<?php echo $bulkUrl; ?>"><i class="fas fa-boxes-stacked me-1"></i>Bulk sale</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo $documentsUrl; ?>"><i class="fas fa-file-lines me-1"></i>Documents</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/inventory/'); ?>"><i class="fas fa-warehouse me-1"></i>Inventory</a>
      <?php if (TenantContext::can(Capabilities::STOCK_ENTER)): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_url('super/stationery/new.php'); ?>"><i class="fas fa-box-open me-1"></i>Record product</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <div>
    <button type="button" class="btn btn-sm btn-light border text-secondary fw-semibold" id="clearSaleBtn" title="Clear current cart and start a fresh sale">
      <i class="fas fa-rotate-left me-1"></i>New sale
    </button>
  </div>
</div>

<?php if ($heldOrderId > 0): ?>
<div class="alert alert-warning py-2 px-3 d-flex justify-content-between align-items-center mb-3 border-warning" style="border-radius:12px;">
  <div>
    <i class="fas fa-bookmark me-1 text-warning"></i>
    Currently resumed: <strong><?php echo htmlspecialchars($customerName ?: 'Held #' . $heldOrderId); ?></strong>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <a href="<?php echo $shopUrl; ?>" class="btn btn-sm btn-outline-danger py-1" title="Discard this hold and start a fresh sale">Start new sale</a>
  </div>
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

    <div class="pos-mode-tabs" id="saleModeTabs" aria-label="Sale mode">
      <button type="button" class="pos-mode active" data-sale-mode="retail"><i class="fas fa-cube me-1"></i>Retail item</button>
      <button type="button" class="pos-mode" data-sale-mode="retail_pack"><i class="fas fa-box me-1"></i>Retail carton</button>
      <button type="button" class="pos-mode" data-sale-mode="wholesale"><i class="fas fa-boxes-stacked me-1"></i>Wholesale carton</button>
    </div>

    <div class="pos-prod-grid" id="productList">
      <?php foreach ($products as $p):
          $price = (float) ($p['retail_price'] ?: $p['selling_price']);
          $wholesale = (float) ($p['wholesale_price'] ?: $price);
          $unitsPerPack = max(1, (float) ($p['units_per_pack'] ?? 1));
          $packUnit = (string) ($p['pack_unit'] ?? '');
          $packPrice = ($p['pack_price'] ?? '') !== '' && $p['pack_price'] !== null ? (float) $p['pack_price'] : 0;
          $retailPackPrice = ($p['retail_pack_price'] ?? '') !== '' && $p['retail_pack_price'] !== null ? (float) $p['retail_pack_price'] : 0;
          $colorBits = !empty($p['colors']) ? (is_array($p['colors']) ? $p['colors'] : []) : [];
          $faulty = (float) ($p['faulty_quantity'] ?? 0);
          $sub = implode(' · ', array_filter([
              $p['brand_name'] ?? null,
              $colorBits ? implode('/', $colorBits) : null,
              !empty($p['unit']) && $p['unit'] !== 'piece' ? $p['unit'] : null,
              $faulty > 0 ? ('faulty ' . rtrim(rtrim(number_format($faulty, 2), '0'), '.')) : null,
          ]));
          $label = $p['name'] . ($sub ? " ({$sub})" : '');
          $tiersJson = json_encode($productTiers[(int) $p['id']] ?? [], JSON_UNESCAPED_UNICODE);
      ?>
        <div class="pos-card<?php echo !empty($p['is_archived']) ? ' pos-card-archived' : ''; ?>" data-id="<?php echo (int) $p['id']; ?>" data-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
             data-price="<?php echo $price; ?>" data-wholesale="<?php echo $wholesale; ?>"
             data-buying="<?php echo (float) ($p['buying_price'] ?? 0); ?>"
             data-package-buying="<?php echo (float) ($p['package_buying_price'] ?? 0); ?>"
             data-stock="<?php echo (float) $p['quantity']; ?>"
             data-units-per-pack="<?php echo $unitsPerPack; ?>"
             data-pack-unit="<?php echo htmlspecialchars($packUnit, ENT_QUOTES); ?>"
             data-pack-price="<?php echo $packPrice; ?>"
             data-retail-pack-price="<?php echo $retailPackPrice; ?>"
             data-tiers="<?php echo htmlspecialchars($tiersJson ?: '[]', ENT_QUOTES); ?>"
             data-type="product"
             data-category="<?php echo (int) ($p['category_id'] ?? 0); ?>"
             data-brand="<?php echo (int) (($p['brand_id'] ?? 0) ?: ($p['publisher_id'] ?? 0)); ?>"
             data-on-offer="<?php echo !empty($p['on_offer']) ? '1' : '0'; ?>"
             data-archived="<?php echo !empty($p['is_archived']) ? '1' : '0'; ?>"
             data-barcode="<?php echo htmlspecialchars($p['barcode'] ?? '', ENT_QUOTES); ?>"
             data-serial-tracking="<?php echo !empty($p['serial_tracking'])?'1':'0';?>">
          <?php if (!empty($p['on_offer'])): ?><span class="pos-ribbon">OFFER</span><?php endif; ?>
          <?php if (!empty($p['is_archived'])): ?><span class="pos-ribbon pos-ribbon-archive">ARCHIVE</span><?php endif; ?>
          <div class="pos-card-img">
            <?php if (!empty($p['image_path'])): ?><img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
            <?php else: ?><i class="fas fa-box"></i><?php endif; ?>
          </div>
          <div class="pos-card-name"><?php echo htmlspecialchars($p['name']); ?><?php echo $sub ? '<small>' . htmlspecialchars($sub) . '</small>' : ''; ?></div>
          <div class="pos-card-price" data-card-price>
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
            <?php if ($packUnit !== '' && $unitsPerPack > 1 && $retailPackPrice > 0): ?>
              <div class="small text-muted">Retail carton KES <?php echo number_format($retailPackPrice, 0); ?> / <?php echo htmlspecialchars($packUnit); ?></div>
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
      <div class="pos-side-mobile-head d-lg-none d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
        <div class="fw-bold fs-6 text-dark"><i class="fas fa-receipt me-2 text-primary"></i>Order Details</div>
        <button type="button" class="btn-close" id="closeMobileCartBtn" aria-label="Close Order Details"></button>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="pos-side-title mb-0">Order Details</h2>
        <span class="badge bg-light text-dark border fw-bold" id="cartTotalItemsBadge">0 items</span>
      </div>
      <?php if ($heldOrderId > 0): ?>
      <div class="pos-resumed-indicator mb-2 d-flex justify-content-between align-items-center p-2 rounded bg-warning bg-opacity-10 border border-warning">
        <div class="small text-dark text-truncate">
          <i class="fas fa-bookmark text-warning me-1"></i> Resumed: <strong><?php echo htmlspecialchars($customerName ?: 'Hold #' . $heldOrderId); ?></strong>
        </div>
        <a href="<?php echo $shopUrl; ?>" class="btn btn-xs btn-outline-danger py-0 px-2" title="Clear and start new sale">New sale</a>
      </div>
      <?php endif; ?>
      <div class="pos-customer mb-2">
        <div class="pos-customer-icon"><i class="fas fa-user"></i></div>
        <input type="text" name="table_name" id="customerName" class="pos-customer-input" value="<?php echo htmlspecialchars($customerName); ?>" autocomplete="off" placeholder="Customer name or table (optional)">
        <div class="customer-suggest-menu" id="customerSuggestMenu"></div>
      </div>
    </div>

    <div class="pos-cart-wrap">
      <div class="pos-cart-search-wrap mb-2" id="cartSearchWrap" style="display:none;">
        <div class="pos-cart-search-box">
          <i class="fas fa-search pos-cart-search-icon"></i>
          <input type="text" id="cartSearchInput" class="pos-cart-search-input" placeholder="Search items in this order..." autocomplete="off">
          <button type="button" id="cartSearchClear" class="pos-cart-search-clear" style="display:none;" title="Clear search"><i class="fas fa-xmark"></i></button>
        </div>
        <div id="cartSearchCount" class="pos-cart-search-count small text-muted mt-1" style="display:none;"></div>
      </div>
      <div class="pos-cart" id="cartRows"><div class="text-muted small text-center py-4">Tap a product to add it.</div></div>
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
        <input type="hidden" name="sale_type" id="saleType" value="retail">
        <input type="hidden" name="vat_rate" id="vatRateInput" value="0">
        <input type="hidden" name="vat_inclusive" id="vatInclusiveInput" value="1">
        <div class="d-flex justify-content-between pos-total-line"><span>Total</span><span id="totalOut">KES 0</span></div>
        <div class="d-flex justify-content-between small mt-1" id="cartProfitRow" style="display:none !important;">
          <span class="text-muted">Est. profit</span>
          <span id="profitOut" class="fw-semibold text-success">KES 0</span>
        </div>
      </div>

      <div class="pos-actions" id="cartButtons">
        <button type="button" class="pos-btn pos-btn-outline" id="holdBtn" disabled>
          <i class="fas fa-pause me-1"></i>Hold Sale
        </button>
        <button type="button" class="pos-btn pos-btn-primary" id="checkoutBtn" disabled>
          Checkout <i class="fas fa-arrow-right ms-1"></i>
        </button>
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

<!-- Sticky Mobile Cart Bar for screens < 992px -->
<div class="pos-mobile-bar d-lg-none" id="posMobileBar">
  <div class="pos-mobile-bar-info" id="openMobileCartFromBar">
    <div class="pos-mobile-cart-icon">
      <i class="fas fa-shopping-cart"></i>
      <span class="pos-mobile-badge" id="mobileCartCountBadge">0</span>
    </div>
    <div>
      <div class="small text-muted" style="line-height:1;">Total:</div>
      <div class="fw-bold text-dark fs-6" id="mobileBarTotal">KES 0</div>
    </div>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <button type="button" class="btn btn-sm btn-outline-warning fw-bold d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#heldSalesModal" title="Held Sales">
      <i class="fas fa-pause-circle me-1"></i> <span class="badge bg-warning text-dark rounded-pill"><?php echo $heldCount; ?></span>
    </button>
    <button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-2" id="mobileOpenCartBtn">
      View Order <i class="fas fa-chevron-up ms-1"></i>
    </button>
  </div>
</div>

<!-- Modal: Held Sales -->
<div class="modal fade" id="heldSalesModal" tabindex="-1" aria-labelledby="heldSalesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow" style="border-radius:16px;">
      <div class="modal-header border-bottom py-3">
        <h5 class="modal-title fw-bold" id="heldSalesModalLabel">
          <i class="fas fa-pause-circle text-warning me-2"></i>Held Sales (<?php echo $heldCount; ?>)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3">
        <?php if (!$heldOrders): ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-pause fa-2x mb-3 d-block text-warning" style="opacity:.35;"></i>
            <div class="fw-bold mb-1">No sales on hold</div>
            <div class="small">When you pause a sale using "Hold Sale", it will appear here so you can resume it anytime.</div>
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($heldOrders as $ho): ?>
              <div class="col-12 col-md-6">
                <div class="card h-100 border shadow-sm" style="border-radius:12px;">
                  <div class="card-body p-3 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                      <div>
                        <span class="badge bg-warning text-dark mb-1">Hold #<?php echo (int) $ho['id']; ?></span>
                        <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($ho['customer_name'] ?: 'Walk-in customer'); ?></div>
                      </div>
                      <div class="text-end">
                        <div class="fw-bold text-success fs-5">KES <?php echo number_format((float) $ho['total'], 0); ?></div>
                        <div class="small text-muted"><?php echo (int) $ho['item_count']; ?> item<?php echo (int) $ho['item_count'] === 1 ? '' : 's'; ?></div>
                      </div>
                    </div>
                    <div class="text-muted small mb-2">
                      <i class="far fa-clock me-1"></i><?php echo date('g:i a', strtotime($ho['created_at'])); ?>
                      <?php if (!empty($ho['staff_name'])): ?> · by <?php echo htmlspecialchars($ho['staff_name']); ?><?php endif; ?>
                    </div>
                    <?php if (!empty($ho['items'])): ?>
                      <div class="bg-light rounded p-2 mb-3 small flex-grow-1" style="max-height:85px; overflow-y:auto;">
                        <ul class="list-unstyled mb-0 text-secondary">
                          <?php foreach ($ho['items'] as $it): ?>
                            <li class="d-flex justify-content-between py-1 border-bottom border-light">
                              <span class="text-truncate me-2"><strong><?php echo (float) $it['quantity']; ?>×</strong> <?php echo htmlspecialchars($it['product_name']); ?></span>
                              <span class="text-nowrap fw-semibold">KES <?php echo number_format((float) ($it['unit_price'] * $it['quantity']), 0); ?></span>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      </div>
                    <?php else: ?>
                      <div class="flex-grow-1"></div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 pt-2 border-top">
                      <button type="button" class="btn btn-sm btn-success flex-fill fw-bold js-resume-held" data-id="<?php echo (int) $ho['id']; ?>" data-name="<?php echo htmlspecialchars($ho['customer_name'] ?: 'Held #' . $ho['id'], ENT_QUOTES); ?>">
                        <i class="fas fa-play me-1"></i>Resume
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger js-discard-held" data-id="<?php echo (int) $ho['id']; ?>" title="Discard this hold">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-top py-2">
        <a href="<?php echo public_url('staff/orders/held.php'); ?>" class="btn btn-sm btn-outline-secondary me-auto">
          <i class="fas fa-list me-1"></i>Open full held sales page
        </a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Confirm Hold Sale -->
<div class="modal fade" id="holdSaleModal" tabindex="-1" aria-labelledby="holdSaleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow" style="border-radius:14px;">
      <div class="modal-header border-bottom py-2">
        <h6 class="modal-title fw-bold" id="holdSaleModalLabel"><i class="fas fa-pause-circle text-warning me-1"></i>Hold Current Sale</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3">
        <p class="small text-muted mb-2">Pause this sale and clear the cart to serve another customer. You can resume it anytime.</p>
        <div class="mb-3">
          <label class="form-label small fw-bold mb-1">Customer / Reference Note</label>
          <input type="text" id="holdReferenceInput" class="form-control form-control-sm" placeholder="e.g. Table 4, Red shirt, or customer name">
        </div>
        <button type="button" class="btn btn-warning w-100 fw-bold py-2 text-dark" id="btnConfirmHold">
          <i class="fas fa-pause me-1"></i>Hold and Start New Sale
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Hidden Form for Discarding Held Sale -->
<form method="post" id="discardHeldForm" style="display:none;">
  <input type="hidden" name="action" value="discard_held">
  <input type="hidden" name="held_id" id="discardHeldId" value="">
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
/* ============================================================
   RESPONSIVE POS LAYOUT (DESKTOP & MOBILE)
   ============================================================ */
.pos-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  gap: 22px;
  align-items: start;
}
.pos-main {
  min-width: 0;
}

/* Search and Scan inputs */
.pos-search { position: relative; margin-bottom: 12px; }
.pos-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.95rem; }
.pos-search input {
  width: 100%;
  padding: 12px 14px 12px 42px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  font-size: 0.95rem;
  color: #0f172a;
  transition: border-color .15s, box-shadow .15s;
}
.pos-search input:focus { outline: none; border-color: var(--pos-green); box-shadow: 0 0 0 .2rem rgba(75,0,110,.12); }
.pos-scan input { border-color: var(--pos-green-light); background: var(--pos-green-light); }
.pos-scan i { color: var(--pos-green); }

/* Dim & Mode Tabs */
.pos-dim-tabs { display: flex; gap: 8px; margin-bottom: 12px; overflow-x: auto; padding-bottom: 4px; }
.pos-dim {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #334155;
  border-radius: 999px;
  padding: 6px 16px;
  font-size: 0.82rem;
  font-weight: 700;
  white-space: nowrap;
  transition: all .15s;
}
.pos-dim.active { border-color: var(--pos-green); color: var(--pos-green); background: var(--pos-green-light); }
.pos-cats { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 16px; }
.pos-cat {
  flex: 0 0 auto;
  width: 88px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 14px;
  padding: 12px 8px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
  transition: all .15s;
}
.pos-cat-img {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  color: #94a3b8;
  font-size: 1.1rem;
}
.pos-cat-img img { width: 100%; height: 100%; object-fit: cover; }
.pos-cat.active { border-color: var(--pos-green); color: var(--pos-green); background: var(--pos-green-light); }
.pos-cat.active .pos-cat-img, .pos-cat.active .pos-cat-all { background: #fff; color: var(--pos-green); }

.pos-mode-tabs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 0 0 16px; }
.pos-mode {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  border-radius: 10px;
  padding: 10px 8px;
  font-size: 0.84rem;
  font-weight: 700;
  white-space: nowrap;
  text-align: center;
  transition: all .15s;
}
.pos-mode.active { border-color: var(--pos-green); background: var(--pos-green-light); color: var(--pos-green); }

/* Product Grid & Cards */
.pos-prod-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
@media (min-width: 540px) { .pos-prod-grid { grid-template-columns: repeat(3, 1fr); gap: 14px; } }
@media (min-width: 992px) { .pos-prod-grid { grid-template-columns: repeat(3, 1fr); gap: 14px; } }
@media (min-width: 1280px) { .pos-prod-grid { grid-template-columns: repeat(4, 1fr); gap: 14px; } }
@media (min-width: 1540px) { .pos-prod-grid { grid-template-columns: repeat(5, 1fr); gap: 16px; } }

.pos-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  text-align: center;
  position: relative;
  transition: transform .12s ease, box-shadow .15s ease;
}
.pos-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
}
.pos-card-img { height: 64px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
.pos-card-img img { max-height: 64px; max-width: 100%; object-fit: contain; }
.pos-card-img i { font-size: 1.8rem; color: #cbd5e1; }
.pos-card-name {
  font-weight: 700;
  font-size: 0.9rem;
  color: #0f172a;
  margin-bottom: 4px;
  line-height: 1.3;
  min-height: 2.4em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.pos-card-name small { color: #475569; font-weight: 500; font-size: 0.78rem; display: block; margin-top: 2px; }
.pos-card-price { color: var(--pos-green); font-weight: 800; font-size: 0.92rem; margin-bottom: 8px; }
.pos-card-regprice { color: #94a3b8; font-weight: 400; text-decoration: line-through; margin-right: 5px; font-size: 0.8em; }
.pos-ribbon {
  position: absolute;
  top: 8px;
  left: 8px;
  background: #f59e0b;
  color: #fff;
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: .03em;
  padding: 2px 7px;
  border-radius: 999px;
  z-index: 2;
}
.pos-ribbon-archive { left: auto; right: 8px; background: #475569; }
.pos-card-archived { opacity: .9; }
.pos-offer-banner {
  display: block;
  width: 100%;
  text-align: left;
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #92400e;
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 0.88rem;
  font-weight: 600;
  margin-bottom: 14px;
  cursor: pointer;
}
.pos-add-row { display: flex; gap: 6px; align-items: stretch; margin-top: auto; }
.pos-add {
  flex: 1;
  border: 0;
  border-radius: 10px;
  background: var(--pos-green);
  color: #fff;
  padding: 9px 0;
  font-weight: 700;
  font-size: 0.88rem;
  transition: background .15s;
}
.pos-add:hover { background: var(--pos-green-dark); }
.pos-add-half {
  width: 44px;
  border: 1px solid var(--pos-green);
  border-radius: 10px;
  background: #fff;
  color: var(--pos-green);
  font-weight: 800;
  font-size: 0.95rem;
  line-height: 1;
}
.pos-add-half:hover { background: var(--pos-green-light); }
.pos-card.is-mode-unavailable .pos-add, .pos-card.is-mode-unavailable .pos-add-half { opacity: .45; pointer-events: none; }

/* ============================================================
   ORDER DETAILS (CART SIDE PANEL)
   ============================================================ */
.pos-side {
  position: sticky;
  top: 16px;
  width: 100%;
  height: calc(100vh - 32px);
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
  z-index: 30;
}
.pos-side-head { flex: 0 0 auto; }
.pos-side-title { font-size: 1.1rem; font-weight: 800; color: #0f172a; }
.pos-customer {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 8px 12px;
  position: relative;
}
.pos-customer-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--pos-green-light);
  color: var(--pos-green);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pos-customer-input { border: 0; background: transparent; flex: 1; font-weight: 600; font-size: 0.92rem; color: #0f172a; }
.pos-customer-input:focus { outline: none; }
.customer-suggest-menu {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 4px);
  z-index: 70;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 12px 28px rgba(15,23,42,.14);
  display: none;
  max-height: 240px;
  overflow: auto;
}
.customer-suggest-menu.show { display: block; }
.customer-suggest-menu button { display: block; width: 100%; border: 0; background: #fff; text-align: left; padding: .55rem .7rem; font-size: .85rem; }
.customer-suggest-menu button:hover { background: #f8fafc; }
.customer-suggest-menu .meta { display: block; color: #64748b; font-size: .75rem; margin-top: 1px; }

/* In-Cart Search Box */
.pos-cart-search-box {
  position: relative;
  display: flex;
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 2px 10px;
  transition: all .15s;
}
.pos-cart-search-box:focus-within {
  border-color: var(--pos-green);
  background: #fff;
  box-shadow: 0 0 0 2px rgba(75,0,110,.1);
}
.pos-cart-search-icon { color: #94a3b8; font-size: 0.85rem; margin-right: 8px; flex-shrink: 0; }
.pos-cart-search-input { border: 0; background: transparent; width: 100%; font-size: 0.88rem; padding: 7px 0; color: #0f172a; }
.pos-cart-search-input:focus { outline: none; }
.pos-cart-search-clear { border: 0; background: transparent; color: #94a3b8; font-size: 0.85rem; padding: 0 4px; cursor: pointer; }
.pos-cart-search-clear:hover { color: #0f172a; }

/* Cart Rows Container */
.pos-cart-wrap {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  margin: 8px 0;
  border-top: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
}
.pos-cart { flex: 1 1 auto; min-height: 0; overflow-y: auto; margin: 0; padding: 6px 2px 8px; -webkit-overflow-scrolling: touch; }
.pos-cart-line { display: flex; gap: 8px; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.pos-cart-idx {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: #f1f5f9;
  color: #475569;
  font-size: 0.72rem;
  font-weight: 800;
  flex-shrink: 0;
  margin-top: 2px;
}
.pos-cart-line img, .pos-cart-line .ph {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  object-fit: cover;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #cbd5e1;
  flex-shrink: 0;
  margin-top: 2px;
}
.pos-cart-name { font-weight: 700; font-size: 0.88rem; color: #0f172a; line-height: 1.3; }
.pos-cart-price { color: #64748b; font-size: 0.78rem; font-weight: 500; }
.pos-qty { display: flex; align-items: center; gap: 5px; }
.pos-qty button { width: 26px; height: 26px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; font-weight: 700; line-height: 1; color: #334155; }
.pos-qty .pos-half-btn { width: auto; min-width: 28px; padding: 0 6px; font-size: 0.78rem; color: var(--pos-green); }
.pos-qty-input { width: 68px; height: 28px; border: 1px solid #e2e8f0; border-radius: 7px; text-align: center; font-weight: 700; font-size: 0.84rem; color: #0f172a; }
.pos-dual-qty { display: flex; flex-direction: column; gap: 6px; margin-top: 6px; }
.pos-dual-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin: 0; }
.pos-dual-label { font-size: 0.74rem; font-weight: 600; color: #475569; min-width: 0; flex: 1; }
.pos-cart-del { color: #94a3b8; background: none; border: 0; font-size: 0.95rem; margin-top: 2px; padding: 4px; transition: color .15s; }
.pos-cart-del:hover { color: #ef4444; }

/* Totals & Actions */
.pos-side-foot { flex: 0 0 auto; max-height: 48vh; overflow-y: auto; padding-top: 4px; -webkit-overflow-scrolling: touch; }
.pos-side.pay-open .pos-side-foot { max-height: 60vh; }
.pos-totals { border-top: 0; padding-top: 4px; font-size: 0.88rem; color: #475569; }
.pos-total-line { font-weight: 800; font-size: 1.15rem; color: #0f172a; margin-top: 6px; }
.pos-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 12px;
  position: sticky;
  bottom: 0;
  background: #fff;
  padding-top: 8px;
  padding-bottom: 4px;
  z-index: 2;
}
.pos-btn { border-radius: 12px; padding: 12px 0; font-weight: 700; font-size: 0.92rem; border: 1px solid #e2e8f0; text-align: center; }
.pos-btn-outline { background: #fff; color: #334155; }
.pos-btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
.pos-btn-primary { background: var(--pos-green); border-color: var(--pos-green); color: #fff; }
.pos-btn-primary:hover { background: var(--pos-green-dark); }
.pos-btn:disabled { opacity: .45; cursor: not-allowed; }

/* ============================================================
   STICKY MOBILE BAR & MOBILE DRAWER (< 992px)
   ============================================================ */
.pos-mobile-bar { display: none; }
@media (max-width: 991.98px) {
  .pos-grid {
    display: block;
    padding-bottom: 76px;
  }
  .pos-side {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    max-height: 100vh;
    border-radius: 0;
    z-index: 1060;
    background: #fff;
    transform: translateY(100%);
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    box-shadow: none;
    padding: 16px 16px 20px;
  }
  .pos-side.mobile-open {
    transform: translateY(0);
  }
  .pos-mobile-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 64px;
    z-index: 1040;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.08);
  }
  .pos-mobile-bar-info {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
  }
  .pos-mobile-cart-icon {
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--pos-green-light);
    color: var(--pos-green);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
  }
  .pos-mobile-badge {
    position: absolute;
    top: -4px;
    right: -6px;
    background: var(--pos-green);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 999px;
    border: 2px solid #fff;
  }
  .pos-actions { grid-template-columns: 1fr 1fr; }
  #payPanel .btn-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
  #payPanel .btn-group > .btn { border-radius: 8px !important; margin: 0 !important; }
}
</style>

<script src="<?php echo htmlspecialchars(public_url('assets/js/pos-pack-cart.js')); ?>"></script>
<script>
var PC = window.PosPackCart;
var PRODUCT_COMMISSION_ENABLED = <?php echo !empty($tenantRow['product_commission_enabled']) ? 'true' : 'false'; ?>;
var PRODUCTS = {};
var BARCODES = {};
document.querySelectorAll('.pos-card').forEach(function (el) {
    var img = el.querySelector('.pos-card-img img');
    var tiers = [];
    try { tiers = JSON.parse(el.dataset.tiers || '[]') || []; } catch (e) { tiers = []; }
    PRODUCTS[el.dataset.id] = {
        name: el.dataset.name,
        price: parseFloat(el.dataset.price),
        wholesale: parseFloat(el.dataset.wholesale),
        buying: parseFloat(el.dataset.buying) || 0,
        packageBuying: parseFloat(el.dataset.packageBuying) || 0,
        stock: parseFloat(el.dataset.stock),
        unitsPerPack: parseFloat(el.dataset.unitsPerPack) || 1,
        packUnit: el.dataset.packUnit || '',
        packPrice: parseFloat(el.dataset.packPrice) || 0,
        retailPackPrice: parseFloat(el.dataset.retailPackPrice) || 0,
        barcode: el.dataset.barcode || '',
        serialTracking: el.dataset.serialTracking === '1',
        tiers: tiers,
        img: img ? img.getAttribute('src') : null
    };
    if (el.dataset.barcode) { BARCODES[el.dataset.barcode] = el.dataset.id; }
});

var cart = {};
var cartOrder = []; // STRICT CHRONOLOGICAL INSERTION ORDER
try {
    var initialItems = JSON.parse(<?php echo json_encode($cartJson); ?>) || [];
    initialItems.forEach(function (c) {
        var id = String(c.product_id);
        PC.applyLine(cart, c, PRODUCTS[id]);
        if (cartOrder.indexOf(id) === -1) {
            cartOrder.push(id);
        }
    });
} catch (e) {}

function money(n) { return 'KES ' + n.toLocaleString('en-KE', {maximumFractionDigits: 0}); }
function activeModeLabel(type) {
    if (type === 'retail_pack') return 'Retail carton';
    if (type === 'wholesale') return 'Wholesale carton';
    return 'Retail item';
}
function modeUnitLabel(p, type) {
    if ((type === 'retail_pack' || type === 'wholesale') && p.packUnit && p.unitsPerPack > 1) return p.packUnit;
    return 'item';
}
function modeAvailable(p, type) {
    if (type === 'retail_pack') return PC.hasRetailPack(p);
    if (type === 'wholesale') return PC.hasWholesalePack(p) || p.wholesale > 0;
    return true;
}
function updateProductModeDisplay() {
    var type = defaultSaleType();
    document.querySelectorAll('.pos-card').forEach(function (el) {
        var p = PRODUCTS[el.dataset.id];
        var priceEl = el.querySelector('[data-card-price]');
        if (!p || !priceEl) return;
        var available = modeAvailable(p, type);
        el.classList.toggle('is-mode-unavailable', !available);
        if (!available) {
            priceEl.innerHTML = '<span class="text-muted">' + activeModeLabel(type) + ' not set</span>';
            return;
        }
        var price = PC.productPrice(p, type);
        var unit = modeUnitLabel(p, type);
        var stock = type === 'retail_pack' ? PC.maxRetailPack(p, { retail: 0, retailPack: 0, wholesale: 0 })
            : (type === 'wholesale' ? PC.maxWholesale(p, { retail: 0, retailPack: 0, wholesale: 0 }) : p.stock);
        var stockText = (Math.round(stock * 100) / 100).toLocaleString('en-KE', {maximumFractionDigits: 2});
        priceEl.innerHTML = activeModeLabel(type) + ' ' + money(price)
            + '<div class="small text-muted">per ' + unit + ' · stock ' + stockText + ' ' + unit + '</div>';
    });
}
function formatHalfQty(n) {
    n = Math.round((parseFloat(n) || 0) * 100) / 100;
    var whole = Math.floor(n + 0.0001);
    var frac = Math.round((n - whole) * 100) / 100;
    if (Math.abs(frac - 0.5) < 0.001) return (whole > 0 ? String(whole) : '') + '½';
    if (Math.abs(frac) < 0.001) return String(whole);
    return String(n);
}
function defaultSaleType() { return document.getElementById('saleType').value; }
function ensureCart(id) {
    var strId = String(id);
    if (!cart[strId]) cart[strId] = PC.buckets();
    if (cartOrder.indexOf(strId) === -1) {
        cartOrder.push(strId);
    }
    return cart[strId];
}
function cartHasItems() {
    return Object.keys(cart).some(function (id) { return !PC.isEmpty(cart[id]); });
}
function serializeCart() {
    return PC.serialize(cart, PRODUCTS, cartOrder);
}
function pruneCart(id) {
    var strId = String(id);
    if (!cart[strId]) return;
    if (PC.isEmpty(cart[strId])) {
        delete cart[strId];
        cartOrder = cartOrder.filter(function(x) { return x !== strId; });
    }
}
function setFieldQty(id, field, val) {
    var strId = String(id);
    var p = PRODUCTS[strId]; if (!p) return;
    var c = ensureCart(strId);
    c[field] = PC.clampField(p, c, field, val);
    pruneCart(strId);
    render();
}
function bump(id, field, delta) {
    var strId = String(id);
    var c = ensureCart(strId);
    if(PRODUCTS[strId]&&PRODUCTS[strId].serialTracking&&field==='retail'){
        if(delta>0&&!confirmSerial(strId))return;
        if(delta<0&&c.serialNumbers&&c.serialNumbers.length)c.serialNumbers.pop();
    }
    setFieldQty(strId, field, (c[field] || 0) + delta);
}
function confirmSerial(id) {
    var p=PRODUCTS[String(id)];if(!p||!p.serialTracking)return true;
    var serial=window.prompt('Scan or enter the serial number / IMEI for '+p.name);
    if(!serial)return false;serial=serial.trim();if(!serial)return false;
    var c=ensureCart(id);c.serialNumbers=c.serialNumbers||[];
    if(c.serialNumbers.indexOf(serial)!==-1){alert('That serial is already in this order.');return false;}
    c.serialNumbers.push(serial);return true;
}
function add(id) {
    var strId = String(id);
    var type = defaultSaleType();
    if (!modeAvailable(PRODUCTS[strId], type)) return;
    if (cartOrder.indexOf(strId) === -1) {
        cartOrder.push(strId);
    }
    if (type === 'wholesale') bump(strId, 'wholesale', 1);
    else if (type === 'retail_pack') bump(strId, 'retailPack', 1);
    else bump(strId, 'retail', 1);
}
function addHalf(id) {
    var strId = String(id);
    var type = defaultSaleType();
    if(PRODUCTS[strId]&&PRODUCTS[strId].serialTracking){alert('Serialized products must be sold as whole units.');return;}
    if (!modeAvailable(PRODUCTS[strId], type)) return;
    if (cartOrder.indexOf(strId) === -1) {
        cartOrder.push(strId);
    }
    if (type === 'wholesale') bump(strId, 'wholesale', 0.5);
    else if (type === 'retail_pack') bump(strId, 'retailPack', 0.5);
    else bump(strId, 'retail', 0.5);
}
function subtotal() {
    var t = 0;
    cartOrder.forEach(function (id) {
        var p = PRODUCTS[id], c = cart[id];
        if (p && c) t += PC.lineTotal(p, c);
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
    function renderSuggest(items) {
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
                .then(function(data){ renderSuggest(data.items || []); })
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
function lineCost(p, c) {
    var cost = 0;
    var unitBuy = parseFloat(p.buying) || 0;
    var pkgBuy = parseFloat(p.packageBuying) || 0;
    var upp = parseFloat(p.unitsPerPack) || 1;
    if ((c.retail || 0) > 0) cost += (c.retail || 0) * unitBuy;
    if ((c.retailPack || 0) > 0) {
        cost += (c.retailPack || 0) * (pkgBuy > 0 ? pkgBuy : (unitBuy * upp));
    }
    if ((c.wholesale || 0) > 0) {
        if (PC.hasWholesalePack(p)) {
            cost += (c.wholesale || 0) * (pkgBuy > 0 ? pkgBuy : (unitBuy * upp));
        } else {
            cost += (c.wholesale || 0) * unitBuy;
        }
    }
    return Math.round(cost * 100) / 100;
}
function cartProfit() {
    var profit = 0;
    Object.keys(cart).forEach(function (id) {
        var p = PRODUCTS[id], c = cart[id];
        if (!p || !c || PC.isEmpty(c)) return;
        profit += PC.lineTotal(p, c) - lineCost(p, c);
    });
    return Math.round(profit * 100) / 100;
}
function updateTotals() {
    var t = total();
    document.getElementById('subtotalOut').textContent = money(subtotal());
    var vatOut = document.getElementById('vatOut');
    if (vatOut) vatOut.textContent = money(t.vat);
    document.getElementById('totalOut').textContent = money(t.total);
    document.getElementById('payableOut').textContent = money(t.total);
    var mobileBarTotal = document.getElementById('mobileBarTotal');
    if (mobileBarTotal) mobileBarTotal.textContent = money(t.total);
    var profit = cartProfit();
    var profitRow = document.getElementById('cartProfitRow');
    var profitOut = document.getElementById('profitOut');
    if (profitRow && profitOut) {
        if (cartHasItems()) {
            profitRow.style.setProperty('display', 'flex', 'important');
            profitOut.textContent = money(profit);
            profitOut.className = 'fw-semibold ' + (profit < 0 ? 'text-danger' : 'text-success');
        } else {
            profitRow.style.setProperty('display', 'none', 'important');
        }
    }
    updatePayFields();
}

/* In-Cart Search */
var cartSearchQuery = '';
var cartSearchInput = document.getElementById('cartSearchInput');
var cartSearchClear = document.getElementById('cartSearchClear');
var cartSearchCount = document.getElementById('cartSearchCount');
if (cartSearchInput) {
    cartSearchInput.addEventListener('input', function () {
        cartSearchQuery = cartSearchInput.value.toLowerCase().trim();
        if (cartSearchClear) cartSearchClear.style.display = cartSearchQuery ? 'block' : 'none';
        render();
    });
}
if (cartSearchClear) {
    cartSearchClear.addEventListener('click', function () {
        if (cartSearchInput) { cartSearchInput.value = ''; }
        cartSearchQuery = '';
        cartSearchClear.style.display = 'none';
        render();
        if (cartSearchInput) { cartSearchInput.focus(); }
    });
}

function render() {
    var wrap = document.getElementById('cartRows');
    var cartSearchWrap = document.getElementById('cartSearchWrap');
    
    // Prune and keep strict insertion order
    cartOrder = cartOrder.filter(function (id) { return cart[id] && !PC.isEmpty(cart[id]); });
    Object.keys(cart).forEach(function (id) {
        if (!PC.isEmpty(cart[id]) && cartOrder.indexOf(String(id)) === -1) {
            cartOrder.push(String(id));
        }
    });

    var ids = cartOrder; // Chronological order
    var totalItems = ids.length;

    var badgeEl = document.getElementById('cartTotalItemsBadge');
    if (badgeEl) badgeEl.textContent = totalItems + ' ' + (totalItems === 1 ? 'item' : 'items');
    var mobileBadge = document.getElementById('mobileCartCountBadge');
    if (mobileBadge) mobileBadge.textContent = totalItems;

    if (cartSearchWrap) {
        cartSearchWrap.style.display = totalItems > 0 ? 'block' : 'none';
    }

    var query = cartSearchQuery;
    var visibleIds = ids;
    if (query) {
        visibleIds = ids.filter(function (id) {
            var p = PRODUCTS[id];
            if (!p) return false;
            var nameMatch = p.name && p.name.toLowerCase().indexOf(query) !== -1;
            var barcodeMatch = BARCODES && Object.keys(BARCODES).some(function (code) {
                return BARCODES[code] === id && code.indexOf(query) !== -1;
            });
            return nameMatch || barcodeMatch;
        });
    }

    if (cartSearchCount) {
        if (query && totalItems > 0) {
            cartSearchCount.style.display = 'block';
            cartSearchCount.textContent = 'Showing ' + visibleIds.length + ' of ' + totalItems + ' items in order';
        } else {
            cartSearchCount.style.display = 'none';
        }
    }

    if (!totalItems) {
        wrap.innerHTML = '<div class="text-muted small text-center py-5"><i class="fas fa-basket-shopping fa-2x mb-2 d-block" style="opacity:.25;"></i>Tap a product to add it.<br>Products will be arranged in order.</div>';
    } else if (query && !visibleIds.length) {
        wrap.innerHTML = '<div class="text-muted small text-center py-4"><i class="fas fa-magnifying-glass fa-2x mb-2 d-block" style="opacity:.25;"></i>No item matches "<strong>' + query.replace(/[&<>'"]/g, '') + '</strong>" in order.<br><button type="button" class="btn btn-sm btn-link p-0 mt-1" id="resetCartSearchFilter">Clear search</button></div>';
        var resetBtn = document.getElementById('resetCartSearchFilter');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (cartSearchInput) { cartSearchInput.value = ''; }
                cartSearchQuery = '';
                if (cartSearchClear) { cartSearchClear.style.display = 'none'; }
                render();
            });
        }
    } else {
        wrap.innerHTML = '';
        visibleIds.forEach(function (id) {
            var p = PRODUCTS[id], c = cart[id];
            if (!p || !c) return;
            var seqIndex = ids.indexOf(id) + 1;
            var retailMax = Math.max(c.retail || 0, PC.maxRetail(p, c));
            var retailPackMax = Math.max(c.retailPack || 0, PC.maxRetailPack(p, c));
            var wholesaleMax = Math.max(c.wholesale || 0, PC.maxWholesale(p, c));
            var wLabel = PC.hasWholesalePack(p) ? PC.packLabel(p) : 'item';
            var lineTotal = PC.lineTotal(p, c);
            var profit = Math.round((lineTotal - lineCost(p, c)) * 100) / 100;
            var rows = '';
            if ((c.retail || 0) > 0) {
                rows += PC.qtyRow(id, 'Retail item', money(PC.productPrice(p, 'retail')) + '/item', 'retail', c.retail || 0, retailMax);
            }
            if ((c.retailPack || 0) > 0 && PC.hasRetailPack(p)) {
                rows += PC.qtyRow(id, 'Retail box', money(PC.productPrice(p, 'retail_pack')) + '/' + PC.packLabel(p), 'retailPack', c.retailPack || 0, retailPackMax);
            }
            if ((c.wholesale || 0) > 0) {
                rows += PC.qtyRow(id, 'Wholesale', money(PC.productPrice(p, 'wholesale')) + '/' + wLabel, 'wholesale', c.wholesale || 0, wholesaleMax);
            }
            if (PRODUCT_COMMISSION_ENABLED && (c.retail || 0) > 0) {
                rows += '<label class="pos-dual-row"><span class="pos-dual-label">Commission selling price'
                  + ' <span class="text-muted">(minimum ' + money(p.price) + ')</span></span>'
                  + '<input type="number" step="0.01" min="' + p.price + '" class="form-control form-control-sm"'
                  + ' style="max-width:130px;" data-commission-price="' + id + '" value="' + (c.customUnitPrice || p.price) + '"></label>';
            }
            if (p.serialTracking && c.serialNumbers && c.serialNumbers.length) {
                rows += '<div class="small text-primary mt-1"><i class="fas fa-fingerprint me-1"></i>Serials: '+c.serialNumbers.join(', ')+'</div>';
            }
            var line = document.createElement('div');
            line.className = 'pos-cart-line pos-cart-line-dual';
            line.innerHTML = '<span class="pos-cart-idx">#' + seqIndex + '</span>'
              + (p.img ? '<img src="' + p.img + '">' : '<div class="ph"><i class="fas fa-box"></i></div>')
              + '<div class="flex-grow-1 min-w-0">'
              +   '<div class="pos-cart-name">' + p.name + '</div>'
              +   '<div class="pos-dual-qty">' + rows + '</div>'
              +   '<div class="pos-cart-price mt-1">Line ' + money(lineTotal)
              +     ' · <span class="' + (profit < 0 ? 'text-danger' : 'text-success') + '">profit ' + money(profit) + '</span>'
              +     (c.retail > 0 && Math.abs((c.retail % 1) - 0.5) < 0.001 ? ' · retail ' + formatHalfQty(c.retail) : '')
              +     (c.retailPack > 0 && Math.abs((c.retailPack % 1) - 0.5) < 0.001 ? ' · box ' + formatHalfQty(c.retailPack) : '')
              +     (c.wholesale > 0 && Math.abs((c.wholesale % 1) - 0.5) < 0.001 ? ' · wholesale ' + formatHalfQty(c.wholesale) : '')
              +     ((PC.hasRetailPack(p) || PC.hasWholesalePack(p)) ? ' <span class="text-muted small">· stock used ' + PC.stockUsed(p, c) + '</span>' : '') + '</div>'
              + '</div>'
              + '<button type="button" class="pos-cart-del" data-del="' + id + '" title="Remove item"><i class="fas fa-trash"></i></button>';
            wrap.appendChild(line);
        });
    }

    var empty = !cartHasItems();
    document.getElementById('holdBtn').disabled = empty;
    document.getElementById('checkoutBtn').disabled = empty;
    document.getElementById('cartInput').value = JSON.stringify(serializeCart());
    updateTotals();
}

function qtyInputField(input) {
    if (input.dataset.retailPackQty) return { id: input.dataset.retailPackQty, field: 'retailPack' };
    if (input.dataset.retailQty) return { id: input.dataset.retailQty, field: 'retail' };
    if (input.dataset.wholesaleQty) return { id: input.dataset.wholesaleQty, field: 'wholesale' };
    return null;
}
function syncTypedQty(input) {
    var info = qtyInputField(input);
    if (info) setFieldQty(info.id, info.field, input.value);
}
document.getElementById('discountInput').addEventListener('input', updateTotals);
var extraChargeInput = document.getElementById('extraChargeInput');
if (extraChargeInput) extraChargeInput.addEventListener('input', updateTotals);
document.getElementById('saleModeTabs').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-sale-mode]');
    if (!btn) return;
    document.querySelectorAll('[data-sale-mode]').forEach(function (x) { x.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('saleType').value = btn.dataset.saleMode || 'retail';
    updateProductModeDisplay();
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
    if (t.dataset.incRetailPack) bump(t.dataset.incRetailPack, 'retailPack', 0.5);
    else if (t.dataset.decRetailPack) bump(t.dataset.decRetailPack, 'retailPack', -0.5);
    else if (t.dataset.halfRetailPack) bump(t.dataset.halfRetailPack, 'retailPack', 0.5);
    else if (t.dataset.incRetail) bump(t.dataset.incRetail, 'retail', 0.5);
    else if (t.dataset.decRetail) bump(t.dataset.decRetail, 'retail', -0.5);
    else if (t.dataset.halfRetail) bump(t.dataset.halfRetail, 'retail', 0.5);
    else if (t.dataset.incWholesale) bump(t.dataset.incWholesale, 'wholesale', 0.5);
    else if (t.dataset.decWholesale) bump(t.dataset.decWholesale, 'wholesale', -0.5);
    else if (t.dataset.halfWholesale) bump(t.dataset.halfWholesale, 'wholesale', 0.5);
    else if (t.dataset.del) {
        var delId = String(t.dataset.del);
        delete cart[delId];
        cartOrder = cartOrder.filter(function(x) { return x !== delId; });
        render();
    }
});
document.getElementById('cartRows').addEventListener('change', function (e) {
    var input = e.target.closest('[data-retail-qty], [data-retail-pack-qty], [data-wholesale-qty]');
    if (input) syncTypedQty(input);
});
document.getElementById('cartRows').addEventListener('input', function (e) {
    var commissionInput = e.target.closest('[data-commission-price]');
    if (commissionInput) {
        var commissionId = commissionInput.dataset.commissionPrice;
        var product = PRODUCTS[commissionId];
        var entered = parseFloat(commissionInput.value);
        ensureCart(commissionId).customUnitPrice = product && entered >= product.price ? entered : null;
        document.getElementById('cartInput').value = JSON.stringify(serializeCart());
        updateTotals();
        return;
    }
    var input = e.target.closest('[data-retail-qty], [data-retail-pack-qty], [data-wholesale-qty]');
    if (!input) return;
    var info = qtyInputField(input);
    if (!info) return;
    var p = PRODUCTS[info.id]; if (!p) return;
    var c = ensureCart(info.id);
    var val = PC.clampField(p, c, info.field, input.value);
    if (val !== (parseFloat(input.value) || 0)) input.value = val;
    c[info.field] = val;
    pruneCart(info.id);
    var empty = !cartHasItems();
    document.getElementById('holdBtn').disabled = empty;
    document.getElementById('checkoutBtn').disabled = empty;
    document.getElementById('cartInput').value = JSON.stringify(serializeCart());
    updateTotals();
});
document.getElementById('cartRows').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.closest('[data-retail-qty], [data-retail-pack-qty], [data-wholesale-qty]')) {
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

/* Holding Sale Handling */
var holdBtn = document.getElementById('holdBtn');
var holdSaleModal = document.getElementById('holdSaleModal');
var holdReferenceInput = document.getElementById('holdReferenceInput');
var btnConfirmHold = document.getElementById('btnConfirmHold');

if (holdBtn) {
    holdBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var custInput = document.getElementById('customerName');
        var custVal = custInput ? custInput.value.trim() : '';
        if (holdSaleModal && window.bootstrap) {
            if (holdReferenceInput) {
                holdReferenceInput.value = custVal;
            }
            new bootstrap.Modal(holdSaleModal).show();
            setTimeout(function () {
                if (holdReferenceInput) { holdReferenceInput.focus(); }
            }, 300);
        } else {
            document.getElementById('formAction').value = 'hold';
            document.getElementById('orderForm').submit();
        }
    });
}

if (btnConfirmHold) {
    btnConfirmHold.addEventListener('click', function () {
        var note = holdReferenceInput ? holdReferenceInput.value.trim() : '';
        var custInput = document.getElementById('customerName');
        if (custInput && note) {
            custInput.value = note;
        }
        document.getElementById('formAction').value = 'hold';
        document.getElementById('orderForm').submit();
    });
}

/* Resume & Discard from Held Sales Modal */
document.querySelectorAll('.js-resume-held').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = btn.dataset.id;
        var shopUrl = <?php echo json_encode($shopUrl); ?>;
        if (cartHasItems()) {
            if (confirm('You already have items in the current order.\n\nWould you like to put the current order on hold before resuming this one?\n\n- Click OK to HOLD current sale & resume\n- Click Cancel to discard current cart and switch')) {
                document.getElementById('formAction').value = 'hold';
                var afterInput = document.createElement('input');
                afterInput.type = 'hidden';
                afterInput.name = 'resume_after';
                afterInput.value = id;
                document.getElementById('orderForm').appendChild(afterInput);
                document.getElementById('orderForm').submit();
                return;
            }
        }
        window.location.href = shopUrl + '?resume=' + id;
    });
});

document.querySelectorAll('.js-discard-held').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = btn.dataset.id;
        if (confirm('Permanently discard held sale #' + id + '?')) {
            document.getElementById('discardHeldId').value = id;
            document.getElementById('discardHeldForm').submit();
        }
    });
});

/* Clear Sale Button */
var clearSaleBtn = document.getElementById('clearSaleBtn');
if (clearSaleBtn) {
    clearSaleBtn.addEventListener('click', function () {
        if (cartHasItems()) {
            if (!confirm('Start a new sale? Any unsaved items in this order will be cleared.')) {
                return;
            }
        }
        window.location.href = <?php echo json_encode($shopUrl); ?>;
    });
}

/* Mobile Offcanvas Drawer Handlers */
var posSide = document.getElementById('posSide');
var mobileOpenCartBtn = document.getElementById('mobileOpenCartBtn');
var openMobileCartFromBar = document.getElementById('openMobileCartFromBar');
var closeMobileCartBtn = document.getElementById('closeMobileCartBtn');

function openMobileDrawer() {
    if (posSide) { posSide.classList.add('mobile-open'); }
}
function closeMobileDrawer() {
    if (posSide) { posSide.classList.remove('mobile-open'); }
}
if (mobileOpenCartBtn) mobileOpenCartBtn.addEventListener('click', openMobileDrawer);
if (openMobileCartFromBar) openMobileCartFromBar.addEventListener('click', openMobileDrawer);
if (closeMobileCartBtn) closeMobileCartBtn.addEventListener('click', closeMobileDrawer);

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
    if (this.dataset.submitting === '1') { e.preventDefault(); return; }
    if (!cartHasItems()) { e.preventDefault(); alert('Add at least one product.'); return; }
    if (document.getElementById('formAction').value === 'pay') {
        var m = payMethod(), t = total().total;
        if (m === 'cash' && (parseFloat(document.getElementById('cashGivenInput').value) || 0) < t) { e.preventDefault(); alert('Cash given is less than the total.'); return; }
        if (m === 'split') {
            var cp = parseFloat(document.getElementById('cashPortionInput').value) || 0, mp = parseFloat(document.getElementById('mpesaPortionInput').value) || 0;
            if (Math.abs(cp + mp - t) > 0.01) { e.preventDefault(); alert('Cash and M-Pesa portions must add up to the total.'); return; }
        }
        if(!navigator.onLine&&window.OfflinePOS){
            e.preventDefault();
            var payload={items:serializeCart(),customer_name:document.querySelector('[name=table_name]').value||'Offline customer',sale_type:document.getElementById('saleType').value,payment_method:payMethod(),vat_rate:parseFloat(document.getElementById('vatRateInput').value)||0,vat_inclusive:document.getElementById('vatInclusiveInput').value==='1'};
            OfflinePOS.queueSale(payload).then(function(){alert('Sale saved offline. It will sync automatically when internet returns.');location.reload();});
            return;
        }
    }
    this.dataset.submitting = '1';
    this.querySelectorAll('button[type=submit]').forEach(function(button){button.disabled=true;});
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
        fetch(<?php echo json_encode(public_url('api/inventory/pos_barcode.php')); ?> + '?code=' + encodeURIComponent(code))
          .then(function(r){return r.json();})
          .then(function(data){
            var p=data.item||(data.items&&data.items[0]);if(!p){flashScan('No in-stock product with that barcode.',false);return;}
            p.stock=parseFloat(p.stock!=null?p.stock:p.balance)||0;
            p.unitsPerPack=parseFloat(p.unitsPerPack!=null?p.unitsPerPack:p.unitsInPack)||1;
            p.packUnit=p.packUnit||p.package||'pack';
            p.packPrice=parseFloat(p.packPrice!=null?p.packPrice:p.packSize)||0;
            p.packageBuying=parseFloat(p.packageBuying!=null?p.packageBuying:p.packagingbying)||0;
            var id=String(p.id);PRODUCTS[id]=PRODUCTS[id]||p;BARCODES[code]=id;
            if(PC.stockUsed(PRODUCTS[id],cart[id]||PC.buckets())>=PRODUCTS[id].stock){flashScan(p.name+' — no more in stock.',false);return;}
            add(id);flashScan(p.name+' added.',true);
          }).catch(function(){flashScan('Could not read barcode. Try again.',false);});
    });
    document.addEventListener('click', function (e) {
        if (e.target === barcodeScan || e.target.closest('input, textarea, select, option, label, button, .btn-group, .modal')) { return; }
        barcodeScan.focus();
    });
}

render();
updateProductModeDisplay();
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
