<?php
require_once __DIR__.'/../../../app/app.php';
header('Content-Type: application/json');
$tid=(int)(TenantContext::tenantId()??0);
if(empty($_SESSION['logged_in'])||$tid<=0){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'login']);exit;}
try{
  $st=Database::pdo()->prepare('SELECT status,enabled_modules,enabled_pages,offline_enabled,settings_revision FROM tenants WHERE id=? LIMIT 1');$st->execute([$tid]);$row=$st->fetch();
  if(!$row){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'account']);exit;}
  echo json_encode(['ok'=>true,'status'=>$row['status'],'modules'=>json_decode((string)$row['enabled_modules'],true)?:[],'pages'=>json_decode((string)$row['enabled_pages'],true)?:[],'offline_enabled'=>!empty($row['offline_enabled']),'revision'=>(int)$row['settings_revision']]);
}catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'error'=>'settings_unavailable']);}
