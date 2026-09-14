<?php
require_once __DIR__.'/../../../app/app.php';
if(empty($supportRouteMode)){header('Location: '.public_url('domain/support/migrations.php'));exit;}
SupportGuard::auth();$runner=new MigrationRunnerService(Database::pdo());
$_SESSION['migration_csrf']=$_SESSION['migration_csrf']??bin2hex(random_bytes(24));$error='';$result='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals((string)$_SESSION['migration_csrf'],(string)($_POST['csrf']??''))){http_response_code(419);exit('Migration form expired.');}
  try{if(($_POST['action']??'')==='required'){$r=$runner->runRequiredSupportMigrations((int)TenantContext::userId());$result='Required POS migrations: '.$r['ran'].' statements executed, '.$r['skipped'].' already-existing statements skipped.';}else{$name=(string)($_POST['migration']??'');$r=$runner->run($name,(int)TenantContext::userId());$result=$name.': '.$r['ran'].' statements executed, '.$r['skipped'].' already-existing statements skipped.';}}
  catch(Throwable $e){$error=$e->getMessage();}
}
$migrations=$runner->list();$page_title='Migration Manager';ob_start();?>
<div class="mb-4"><h1 class="h4 fw-bold mb-1">Migration manager</h1><p class="text-muted mb-0">Only developer support can change the system database. Run numbered migrations in order and review failures before continuing.</p></div>
<?php if($result):?><div class="alert alert-success"><?php echo htmlspecialchars($result);?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?php echo htmlspecialchars($error);?></div><?php endif;?>
<?php if(!$runner->supportSchemaReady()):?><div class="alert alert-warning"><strong>This hosted database needs the current support/POS migrations.</strong><form method="post" class="mt-2" onsubmit="return confirm('Run all required support portal migrations now?');"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['migration_csrf']);?>"><input type="hidden" name="action" value="required"><button class="btn btn-dark">Run required migrations</button></form></div><?php endif;?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Migration</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($migrations as $migration):?><tr><td><code><?php echo htmlspecialchars($migration['name']);?></code></td><td><span class="badge <?php echo $migration['applied']?'bg-success':'bg-warning text-dark';?>"><?php echo $migration['applied']?'Recorded as applied':'Pending / unrecorded';?></span></td><td class="text-end"><form method="post" onsubmit="return confirm('Run <?php echo htmlspecialchars($migration['name']);?> now?');"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['migration_csrf']);?>"><input type="hidden" name="migration" value="<?php echo htmlspecialchars($migration['name']);?>"><button class="btn btn-sm <?php echo $migration['applied']?'btn-outline-secondary':'btn-primary';?>"><?php echo $migration['applied']?'Run again':'Run migration';?></button></form></td></tr><?php endforeach;?></tbody></table></div></div>
<?php $content=ob_get_clean();include __DIR__.'/../../templates/platform/layout.php';
