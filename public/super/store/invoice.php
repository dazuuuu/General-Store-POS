<?php
require_once __DIR__ . '/../../../app/app.php';
// Same staff who transfer from Store must be able to open/print the saved invoice.
PageGuard::capability(Capabilities::STOCK_ENTER);

$pdo = Database::pdo();
$SP = new Models\StoreProductModel($pdo);
$id = (int) ($_GET['id'] ?? 0);
$invoice = $id > 0 ? $SP->invoice($id) : null;
if (!$invoice) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}
$items = $SP->invoiceItems($id);
$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId()) ?: [];
$shop = $tenant['name'] ?? ReceiptFooter::SHOP_NAME;
$logo = Branding::loginLogo();
$phone = trim((string) ($tenant['phone'] ?? ReceiptFooter::SHOP_PHONE));
$poBox = trim((string) ($tenant['po_box'] ?? ReceiptFooter::SHOP_BOX));
$location = trim((string) ($tenant['address'] ?? ReceiptFooter::SHOP_LOCATION));
$currency = $tenant['currency'] ?? 'KES';
$money = fn($n) => $currency . ' ' . number_format((float) $n, 2);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f1f5f9;margin:0;padding:24px;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;}
.sheet{background:#fff;max-width:380px;margin:0 auto 18px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:24px;position:relative;overflow:hidden;}
.sheet::before{content:"";position:absolute;inset:42px 18px;background:url('<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>') center 48%/82% auto no-repeat;opacity:.38;pointer-events:none;}
.sheet>*{position:relative;z-index:1;}
.sheet,.sheet *{font-weight:900!important;color:#000!important;}
.actions{max-width:380px;margin:0 auto;}
@page{margin:8mm;}
@media print{body{background:#fff;padding:0;margin:0}.actions{display:none!important}.sheet{box-shadow:none;border-radius:0;margin:0 auto!important;width:80mm;max-width:80mm;padding:10px 12px;font-size:16px!important;}}
</style>
</head>
<body>
<div class="sheet">
  <div style="text-align:center;border-bottom:2px dashed #000;padding-bottom:10px;margin-bottom:10px;">
    <img src="<?php echo htmlspecialchars($logo); ?>" alt="" style="max-height:104px;max-width:285px;object-fit:contain;margin-bottom:8px;">
    <div style="font-size:24px;"><?php echo htmlspecialchars($shop); ?></div>
    <?php if ($poBox): ?><div style="font-size:15px;"><?php echo htmlspecialchars($poBox); ?></div><?php endif; ?>
    <?php if ($location): ?><div style="font-size:15px;"><?php echo htmlspecialchars($location); ?></div><?php endif; ?>
    <?php if ($phone): ?><div style="font-size:16px;">TEL: <?php echo htmlspecialchars($phone); ?></div><?php endif; ?>
    <div style="font-size:16px;margin-top:5px;">Internal transfer <?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
    <div style="font-size:14px;">Store warehouse → shop Inventory</div>
    <div style="font-size:15px;"><?php echo date('j M Y, g:i a', strtotime($invoice['created_at'])); ?></div>
    <?php if (!empty($invoice['invoice_to'])): ?><div style="font-size:15px;">To: <?php echo htmlspecialchars($invoice['invoice_to']); ?></div><?php endif; ?>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:16px;">
    <tr style="border-bottom:1px solid #000;"><th style="text-align:left;">NO.</th><th style="text-align:left;">ITEM</th><th style="text-align:center;">PACKAGES</th><th style="text-align:right;">AMT</th></tr>
    <?php foreach ($items as $i => $it):
      $pkgQty = (float) ($it['package_quantity'] ?? 0);
      $pkgUnit = trim((string) ($it['package_unit'] ?? '')) ?: 'pkg';
      $inside = (float) ($it['units_per_package'] ?? 0);
      $itemQty = (float) ($it['quantity'] ?? 0);
      if ($pkgQty <= 0 && $inside > 0) {
          $pkgQty = round($itemQty / $inside, 2);
      }
    ?>
    <tr>
      <td style="padding:4px 4px 4px 0;vertical-align:top;"><?php echo $i + 1; ?></td>
      <td style="padding:4px 4px 4px 0;">
        <?php echo htmlspecialchars($it['product_name']); ?>
        <?php if ($inside > 0): ?>
          <div style="font-size:13px;"><?php echo rtrim(rtrim(number_format($itemQty, 2), '0'), '.'); ?> <?php echo htmlspecialchars($it['unit'] ?? ''); ?> (<?php echo rtrim(rtrim(number_format($inside, 2), '0'), '.'); ?>/<?php echo htmlspecialchars($pkgUnit); ?>)</div>
        <?php endif; ?>
      </td>
      <td style="padding:4px;text-align:center;"><?php echo rtrim(rtrim(number_format($pkgQty, 2), '0'), '.'); ?> <?php echo htmlspecialchars($pkgUnit); ?><?php echo abs($pkgQty - 1) < 0.0001 ? '' : 's'; ?></td>
      <td style="padding:4px 0 4px 4px;text-align:right;"><?php echo number_format((float) $it['line_total'], 2); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <table style="width:100%;border-collapse:collapse;font-size:18px;border-top:2px dashed #000;margin-top:8px;">
    <tr><td style="padding-top:8px;font-size:20px;">Total</td><td style="padding-top:8px;text-align:right;font-size:20px;"><?php echo $money($invoice['total']); ?></td></tr>
  </table>
  <?php if (!empty($invoice['notes'])): ?><div style="border-top:2px dashed #000;margin-top:10px;padding-top:8px;text-align:center;"><?php echo htmlspecialchars($invoice['notes']); ?></div><?php endif; ?>
</div>
<div class="actions">
  <button class="btn btn-primary w-100 mb-2" onclick="window.print()">Print / Download invoice</button>
  <a class="btn btn-link w-100" href="<?php echo public_url('super/store/'); ?>">Back to Store warehouse</a>
</div>
</body>
</html>
