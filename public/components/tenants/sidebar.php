<?php
// public/components/tenants/sidebar.php
// Tenant (shop owner) sidebar. Capability-gated POS navigation with the tenant's
// own business name + logo. Expects $__tenant (array|null) and a booted TenantContext.
// NOTE: this is the TENANT sidebar; the staff version will be duplicated from it later.

require_once __DIR__ . '/../../../app/helpers/PathConfig.php';

$__tenant   = $__tenant ?? null;
$shopName   = $__tenant['name'] ?? 'My Shop';
$logo = Branding::tenantLogo($__tenant);
$username   = $_SESSION['username'] ?? 'User';
$roleLabel  = TenantContext::role() === 'tenant_owner' ? 'Owner' : 'Staff';
$uri        = $_SERVER['REQUEST_URI'] ?? '';
$dashUrl    = TenantContext::role() === 'staff' ? public_url('staff/dashboard/') : public_url('super/dashboard/');

$isOn = function (string $needle) use ($uri): string {
    return strpos($uri, $needle) !== false ? 'active' : '';
};
?>
<button class="t-sidebar-toggle" id="tSidebarToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
<div class="t-sidebar-overlay" id="tSidebarOverlay"></div>

<aside class="t-sidebar" id="tSidebar">
    <div class="t-brand">
        <button class="t-close" id="tSidebarClose" aria-label="Close"><i class="fas fa-times"></i></button>
        <img class="t-logo" src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($shopName); ?>">
        <div class="t-shop"><?php echo htmlspecialchars($shopName); ?></div>
        <div class="t-user">
            <?php echo htmlspecialchars($username); ?>
            <span class="t-role"><?php echo htmlspecialchars($roleLabel); ?></span>
        </div>
    </div>

    <nav class="t-nav">
        <a class="t-link <?php echo $isOn('/dashboard'); ?>" href="<?php echo $dashUrl; ?>">
            <i class="fas fa-house"></i><span>Home</span>
        </a>

        <?php if (TenantContext::role() === 'staff' && TenantContext::can(Capabilities::SALES_VIEW)): ?>
        <a class="t-link <?php echo $isOn('/staff/sales/'); ?>" href="<?php echo public_url('staff/sales/'); ?>"><i class="fas fa-receipt"></i><span>My sales</span></a>
        <?php endif; ?>
        <?php if (TenantContext::role() === 'tenant_owner'): ?>
        <a class="t-link <?php echo $isOn('/super/sales'); ?>" href="<?php echo public_url('super/sales/'); ?>"><i class="fas fa-receipt"></i><span>Sales</span></a>
        <?php endif; ?>
        <?php if (TenantContext::can(Capabilities::SALES_RECORD)): ?>
        <a class="t-link <?php echo $isOn('/super/orders'); ?>" href="<?php echo public_url('super/orders/'); ?>"><i class="fas fa-hand-holding-dollar"></i><span>Open tabs</span></a>
        <?php endif; ?>

        <?php if (TenantContext::can(Capabilities::STAFF_MANAGE)):
            $onPermissions = strpos($uri, 'permissions') !== false;
            $onAttendance  = strpos($uri, 'attendance') !== false;
        ?>
        <a class="t-link <?php echo ($isOn('/super/staff') && !$onPermissions && !$onAttendance) ? 'active' : ''; ?>" href="<?php echo public_url('super/staff/'); ?>">
            <i class="fas fa-user-gear"></i><span>Staff</span>
        </a>
        <a class="t-link <?php echo $onPermissions ? 'active' : ''; ?>" href="<?php echo public_url('super/staff/permissions.php'); ?>">
            <i class="fas fa-key"></i><span>Permissions</span>
        </a>
        <a class="t-link <?php echo $onAttendance ? 'active' : ''; ?>" href="<?php echo public_url('super/staff/attendance.php'); ?>">
            <i class="fas fa-clock"></i><span>Attendance</span>
        </a>
        <?php endif; ?>

        <?php if (TenantContext::role() === 'tenant_owner' && (int) ($__tenant['owner_user_id'] ?? 0) === (int) TenantContext::userId()): ?>
        <a class="t-link <?php echo $isOn('/super/admins'); ?>" href="<?php echo public_url('super/admins/'); ?>">
            <i class="fas fa-user-shield"></i><span>Admins</span>
        </a>
        <?php endif; ?>

        <?php if (TenantContext::can(Capabilities::INVENTORY_VIEW) || TenantContext::can(Capabilities::INVENTORY_EDIT)): ?>
        <a class="t-link <?php echo $isOn('/super/inventory'); ?>" href="<?php echo public_url('super/inventory/'); ?>">
            <i class="fas fa-warehouse"></i><span>Inventory</span>
        </a>
        <?php endif; ?>
        <?php if (TenantContext::can(Capabilities::INVENTORY_EDIT)):
            $onStationeryCats = strpos($uri, '/super/categories') !== false && ($_GET['type'] ?? '') === 'stationery';
        ?>
        <a class="t-link <?php echo (strpos($uri, '/super/categories') !== false && !$onStationeryCats) ? 'active' : ''; ?>" href="<?php echo public_url('super/categories/'); ?>">
            <i class="fas fa-tags"></i><span>Subjects</span>
        </a>
        <a class="t-link <?php echo $isOn('/super/grades'); ?>" href="<?php echo public_url('super/grades/'); ?>">
            <i class="fas fa-graduation-cap"></i><span>Grades</span>
        </a>
        <a class="t-link <?php echo $isOn('/super/publishers'); ?>" href="<?php echo public_url('super/publishers/'); ?>">
            <i class="fas fa-building"></i><span>Publishers</span>
        </a>
        <a class="t-link <?php echo $isOn('/super/suppliers'); ?>" href="<?php echo public_url('super/suppliers/'); ?>">
            <i class="fas fa-truck-field"></i><span>Suppliers</span>
        </a>
        <a class="t-link <?php echo $isOn('/super/stock'); ?>" href="<?php echo public_url('super/stock/new.php'); ?>">
            <i class="fas fa-truck-loading"></i><span>Record stock</span>
        </a>
        <?php endif; ?>
        <?php if (TenantContext::can(Capabilities::STOCK_ENTER)): ?>
        <a class="t-link <?php echo $isOn('/super/stationery'); ?>" href="<?php echo public_url('super/stationery/new.php'); ?>">
            <i class="fas fa-pen-ruler"></i><span>Record stationery</span>
        </a>
        <?php endif; ?>
        <?php if (TenantContext::can(Capabilities::INVENTORY_EDIT)): ?>
        <a class="t-link <?php echo $onStationeryCats ? 'active' : ''; ?>" href="<?php echo public_url('super/categories/') . '?type=stationery'; ?>">
            <i class="fas fa-shapes"></i><span>Stationery categories</span>
        </a>
        <?php endif; ?>

        <?php if (TenantContext::can(Capabilities::REPORTS_VIEW)): ?>
        <a class="t-link <?php echo $isOn('/super/reports'); ?>" href="<?php echo public_url('super/reports/'); ?>"><i class="fas fa-chart-line"></i><span>Reports</span></a>
        <?php endif; ?>

        <?php if (TenantContext::can(Capabilities::SETTINGS_MANAGE)): ?>
        <a class="t-link <?php echo $isOn('/super/settings'); ?>" href="<?php echo public_url('super/settings/'); ?>"><i class="fas fa-gear"></i><span>Settings</span></a>
        <?php endif; ?>

        <hr>

   
        <a class="t-link t-danger" href="<?php echo public_url('auth/logout.php'); ?>">
            <i class="fas fa-arrow-right-from-bracket"></i><span>Logout</span>
        </a>
    </nav>
</aside>

<style>
:root { --t-bg:#fff; --t-bg2:var(--pos-green-light,#f0fdf4); --t-line:#eef0f4; --t-accent:var(--pos-green,#16a34a); --t-text:#5b6070; }
.t-sidebar { width:264px; background:var(--t-bg); color:var(--t-text); position:fixed; left:0; top:0; height:100vh; overflow-y:auto; z-index:1001; transition:transform .3s ease; border-right:1px solid var(--t-line); }
.t-brand { padding:24px 20px; border-bottom:1px solid var(--t-line); text-align:center; position:relative; }
.t-logo { height:44px; max-width:160px; object-fit:contain; border-radius:8px; padding:4px; }
.t-shop { margin-top:12px; font-weight:800; color:#1f2330; font-size:1.05rem; }
.t-user { margin-top:6px; font-size:.8rem; color:#9aa0ac; }
.t-role { display:inline-block; margin-left:6px; padding:1px 8px; border-radius:999px; background:#f3f4f7; color:#5b6070; font-size:.7rem; }
.t-nav { padding:14px 12px; }
.t-link { display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:10px; color:var(--t-text); text-decoration:none; font-size:.9rem; margin-bottom:3px; font-weight:500; }
.t-link i { width:20px; color:#b7bac3; text-align:center; }
.t-link:hover{ background:#f7f7fb; color:#1f2330; }
.t-link:hover i{ color:#1f2330; }
.t-link.active { background:var(--t-bg2); color:var(--t-accent); font-weight:700; box-shadow:inset 3px 0 0 var(--t-accent); }
.t-link.active i { color:var(--t-accent); }
.t-soon { margin-left:auto; font-size:.62rem; font-style:normal; background:#f3f4f7; color:#9aa0ac; padding:1px 7px; border-radius:999px; }
.t-danger { color:#64748b; }
.t-danger:hover { background:#f1f5f9; color:#334155; }
.t-nav hr { border:0; border-top:1px solid var(--t-line); margin:12px 0; }
.t-sidebar-toggle, .t-close { display:none; }
.t-sidebar-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; opacity:0; display:none; transition:opacity .3s; }
.t-sidebar-overlay.active { display:block; opacity:1; }
@media (max-width:992px){
  .t-sidebar { transform:translateX(-100%); box-shadow:0 0 40px rgba(0,0,0,.15); }
  .t-sidebar.active { transform:translateX(0); }
  .t-sidebar-toggle { display:flex; align-items:center; justify-content:center; position:fixed; top:12px; left:12px; z-index:1002; width:44px; height:44px; border:0; border-radius:10px; background:#fff; color:var(--t-accent); font-size:20px; box-shadow:0 2px 10px rgba(0,0,0,.15); }
  .t-close { display:flex; align-items:center; justify-content:center; position:absolute; top:12px; right:12px; width:30px; height:30px; border:0; border-radius:6px; background:#f3f4f7; color:#5b6070; }
}
</style>
<script>
(function(){
  var tg=document.getElementById('tSidebarToggle'),sb=document.getElementById('tSidebar'),
      ov=document.getElementById('tSidebarOverlay'),cl=document.getElementById('tSidebarClose');
  function open(){sb&&sb.classList.add('active');ov&&ov.classList.add('active');document.body.style.overflow='hidden';}
  function close(){sb&&sb.classList.remove('active');ov&&ov.classList.remove('active');document.body.style.overflow='';}
  tg&&tg.addEventListener('click',open); cl&&cl.addEventListener('click',close); ov&&ov.addEventListener('click',close);
  document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
  document.querySelectorAll('[data-soon]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();});});
})();
</script>