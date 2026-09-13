<?php
require_once __DIR__.'/../../../app/app.php';PageGuard::capability(Capabilities::SALES_RECORD);header('Content-Type: application/json');
if(!TenantFeatures::offlineEnabled()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Offline sync is not enabled.']);exit;}
$in=json_decode(file_get_contents('php://input'),true);
if(!is_array($in)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Invalid offline sale.']);exit;}
$uuid=trim((string)($in['client_uuid']??''));
if(!preg_match('/^[a-f0-9-]{36}$/i',$uuid)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Invalid sale sync ID.']);exit;}
$db=Database::pdo();$existing=$db->prepare('SELECT id,receipt_number,status FROM orders WHERE tenant_id=? AND client_uuid=? LIMIT 1');$existing->execute([TenantContext::tenantId(),$uuid]);
$O=new Models\OrderModel($db);
$row=$existing->fetch();$orderId=0;$receiptNumber=null;
if($row&&$row['status']==='paid'){echo json_encode(['ok'=>true,'duplicate'=>true,'order_id'=>(int)$row['id'],'receipt_number'=>$row['receipt_number']]);exit;}
if($row&&$row['status']==='open'){$orderId=(int)$row['id'];$receiptNumber=$row['receipt_number'];}
if($row&&!in_array($row['status'],['open','paid'],true))$db->prepare('UPDATE orders SET client_uuid=NULL WHERE id=? AND tenant_id=?')->execute([(int)$row['id'],TenantContext::tenantId()]);
if(!$orderId){
    $res=$O->open(['client_uuid'=>$uuid,'opened_by'=>TenantContext::userId(),'channel'=>'walkin','table_name'=>trim((string)($in['customer_name']??'')),'items'=>(array)($in['items']??[]),'sale_type'=>$in['sale_type']??'retail','vat_rate'=>(float)($in['vat_rate']??0),'vat_inclusive'=>!empty($in['vat_inclusive'])]);
    if(!$res['ok']){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$res['errors']['_']??'Could not sync sale.']);exit;}
    $orderId=(int)$res['order_id'];$receiptNumber=$res['receipt_number'];
}
$paid=$O->markPaid($orderId,[
    'method'=>$in['payment_method']??'cash',
    'amount_tendered'=>(($in['amount_tendered']??'')?:999999999),
    'cash_amount'=>$in['cash_amount']??0,
    'mpesa_amount'=>$in['mpesa_amount']??0,
    'reference'=>'OFFLINE-'.$uuid,
],TenantContext::userId());
if(!$paid['ok']){$O->void($orderId,TenantContext::userId());$db->prepare('UPDATE orders SET client_uuid=NULL WHERE id=? AND tenant_id=?')->execute([$orderId,TenantContext::tenantId()]);http_response_code(422);echo json_encode(['ok'=>false,'error'=>$paid['error']??'Could not sync payment.']);exit;}
try{$db->prepare("INSERT INTO sync_events(tenant_id,entity_type,entity_id,action,payload) VALUES(?,'order',?,'offline_synced',?)")->execute([TenantContext::tenantId(),$orderId,json_encode(['client_uuid'=>$uuid])]);}catch(Throwable $ignored){}
echo json_encode(['ok'=>true,'duplicate'=>false,'order_id'=>$orderId,'receipt_number'=>$receiptNumber]);
