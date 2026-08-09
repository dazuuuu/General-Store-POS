<?php
// public/staff/dashboard/index.php — Home: walk-in POS. Build a cart and
// either Hold it, or Checkout → Pay Now (paid immediately, no invoice/tab —
// that's what Orders is for, for customers staying to drink).
require_once __DIR__ . '/../../../app/app.php';
PageGuard::staff();

$pdo = Database::pdo();
$canSell = TenantContext::can(Capabilities::SALES_RECORD);

if (!$canSell) {
    // No selling permission — a light landing page instead of the POS screen.
    $__tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
    $page_title = 'Home';
    $who = $_SESSION['username'] ?? 'there';
    ob_start();
    ?>
    <div class="card border-0 shadow-sm" style="border-radius:16px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Hi <?php echo htmlspecialchars($who); ?></h2>
        <p class="text-muted mb-0">
          You're signed in at <strong><?php echo htmlspecialchars($__tenant['name'] ?? 'your shop'); ?></strong>.
          <?php if (TenantContext::can(Capabilities::PAYMENTS_PROCESS)): ?>
            Use <a href="<?php echo public_url('staff/payments/'); ?>">Payments</a> to settle invoices.
          <?php endif; ?>
        </p>
      </div>
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../../templates/staff/layout.php';
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
$byId = [];
foreach ($products as $p) { $byId[(int) $p['id']] = $p; }

$error = '';
$cartJson = '[]';
$customerName = 'Walk-in Customer';
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
                $cart[] = ['product_id' => (int) $it['product_id'], 'quantity' => (float) $it['quantity']];
            }
        }
        $cartJson = json_encode($cart);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'pay';
    $cart = json_decode($_POST['cart'] ?? '[]', true);
    $cartJson = $_POST['cart'] ?? '[]';
    $customerName = trim($_POST['table_name'] ?? '') !== '' ? trim($_POST['table_name']) : 'Walk-in Customer';
    $heldOrderId = (int) ($_POST['held_order_id'] ?? 0);
    if (!is_array($cart)) { $cart = []; }
    $items = [];
    foreach ($cart as $c) {
        $items[] = ['product_id' => (int) ($c['product_id'] ?? 0), 'quantity' => (float) ($c['quantity'] ?? 0)];
    }

    if ($action === 'hold') {
        $res = $HO->hold(['customer_name' => $customerName, 'staff_id' => TenantContext::userId(), 'items' => $items]);
        if ($res['ok']) {
            if ($heldOrderId > 0) { $HO->discard($heldOrderId); }
            $_SESSION['flash']['success'] = 'Sale held for ' . $customerName . '.';
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
            if ($prod) { $subtotal += (float) ($prod['retail_price'] ?: $prod['selling_price']) * $it['quantity']; }
        }
        $subtotal = round($subtotal, 2);
        // Negotiated discount — clamp so a typo can't produce a negative total.
        $discount = min(max(round((float) ($_POST['discount_amount'] ?? 0), 2), 0), $subtotal);
        $saleType = (($_POST['sale_type'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail';
        $postVatRate = max(0, round((float) ($_POST['vat_rate'] ?? $vatRate), 2));
        $postVatInc = array_key_exists('vat_inclusive', $_POST) ? (bool) (int) $_POST['vat_inclusive'] : $vatInclusive;
        $priced = Pricing::totals($subtotal, $discount, $postVatRate, $postVatInc);
        $total = $priced['total'];
        $method = $_POST['payment_method'] ?? '';
        $tendered = round((float) ($_POST['amount_tendered'] ?? 0), 2);
        $cashAmt  = round((float) ($_POST['cash_amount'] ?? 0), 2);
        $mpesaAmt = round((float) ($_POST['mpesa_amount'] ?? 0), 2);
        $allowedPay = ['cash', 'mpesa', 'split', 'card', 'bank', 'credit'];

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
                'vat_rate' => $postVatRate,
                'vat_inclusive' => $postVatInc,
                'sale_type' => $saleType,
            ]);
            if (!$openRes['ok']) {
                $error = $openRes['errors']['_'] ?? 'Could not record this sale.';
            } else {
                $payRes = $OR->markPaid($openRes['order_id'], [
                    'method' => $method, 'cash_amount' => $cashAmt, 'mpesa_amount' => $mpesaAmt, 'amount_tendered' => $tendered,
                ], TenantContext::userId());
                if ($payRes['ok']) {
                    if ($heldOrderId > 0) { $HO->discard($heldOrderId); }
                    $_SESSION['flash']['success'] = 'Sale recorded — ' . $openRes['receipt_number'] . '.';
                    header('Location: ' . public_url('staff/orders/receipt.php?id=' . $openRes['order_id']));
                    exit;
                }
                // Rare: opened fine but settling failed. It's now a normal open
                // tab, recoverable from Credit sales/Payments — not lost.
                $error = ($payRes['error'] ?? 'Could not complete the payment.') . ' The sale was saved as a credit sale — settle it from Payments.';
            }
        }
    }
}

$page_title = 'Home';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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
             data-price="<?php echo $price; ?>" data-stock="<?php echo (float) $p['quantity']; ?>"
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
              KES <?php echo number_format($price, 0); ?>
            <?php else: ?>
              KES <?php echo number_format($price, 0); ?>
            <?php endif; ?>
          </div>
          <button type="button" class="pos-add"><i class="fas fa-cart-plus me-1"></i>Add</button>
        </div>
      <?php endforeach; ?>
      <div id="noMatch" class="text-muted small text-center py-4" style="display:none;grid-column:1/-1;"><i class="fas fa-search me-1"></i>No products match.</div>
    </div>
  </div>

  <aside class="pos-side">
    <h2 class="pos-side-title">Sale Details</h2>
    <div class="pos-customer">
      <div class="pos-customer-icon"><i class="fas fa-user"></i></div>
      <input type="text" name="table_name" id="customerName" class="pos-customer-input" value="<?php echo htmlspecialchars($customerName); ?>">
    </div>
    <div class="pos-cart" id="cartRows"><div class="text-muted small text-center py-4">Tap a product to add it.</div></div>

    <div class="pos-totals">
      <div class="d-flex justify-content-between"><span>Sub Total</span><span id="subtotalOut">KES 0</span></div>
      <div class="d-flex justify-content-between align-items-center py-1">
        <span>Discount <span class="text-muted small">(if they negotiate)</span></span>
        <input type="number" step="0.01" min="0" id="discountInput" name="discount_amount" class="form-control form-control-sm" style="width:100px;text-align:right;" placeholder="0" value="0">
      </div>
      <div class="d-flex justify-content-between align-items-center py-1">
        <span>VAT <span class="text-muted small" id="vatRateLabel"></span></span>
        <span id="vatOut">KES 0</span>
      </div>
      <div class="d-flex justify-content-between align-items-center py-1">
        <span>Sale type</span>
        <select name="sale_type" id="saleType" class="form-select form-select-sm" style="width:120px;">
          <option value="retail">Retail</option>
          <option value="wholesale">Wholesale</option>
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
      <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
        <span class="text-muted small">Total Payable</span>
        <span class="fw-bold fs-5" id="payableOut">KES 0</span>
      </div>
      <button type="submit" class="pos-btn pos-btn-primary w-100">Pay Now</button>
    </div>
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
.pos-add{width:100%;border:0;border-radius:10px;background:var(--pos-green);color:#fff;padding:8px 0;font-weight:600;font-size:.82rem;}
.pos-add:hover{background:var(--pos-green-dark);}

.pos-side{background:#fff;border:1px solid #eef0f4;border-radius:16px;padding:20px;position:sticky;top:20px;max-height:calc(100vh - 40px);overflow-y:auto;}
.pos-side-title{font-size:1.1rem;font-weight:800;margin-bottom:14px;}
.pos-customer{display:flex;align-items:center;gap:10px;background:#f7f7fb;border-radius:12px;padding:10px 12px;margin-bottom:6px;}
.pos-customer-icon{width:34px;height:34px;border-radius:50%;background:var(--pos-green-light);color:var(--pos-green);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.pos-customer-input{border:0;background:transparent;flex:1;font-weight:600;font-size:.9rem;}
.pos-customer-input:focus{outline:none;}
.pos-cart{max-height:280px;overflow-y:auto;margin:14px 0;}
.pos-cart-line{display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f7;}
.pos-cart-line img, .pos-cart-line .ph{width:38px;height:38px;border-radius:8px;object-fit:cover;background:#f3f4f7;display:flex;align-items:center;justify-content:center;color:#d7d9df;flex-shrink:0;}
.pos-cart-name{font-weight:600;font-size:.85rem;color:#1f2330;}
.pos-cart-price{color:#9aa0ac;font-size:.76rem;}
.pos-qty{display:flex;align-items:center;gap:6px;}
.pos-qty button{width:24px;height:24px;border-radius:6px;border:1px solid #eef0f4;background:#fff;font-weight:700;line-height:1;}
.pos-cart-del{color:#64748b;background:none;border:0;font-size:.85rem;}
.pos-totals{border-top:1px dashed #eef0f4;padding-top:12px;font-size:.9rem;color:#5b6070;}
.pos-total-line{font-weight:800;font-size:1.05rem;color:#1f2330;margin-top:6px;}
.pos-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px;}
.pos-btn{border-radius:12px;padding:12px 0;font-weight:700;font-size:.9rem;border:1px solid #eef0f4;}
.pos-btn-outline{background:#fff;color:#5b6070;}
.pos-btn-primary{background:var(--pos-green);border-color:var(--pos-green);color:#fff;}
.pos-btn:disabled{opacity:.5;}
@media (max-width:900px){
  .pos-grid{grid-template-columns:1fr;}
  /* Cart stays put — pinned to the top of the screen, not lost below a long
     product list. It scrolls its own contents (bounded height) while the
     product grid scrolls with the page underneath it. */
  .pos-side{position:sticky;top:0;order:-1;max-height:46vh;z-index:20;box-shadow:0 6px 16px rgba(16,24,40,.1);margin-bottom:14px;}
}
</style>

<script>
var PRODUCTS = {};
var BARCODES = {};
document.querySelectorAll('.pos-card').forEach(function (el) {
    var img = el.querySelector('.pos-card-img img');
    PRODUCTS[el.dataset.id] = { name: el.dataset.name, price: parseFloat(el.dataset.price), stock: parseFloat(el.dataset.stock), img: img ? img.getAttribute('src') : null };
    if (el.dataset.barcode) { BARCODES[el.dataset.barcode] = el.dataset.id; }
});
var cart = {};
try { (JSON.parse(<?php echo json_encode($cartJson); ?>) || []).forEach(function (c) { cart[c.product_id] = c.quantity; }); } catch (e) {}
function money(n) { return 'KES ' + n.toLocaleString('en-KE', {maximumFractionDigits: 0}); }

function setQty(id, val) {
    var p = PRODUCTS[id]; if (!p) return;
    val = Math.round(val);
    if (val <= 0) { delete cart[id]; render(); return; }
    if (val > p.stock) { val = p.stock; }
    cart[id] = val; render();
}
function add(id) { setQty(id, (cart[id] || 0) + 1); }
function subtotal() { var t = 0; for (var id in cart) { t += PRODUCTS[id].price * cart[id]; } return t; }
function total() {
    var sub = subtotal();
    var d = parseFloat(document.getElementById('discountInput').value) || 0;
    if (d < 0) d = 0;
    if (d > sub) d = sub;
    var net = sub - d;
    var rate = parseFloat(document.getElementById('vatRateInput').value) || 0;
    var inclusive = document.getElementById('vatInclusiveInput').value === '1';
    var vat = 0;
    if (rate > 0) {
        vat = inclusive ? (net - (net / (1 + rate / 100))) : (net * rate / 100);
        if (!inclusive) net = net + vat;
    }
    return { total: Math.round(net * 100) / 100, vat: Math.round(vat * 100) / 100 };
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
    wrap.innerHTML = ids.length ? '' : '<div class="text-muted small text-center py-4">Tap a product to add it.</div>';
    ids.forEach(function (id) {
        var p = PRODUCTS[id], qty = cart[id];
        var line = document.createElement('div');
        line.className = 'pos-cart-line';
        line.innerHTML = (p.img ? '<img src="' + p.img + '">' : '<div class="ph"><i class="fas fa-box"></i></div>')
          + '<div class="flex-grow-1"><div class="pos-cart-name">' + p.name + '</div><div class="pos-cart-price">' + money(p.price) + '</div></div>'
          + '<div class="pos-qty"><button type="button" data-dec="' + id + '">−</button><span>' + qty + '</span><button type="button" data-inc="' + id + '">+</button></div>'
          + '<button type="button" class="pos-cart-del" data-del="' + id + '"><i class="fas fa-trash"></i></button>';
        wrap.appendChild(line);
    });
    document.getElementById('holdBtn').disabled = ids.length === 0;
    document.getElementById('checkoutBtn').disabled = ids.length === 0;
    document.getElementById('cartInput').value = JSON.stringify(ids.map(function (id) { return { product_id: parseInt(id, 10), quantity: cart[id] }; }));
    updateTotals();
}
document.getElementById('discountInput').addEventListener('input', updateTotals);

document.querySelectorAll('.pos-card .pos-add').forEach(function (b) { b.addEventListener('click', function () { add(b.closest('.pos-card').dataset.id); }); });
document.getElementById('cartRows').addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.inc) add(t.dataset.inc);
    else if (t.dataset.dec) setQty(t.dataset.dec, (cart[t.dataset.dec] || 0) - 1);
    else if (t.dataset.del) { delete cart[t.dataset.del]; render(); }
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
document.getElementById('checkoutBtn').addEventListener('click', function () {
    document.getElementById('formAction').value = 'pay';
    document.getElementById('cartButtons').style.display = 'none';
    document.getElementById('payPanel').style.display = 'block';
});

function payMethod() { return document.querySelector('input[name=pm]:checked').value; }
function updatePayFields() {
    var m = payMethod(), t = total().total;
    var needsCash = (m === 'cash' || m === 'split');
    document.getElementById('cashBox').style.display = needsCash ? 'flex' : 'none';
    document.getElementById('splitBox').style.display = m === 'split' ? 'flex' : 'none';
    var due = m === 'split' ? (parseFloat(document.getElementById('cashPortionInput').value) || 0) : t;
    var given = parseFloat(document.getElementById('cashGivenInput').value) || 0;
    document.getElementById('balanceOut').textContent = needsCash ? (given >= due ? money(given - due) : 'short') : '—';

    document.getElementById('paymentMethod').value = m;
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
['cashGivenInput', 'cashPortionInput', 'mpesaPortionInput'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', updatePayFields);
});

document.getElementById('orderForm').addEventListener('submit', function (e) {
    if (Object.keys(cart).length === 0) { e.preventDefault(); alert('Add at least one product.'); return; }
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
    document.getElementById('vatRateInput').value = rate;
    document.getElementById('vatInclusiveInput').value = inclusive ? '1' : '0';
    var label = document.getElementById('vatRateLabel');
    if (label) label.textContent = rate > 0 ? '(' + rate + '%' + (inclusive ? ' incl.' : ' excl.') + ')' : '(off)';
})();

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
        if (p && p.stock <= (cart[id] || 0)) { flashScan(p.name + ' — no more in stock.', false); return; }
        add(id);
        flashScan((p ? p.name : 'Product') + ' added.', true);
    });
    // Keep the scanner's keystrokes landing here even after other clicks,
    // as long as no other field is being typed into.
    document.addEventListener('click', function (e) {
        if (e.target === barcodeScan || e.target.closest('input, textarea, button')) { return; }
        barcodeScan.focus();
    });
}

render();
applyFilters();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
