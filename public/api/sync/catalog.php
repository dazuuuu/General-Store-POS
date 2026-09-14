<?php
require_once __DIR__.'/../../../app/app.php';PageGuard::auth();header('Content-Type: application/json');
if(!TenantFeatures::offlineEnabled()){http_response_code(403);echo json_encode(['error'=>'Offline mode is not enabled.']);exit;}
$products=(new Models\ProductModel(Database::pdo()))->sellable();
echo json_encode(['tenant_id'=>TenantContext::tenantId(),'synced_at'=>date(DATE_ATOM),'products'=>array_map(static function($p){return[
 'id'=>(int)$p['id'],'name'=>$p['name'],'barcode'=>$p['barcode']??null,'quantity'=>(float)$p['quantity'],
 'retail_price'=>(float)$p['retail_price'],'wholesale_price'=>(float)$p['wholesale_price'],
 'unit'=>$p['unit']??'piece','serial_tracking'=>!empty($p['serial_tracking'])
];},$products)]);
