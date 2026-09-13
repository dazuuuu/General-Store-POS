<?php
require_once __DIR__.'/../../../app/app.php';PageGuard::capability(Capabilities::PAYMENTS_PROCESS);
$db=Database::pdo();$O=new Models\OrderModel($db);$id=(int)($_GET['id']??$_POST['id']??0);$order=$O->find($id);
if(!$order||($order['channel']??'')!=='restaurant'){http_response_code(404);exit('Restaurant order not found.');}
$isStaff=TenantContext::role()==='staff';$base=public_url(($isStaff?'staff':'super').'/restaurant/');$error='';$due=max(0,(float)$order['total']-(float)($order['amount_paid']??0));
if($_SERVER['REQUEST_METHOD']==='POST'){
 $method=in_array($_POST['method']??'',['cash','mpesa','card','bank','paybill'],true)?$_POST['method']:'cash';
 $res=$O->markPaid($id,['method'=>$method,'amount_tendered'=>$_POST['amount_tendered']??$due,'reference'=>trim((string)($_POST['reference']??''))],TenantContext::userId());
 if($res['ok']){$_SESSION['flash']['success']='Restaurant order paid successfully.';header('Location: '.public_url(($isStaff?'staff':'super').'/orders/receipt.php?id='.$id.'&print=1'));exit;}$error=$res['error']??'Payment failed.';
}
$page_title='Restaurant Payment';ob_start();?>
<div class="mb-4"><a href="<?php echo $base;?>view.php?id=<?php echo $id;?>" class="small text-muted text-decoration-none">&larr; Back to order</a><h1 class="h5 fw-bold mt-2 mb-1">Process restaurant payment</h1><p class="text-muted small"><?php echo htmlspecialchars($order['receipt_number']);?> · <?php echo htmlspecialchars($order['table_name']?:'Walk-in');?></p></div>
<?php if($error):?><div class="alert alert-danger"><?php echo htmlspecialchars($error);?></div><?php endif;?>
<div class="row"><div class="col-md-7 col-lg-5"><form method="post" class="card border-0 shadow-sm"><div class="card-body p-4"><input type="hidden" name="id" value="<?php echo $id;?>"><div class="text-muted small text-uppercase fw-semibold">Balance due</div><div class="h3 fw-bold text-primary mb-4">KES <?php echo number_format($due,2);?></div><label class="form-label">Payment method</label><select name="method" id="restaurantMethod" class="form-select mb-3"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="card">Card</option><option value="bank">Bank</option><option value="paybill">Paybill</option></select><div id="cashGiven"><label class="form-label">Cash given</label><input name="amount_tendered" type="number" min="<?php echo $due;?>" step=".01" value="<?php echo $due;?>" class="form-control mb-3"></div><label class="form-label">Payment reference <span class="text-muted">(optional)</span></label><input name="reference" class="form-control mb-4" placeholder="M-Pesa code, card or bank reference"><button class="btn btn-primary w-100" <?php echo $due<=0?'disabled':'';?>>Complete payment</button></div></form></div></div>
<script>document.getElementById('restaurantMethod').addEventListener('change',function(){document.getElementById('cashGiven').style.display=this.value==='cash'?'':'none';});</script>
<?php $content=ob_get_clean();$__layout=$isStaff?'staff':'tenants';include __DIR__.'/../../templates/'.$__layout.'/layout.php';
