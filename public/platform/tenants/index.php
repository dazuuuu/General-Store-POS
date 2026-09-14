<?php
require_once __DIR__.'/../../../app/app.php';
$isPlatform=in_array($_SESSION['role']??'', ['superadmin','platform_admin'],true)||in_array(Capabilities::ALL,$_SESSION['capabilities']??[],true)||in_array(Capabilities::PLATFORM_TENANTS,$_SESSION['capabilities']??[],true);
if(empty($_SESSION['logged_in'])||!$isPlatform){http_response_code(403);exit('Platform administrator access required.');}
$db=Database::pdo();
foreach([
  'enabled_modules'=>"ALTER TABLE tenants ADD COLUMN enabled_modules TEXT NULL",
  'offline_enabled'=>"ALTER TABLE tenants ADD COLUMN offline_enabled TINYINT(1) NOT NULL DEFAULT 0",
] as $column=>$sql){$st=$db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tenants' AND column_name=?");$st->execute([$column]);if(!(int)$st->fetchColumn())$db->exec($sql);}
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=(int)($_POST['tenant_id']??0);$modules=array_values(array_intersect(array_keys(TenantFeatures::MODULES),(array)($_POST['modules']??[])));
  $db->prepare('UPDATE tenants SET enabled_modules=?,offline_enabled=? WHERE id=?')->execute([json_encode(array_merge($modules,[TenantFeatures::VERSION_MARKER])),!empty($_POST['offline_enabled'])?1:0,$id]);
  header('Location: '.public_url('platform/tenants/'));exit;
}
$tenants=$db->query('SELECT id,name,slug,status,enabled_modules,offline_enabled FROM tenants ORDER BY name')->fetchAll();
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tenant features</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4"><h1 class="h4">Tenant feature access</h1><p class="text-muted">Choose modules per business. Offline mode is controlled only here.</p>
<?php foreach($tenants as $tenant):$enabled=json_decode((string)$tenant['enabled_modules'],true);if(!is_array($enabled))$enabled=array_keys(TenantFeatures::MODULES);elseif(!in_array(TenantFeatures::VERSION_MARKER,$enabled,true)&&!in_array('shop_pos',$enabled,true))$enabled[]='shop_pos';?>
<form method="post" class="card mb-3"><input type="hidden" name="tenant_id" value="<?php echo (int)$tenant['id'];?>"><div class="card-body"><div class="d-flex justify-content-between"><div><strong><?php echo htmlspecialchars($tenant['name']);?></strong><small class="text-muted ms-2"><?php echo htmlspecialchars($tenant['slug']);?></small></div><button class="btn btn-primary btn-sm">Save features</button></div>
<div class="row g-2 mt-2"><?php foreach(TenantFeatures::MODULES as $key=>$label):?><div class="col-md-3"><label class="form-check"><input class="form-check-input" type="checkbox" name="modules[]" value="<?php echo $key;?>" <?php echo in_array($key,$enabled,true)?'checked':'';?>><?php echo htmlspecialchars($label);?></label></div><?php endforeach;?></div>
<label class="form-check border rounded p-2 ps-4 mt-3"><input class="form-check-input" type="checkbox" name="offline_enabled" value="1" <?php echo $tenant['offline_enabled']?'checked':'';?>><strong>Online + Offline PWA</strong> <small class="text-muted">(unchecked = online only)</small></label>
</div></form><?php endforeach;?></main></body></html>
