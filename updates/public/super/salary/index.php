<?php
header('Location: ../payroll/');
exit;
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();
$pdo=Database::pdo(); $F=new Models\FinanceModel($pdo); $error='';
$st=$pdo->prepare("SELECT id,username FROM users WHERE tenant_id=? AND is_active=1 ORDER BY username"); $st->execute([TenantContext::tenantId()]); $staff=$st->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $staffId=(int)($_POST['staff_id']??0); $person='Staff';
  foreach($staff as $s) if((int)$s['id']===$staffId) $person=$s['username'];
  $res=$F->create(['entry_type'=>'expense','category'=>'Staff Salary','description'=>$person.(!empty($_POST['period_label'])?' · '.trim($_POST['period_label']):''),'amount'=>$_POST['amount']??0,'payment_method'=>$_POST['payment_method']??'cash','reference'=>'STAFF-'.$staffId,'entry_date'=>$_POST['entry_date']??date('Y-m-d'),'created_by'=>TenantContext::userId()]);
  if($res['ok']){$_SESSION['flash']['success']='Salary payment recorded in expenses and finances.';header('Location: '.public_url('super/salary/'));exit;} $error=$res['errors']['amount']??'Could not record salary.';
}
$hist=$pdo->prepare("SELECT * FROM finance_entries WHERE tenant_id=? AND category='Staff Salary' ORDER BY entry_date DESC,id DESC LIMIT 100");$hist->execute([TenantContext::tenantId()]);$rows=$hist->fetchAll();
$page_title='Staff Salary';ob_start();
?>
<div class="mb-4"><h1 class="h5 fw-bold mb-1">Staff Salary</h1><p class="text-muted small mb-0">Salary payments are automatically included as expenses in Finances.</p></div>
<?php if($error):?><div class="alert alert-danger"><?php echo htmlspecialchars($error);?></div><?php endif;?>
<div class="row g-4"><div class="col-lg-4"><form method="post" class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h6 fw-bold">Record salary payment</h2>
<label class="form-label mt-2">Staff</label><select name="staff_id" class="form-select" required><?php foreach($staff as $s):?><option value="<?php echo (int)$s['id'];?>"><?php echo htmlspecialchars($s['username']);?></option><?php endforeach;?></select>
<label class="form-label mt-3">Amount</label><input name="amount" type="number" step="0.01" min="0" class="form-control" required>
<label class="form-label mt-3">Salary period</label><input name="period_label" class="form-control" placeholder="e.g. September 2026">
<label class="form-label mt-3">Payment date</label><input name="entry_date" type="date" value="<?php echo date('Y-m-d');?>" class="form-control">
<label class="form-label mt-3">Method</label><select name="payment_method" class="form-select"><option>cash</option><option>mpesa</option><option>bank</option></select>
<button class="btn btn-primary w-100 mt-4">Record salary</button></div></form></div>
<div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Date</th><th>Staff / period</th><th>Method</th><th class="text-end">Amount</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="4" class="text-center text-muted py-5">No salary payments yet.</td></tr><?php else:foreach($rows as $r):?><tr><td><?php echo htmlspecialchars(date('j M Y',strtotime($r['entry_date'])));?></td><td><?php echo htmlspecialchars($r['description']);?></td><td><?php echo htmlspecialchars($r['payment_method']);?></td><td class="text-end fw-bold">KES <?php echo number_format((float)$r['amount'],2);?></td></tr><?php endforeach;endif;?></tbody></table></div></div></div></div>
<?php $content=ob_get_clean();include __DIR__.'/../../templates/tenants/layout.php';
