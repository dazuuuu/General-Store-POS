<?php
require_once __DIR__.'/../../../app/app.php';PageGuard::tenant();
$db=Database::pdo();$TM=new Models\TenantModel($db);$tid=(int)TenantContext::tenantId();
if($_SERVER['REQUEST_METHOD']==='POST'){
 http_response_code(403);exit('Feature access is managed by developer support.');
}
$tenant=$TM->find($tid)?:[];$enabled=json_decode((string)($tenant['enabled_modules']??''),true);if(!is_array($enabled))$enabled=array_keys(TenantFeatures::MODULES);elseif(!in_array(TenantFeatures::VERSION_MARKER,$enabled,true)&&!in_array('shop_pos',$enabled,true))$enabled[]='shop_pos';$offline=!empty($tenant['offline_enabled']);
$page_title='Features & Connection Mode';ob_start();?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4"><div><h1 class="h5 fw-bold mb-1">Features & Online/Offline mode</h1><p class="text-muted small mb-0">This is a read-only view. Developer support controls account features and pages.</p></div><span class="badge <?php echo $offline?'bg-success':'bg-primary';?> p-2"><?php echo $offline?'Online + Offline':'Online only';?></span></div>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h6 fw-bold">Enabled business modules</h2><div class="row g-3"><?php foreach(TenantFeatures::MODULES as $key=>$label):?><div class="col-md-6 col-xl-4"><div class="border rounded p-3 h-100 <?php echo in_array($key,$enabled,true)?'bg-light':'opacity-50';?>"><i class="fas <?php echo in_array($key,$enabled,true)?'fa-circle-check text-success':'fa-circle-xmark text-muted';?> me-2"></i><span class="fw-semibold"><?php echo htmlspecialchars($label);?></span></div></div><?php endforeach;?></div></div></div>
<?php if(!$offline):?><script>if('serviceWorker'in navigator){navigator.serviceWorker.getRegistrations().then(function(rs){rs.forEach(function(r){r.unregister();});});if(window.caches)caches.keys().then(function(keys){keys.filter(function(k){return k.indexOf('shop-pos-')===0;}).forEach(function(k){caches.delete(k);});});}</script><?php endif;?>
<?php $content=ob_get_clean();include __DIR__.'/../../templates/tenants/layout.php';
