<?php
require_once __DIR__.'/../../../app/app.php';
PageGuard::tenant();
$P=new Models\PayrollModel(Database::pdo());$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  $res=$action==='add_employee'?$P->addEmployee($_POST):$P->pay((int)($_POST['employee_id']??0),$_POST,TenantContext::userId());
  if($res['ok']){$_SESSION['flash']['success']=$action==='add_employee'?'Employee added to payroll.':'Employee paid; salary and commission recorded in Finances.';header('Location: '.public_url('super/payroll/'));exit;}
  $error=$res['error']??'Could not save payroll.';
}
$employees=$P->employees();$payments=$P->payments();$users=$P->availableUsers();$page_title='Salary & Payroll';ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h5 fw-bold mb-1">Salary & Payroll</h1><p class="small text-muted mb-0">Manage employees, their salary, payday and commission. Employees do not need POS accounts.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?php echo htmlspecialchars($error);?></div><?php endif;?>
<div class="row g-3 mb-4">
<div class="col-lg-4"><form method="post" class="card border-0 shadow-sm h-100"><input type="hidden" name="action" value="add_employee"><div class="card-body">
<h2 class="h6 fw-bold">Add employee</h2><p class="small text-muted">Drivers, cleaners and other employees can be added without login access.</p>
<label class="form-label small">Name</label><input name="name" class="form-control mb-2" required>
<label class="form-label small">Job / role</label><input name="job_title" class="form-control mb-2" placeholder="Driver, Cleaner, Cashier">
<div class="row g-2"><div class="col-7"><label class="form-label small">Salary (KES)</label><input name="salary_amount" type="number" min="0" step=".01" class="form-control"></div><div class="col-5"><label class="form-label small">Pay day</label><input name="pay_day" type="number" min="1" max="31" value="30" class="form-control"></div></div>
<label class="form-label small mt-2">Link POS account <span class="text-muted">(optional, for commissions)</span></label><select name="user_id" class="form-select"><option value="">No login account</option><?php foreach($users as $u):?><option value="<?php echo (int)$u['id'];?>"><?php echo htmlspecialchars($u['username']);?></option><?php endforeach;?></select>
<button class="btn btn-primary w-100 mt-3">Add employee</button></div></form></div>
<div class="col-lg-8"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h2 class="h6 fw-bold">Employees and next pay</h2>
<?php if(!$employees):?><div class="text-muted py-4 text-center">Add your first employee. A POS staff account is not required.</div><?php else:?><div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead><tr><th>Employee</th><th>Job</th><th class="text-end">Salary</th><th class="text-center">Pay day</th><th class="text-end">Commission due</th><th>Pay employee</th></tr></thead><tbody>
<?php foreach($employees as $e):?><tr><td class="fw-semibold"><?php echo htmlspecialchars($e['name']);?><?php if($e['login_name']):?><small class="d-block text-muted">POS: <?php echo htmlspecialchars($e['login_name']);?></small><?php endif;?></td><td><?php echo htmlspecialchars($e['job_title']);?></td><td class="text-end">KES <?php echo number_format((float)$e['salary_amount'],2);?></td><td class="text-center"><?php echo (int)$e['pay_day'];?></td><td class="text-end text-success">KES <?php echo number_format((float)$e['commission_due'],2);?></td><td>
<form method="post" class="d-flex gap-1 flex-wrap"><input type="hidden" name="action" value="pay"><input type="hidden" name="employee_id" value="<?php echo (int)$e['id'];?>">
<input name="salary_amount" type="number" step=".01" min="0" value="<?php echo (float)$e['salary_amount'];?>" class="form-control form-control-sm" title="Salary" style="width:100px">
<input name="commission_amount" type="number" step=".01" min="0" max="<?php echo (float)$e['commission_due'];?>" value="<?php echo (float)$e['commission_due'];?>" class="form-control form-control-sm" title="Commission" style="width:100px">
<input name="pay_period" value="<?php echo date('F Y');?>" class="form-control form-control-sm" style="width:110px"><button class="btn btn-success btn-sm">Pay</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h6 fw-bold">Payroll history</h2><input id="payrollSearch" class="form-control form-control-sm" style="max-width:280px" placeholder="Search anything..."></div>
<div class="table-responsive mt-3"><table class="table table-bordered table-sm align-middle" id="payrollTable"><thead><tr><th>Date</th><th>Employee</th><th>Job</th><th>Period</th><th class="text-end">Salary</th><th class="text-end">Commission</th><th class="text-end">Total paid</th><th>Method</th></tr></thead><tbody>
<?php if(!$payments):?><tr><td colspan="8" class="text-center text-muted py-4">No payroll payments yet.</td></tr><?php else:foreach($payments as $r):?><tr><td><?php echo htmlspecialchars($r['paid_on']);?></td><td><?php echo htmlspecialchars($r['name']);?></td><td><?php echo htmlspecialchars($r['job_title']);?></td><td><?php echo htmlspecialchars($r['pay_period']);?></td><td class="text-end"><?php echo number_format((float)$r['salary_amount'],2);?></td><td class="text-end"><?php echo number_format((float)$r['commission_amount'],2);?></td><td class="text-end fw-bold"><?php echo number_format((float)$r['total_amount'],2);?></td><td><?php echo htmlspecialchars($r['payment_method']);?></td></tr><?php endforeach;endif;?></tbody></table></div></div></div>
<script>document.getElementById('payrollSearch').addEventListener('input',function(){var q=this.value.toLowerCase();document.querySelectorAll('#payrollTable tbody tr').forEach(function(r){r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});});</script>
<?php $content=ob_get_clean();include __DIR__.'/../../templates/tenants/layout.php';
