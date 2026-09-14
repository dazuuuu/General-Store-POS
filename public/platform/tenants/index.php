<?php
require_once __DIR__.'/../../../app/app.php';PageGuard::platform();$db=Database::pdo();$support=new TenantSupportService($db);
$ready=$support->schemaReady();
$tenants=$db->query("SELECT t.id,t.name,t.slug,t.status,t.offline_enabled,t.created_at,u.username owner_name,u.email owner_email,
 (SELECT COUNT(*) FROM users x WHERE x.tenant_id=t.id) user_count,
 (SELECT COUNT(*) FROM branches b WHERE b.tenant_id=t.id) branch_count
 FROM tenants t LEFT JOIN users u ON u.id=t.owner_user_id ORDER BY t.name")->fetchAll();
$page_title='Business Support';ob_start();?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4"><div><h1 class="h4 fw-bold mb-1">Business support</h1><p class="text-muted mb-0">Control every tenant page, business feature, connection mode and account status.</p></div></div>
<?php if(!$ready):?><div class="alert alert-warning"><strong>Support controls need their database migration.</strong> Run migrations before editing account access. <a href="<?php echo public_url('platform/migrations/');?>">Open migration manager</a>.</div><?php endif;?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Business</th><th>Owner</th><th>Status</th><th class="text-end">Users</th><th class="text-end">Branches</th><th>Connection</th><th></th></tr></thead><tbody>
<?php foreach($tenants as $tenant):?><tr><td><strong><?php echo htmlspecialchars($tenant['name']);?></strong><small class="d-block text-muted"><?php echo htmlspecialchars($tenant['slug']);?></small></td><td><?php echo htmlspecialchars($tenant['owner_name']??'Not assigned');?><small class="d-block text-muted"><?php echo htmlspecialchars($tenant['owner_email']??'');?></small></td><td><span class="badge <?php echo $tenant['status']==='active'?'bg-success':'bg-danger';?>"><?php echo htmlspecialchars(ucfirst($tenant['status']));?></span></td><td class="text-end"><?php echo (int)$tenant['user_count'];?></td><td class="text-end"><?php echo (int)$tenant['branch_count'];?></td><td><?php echo !empty($tenant['offline_enabled'])?'Online + Offline':'Online only';?></td><td class="text-end"><a class="btn btn-sm btn-primary <?php echo !$ready?'disabled':'';?>" href="<?php echo public_url('platform/tenants/edit.php?id='.(int)$tenant['id']);?>">Manage</a></td></tr><?php endforeach;?>
</tbody></table></div></div>
<?php $content=ob_get_clean();include __DIR__.'/../../templates/platform/layout.php';
