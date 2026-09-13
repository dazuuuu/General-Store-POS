<?php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();
$pdo = Database::pdo();
(new Models\OrderModel($pdo)); // ensure commission snapshot columns
(new Models\BusinessServiceModel($pdo));
$tid = TenantContext::tenantId();
$period = in_array($_GET['period'] ?? '', ['today','week','month','all'], true) ? $_GET['period'] : 'month';
$where = match($period) {
    'today' => 'DATE(x.created_at)=CURDATE()',
    'week' => 'x.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
    'month' => 'x.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    default => '1=1',
};
$sql = "SELECT staff_id, staff_name, SUM(product_commission) product_commission, SUM(service_commission) service_commission
FROM (
 SELECT oi.added_by staff_id, u.username staff_name, SUM(COALESCE(oi.commission_amount,0)) product_commission, 0 service_commission, o.created_at
 FROM order_items oi JOIN orders o ON o.id=oi.order_id AND o.tenant_id=oi.tenant_id LEFT JOIN users u ON u.id=oi.added_by
 WHERE oi.tenant_id=? AND o.status <> 'void' GROUP BY oi.added_by, u.username, o.id, o.created_at
 UNION ALL
 SELECT a.staff_id, u.username, 0, a.commission_total, a.created_at
 FROM service_appointments a LEFT JOIN users u ON u.id=a.staff_id
 WHERE a.tenant_id=? AND a.status <> 'cancelled'
) x WHERE {$where} GROUP BY staff_id, staff_name ORDER BY (SUM(product_commission)+SUM(service_commission)) DESC";
$st=$pdo->prepare($sql); $st->execute([$tid,$tid]); $rows=$st->fetchAll();
$page_title='Commissions'; ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><div><h1 class="h5 fw-bold mb-1">Staff commissions</h1><p class="text-muted small mb-0">Service commissions plus extra product price earned above the protected minimum. Commission is paid together with salary through Payroll.</p></div>
<div><a class="btn btn-success btn-sm me-2" href="<?php echo public_url('super/payroll/');?>">Pay salary + commission</a><div class="btn-group"><?php foreach(['today'=>'Today','week'=>'7 days','month'=>'30 days','all'=>'All'] as $p=>$l): ?><a class="btn btn-sm <?php echo $period===$p?'btn-primary':'btn-outline-secondary'; ?>" href="?period=<?php echo $p; ?>"><?php echo $l; ?></a><?php endforeach; ?></div></div></div>
<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr class="small text-muted text-uppercase"><th>Staff</th><th class="text-end">Product commission</th><th class="text-end">Service commission</th><th class="text-end">Total earned</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="4" class="text-center text-muted py-5">No commissions recorded for this period.</td></tr>
<?php else: foreach($rows as $r): $total=(float)$r['product_commission']+(float)$r['service_commission']; ?><tr><td class="fw-semibold"><?php echo htmlspecialchars($r['staff_name'] ?: 'Unknown'); ?></td><td class="text-end">KES <?php echo number_format((float)$r['product_commission'],2); ?></td><td class="text-end">KES <?php echo number_format((float)$r['service_commission'],2); ?></td><td class="text-end fw-bold text-success">KES <?php echo number_format($total,2); ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div>
<?php $content=ob_get_clean(); include __DIR__.'/../../templates/tenants/layout.php';
