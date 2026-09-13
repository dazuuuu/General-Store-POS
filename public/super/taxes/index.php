<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();
$pdo=Database::pdo(); new Models\ProductModel($pdo); $tid=TenantContext::tenantId();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $up=$pdo->prepare('UPDATE products SET tax_rate=? WHERE id=? AND tenant_id=?');
  foreach((array)($_POST['tax_rate']??[]) as $id=>$rate) $up->execute([trim((string)$rate)===''?null:max(0,(float)$rate),(int)$id,$tid]);
  $_SESSION['flash']['success']='Product tax rates updated.';header('Location: '.public_url('super/taxes/'));exit;
}
$st=$pdo->prepare("SELECT p.id,p.name,p.quantity,p.unit,p.retail_price,p.selling_price,p.tax_rate,c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.tenant_id=? AND p.status IN ('active','draft','archived') ORDER BY p.name");$st->execute([$tid]);$products=$st->fetchAll();
$tenant=(new Models\TenantModel($pdo))->find($tid)?:[];$page_title='Taxes';ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h5 fw-bold mb-1">Product taxes</h1><p class="text-muted small mb-0">All available database products and their tax rate. Blank uses the shop VAT rate (<?php echo number_format((float)($tenant['vat_rate']??0),2);?>%).</p></div><a class="btn btn-outline-secondary btn-sm" href="<?php echo public_url('super/settings/');?>">VAT settings</a></div>
<form method="post" class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr class="small text-muted text-uppercase"><th>Product</th><th>Category</th><th class="text-end">Available</th><th class="text-end">Selling price</th><th style="width:160px;">Tax rate %</th></tr></thead><tbody>
<?php if(!$products):?><tr><td colspan="5" class="text-center text-muted py-5">No products available.</td></tr><?php else:foreach($products as $p):?><tr><td class="fw-semibold"><?php echo htmlspecialchars($p['name']);?></td><td class="small"><?php echo htmlspecialchars($p['category_name']?:'Uncategorised');?></td><td class="text-end"><?php echo rtrim(rtrim(number_format((float)$p['quantity'],2),'0'),'.').' '.htmlspecialchars($p['unit']);?></td><td class="text-end">KES <?php echo number_format((float)($p['retail_price']?:$p['selling_price']),2);?></td><td><div class="input-group"><input name="tax_rate[<?php echo (int)$p['id'];?>]" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars((string)($p['tax_rate']??''));?>" placeholder="<?php echo htmlspecialchars((string)($tenant['vat_rate']??0));?>"><span class="input-group-text">%</span></div></td></tr><?php endforeach;endif;?></tbody></table></div>
<div class="p-3 border-top"><button class="btn btn-primary">Save product tax rates</button></div></form>
<?php $content=ob_get_clean();include __DIR__.'/../../templates/tenants/layout.php';
