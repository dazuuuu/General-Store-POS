<?php
require_once __DIR__.'/../../../app/app.php';
header('Content-Type: application/json; charset=utf-8');
if(!TenantContext::check()||!TenantContext::can(Capabilities::SALES_RECORD)){http_response_code(403);echo json_encode(['item'=>null]);exit;}
$code=trim((string)($_GET['code']??''));
$row=$code!==''?(new Models\ProductModel(Database::pdo()))->findByBarcode($code):null;
if(!$row||(float)($row['quantity']??0)<=0){echo json_encode(['item'=>null]);exit;}
$effective=Models\ProductModel::effectivePrice($row);
echo json_encode(['item'=>[
  'id'=>(int)$row['id'],'name'=>$row['name'],'price'=>(float)$effective['price'],
  'wholesale'=>(float)($row['wholesale_price']?:$row['selling_price']),
  'buying'=>(float)($row['buying_price']??0),'packageBuying'=>(float)($row['package_buying_price']??0),
  'stock'=>(float)$row['quantity'],'unitsPerPack'=>(float)($row['units_per_pack']??1),
  'packUnit'=>$row['pack_unit']??'pack','packPrice'=>(float)($row['pack_price']??0),
  'retailPackPrice'=>(float)($row['retail_pack_price']??0),'barcode'=>$row['barcode'],'tiers'=>[],
  'serialTracking'=>!empty($row['serial_tracking']),
  'img'=>$row['image_path']??null,
]]);
