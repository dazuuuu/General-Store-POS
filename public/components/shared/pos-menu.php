<?php
// Shared grouped POS navigation. Included inside the owner/staff <nav>.
$menuUri = $_SERVER['REQUEST_URI'] ?? '';
$menuOwner = TenantContext::role() === 'tenant_owner';
$menuOn = static fn(string $needle): string => strpos($menuUri, $needle) !== false ? 'active' : '';
$menuGroupOpen = static function (array $needles) use ($menuUri): bool {
    foreach ($needles as $needle) {
        if (strpos($menuUri, $needle) !== false) {
            return true;
        }
    }
    return false;
};
$menuRoute = static function (string $owner, string $staff = '') use ($menuOwner): string {
    return public_url($menuOwner || $staff === '' ? $owner : $staff);
};

$shopEnabled=TenantFeatures::enabled('shop_pos');
$posOpen = $menuGroupOpen(['/shop', '/orders', '/sales', '/invoices', '/returns', '/customers', '/reports', '/data', '/documents', '/services']);
$restaurantOpen=$menuGroupOpen(['/restaurant','/menu','/restaurant-stock']);
$inventoryOpen = $menuGroupOpen(['/inventory', '/store', '/purchases', '/suppliers', '/stationery', '/stock', '/publishers', '/categories']);
$financeOpen = $menuGroupOpen(['/finances', '/expenses', '/salary', '/payroll', '/commissions', '/taxes']);
$settingsOpen = $menuGroupOpen(['/admins', '/staff', '/settings', '/clean_migrations']);
$menuUpcoming = [];
try {
    $menuUpcoming = (new Models\BusinessServiceModel(Database::pdo()))->upcomingForUser((int) TenantContext::userId(), 60);
} catch (\Throwable $ignored) {
}
?>
<?php if ($menuUpcoming): $nextAppointment = $menuUpcoming[0]; ?>
<a class="t-link" href="<?php echo public_url('super/services/appointments.php?status=booked'); ?>" style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;">
  <i class="fas fa-bell" style="color:#ea580c;"></i>
  <span>Appointment soon<small class="d-block"><?php echo htmlspecialchars(date('g:i a', strtotime($nextAppointment['scheduled_at'])) . ' · ' . $nextAppointment['customer_name']); ?></small></span>
</a>
<?php endif; ?>
<a class="t-link <?php echo $menuOn('/dashboard'); ?>" href="<?php echo $menuRoute('super/dashboard/', 'staff/dashboard/'); ?>">
  <i class="fas fa-house"></i><span>Home</span>
</a>

<div class="t-group <?php echo $posOpen ? 'open' : ''; ?>" data-nav-group>
  <button type="button" class="t-link t-group-toggle <?php echo $posOpen ? 'active' : ''; ?>" aria-expanded="<?php echo $posOpen ? 'true' : 'false'; ?>">
    <i class="fas fa-cash-register"></i><span>POS</span><i class="fas fa-chevron-down t-group-caret"></i>
  </button>
  <div class="t-subnav">
    <?php if ($shopEnabled&&($menuOwner || TenantContext::can(Capabilities::SALES_RECORD))): ?>
      <a class="t-sublink <?php echo $menuOn('/shop'); ?>" href="<?php echo $menuRoute('super/shop/', 'staff/dashboard/'); ?>">Shop</a>
      <a class="t-sublink t-subsub <?php echo $menuOn('/orders/held'); ?>" href="<?php echo $menuRoute('super/orders/held.php', 'staff/orders/held.php'); ?>">Hold sales</a>
    <?php endif; ?>
    <?php if ($menuOwner || TenantContext::can(Capabilities::SALES_VIEW)): ?>
      <a class="t-sublink <?php echo $menuOn('/sales'); ?>" href="<?php echo $menuRoute('super/sales/', 'staff/sales/'); ?>">Sales</a>
    <?php endif; ?>
    <?php if ($menuOwner): ?>
      <a class="t-sublink <?php echo $menuOn('/invoices'); ?>" href="<?php echo public_url('super/invoices/'); ?>">Invoices</a>
    <?php endif; ?>
    <?php if ($shopEnabled&&($menuOwner || TenantContext::can(Capabilities::SALES_RECORD))): ?>
      <a class="t-sublink <?php echo $menuOn('/orders'); ?>" href="<?php echo $menuRoute('super/orders/', 'staff/orders/'); ?>">Credit Sales</a>
      <?php if(TenantFeatures::enabled('returns')):?><a class="t-sublink <?php echo $menuOn('/returns'); ?>" href="<?php echo $menuRoute('super/returns/', 'staff/returns/'); ?>">Returns</a><?php endif;?>
    <?php endif; ?>
    <?php if ($shopEnabled&&($menuOwner || TenantContext::can(Capabilities::CUSTOMERS_MANAGE))): ?>
      <a class="t-sublink <?php echo $menuOn('/customers'); ?>" href="<?php echo public_url('super/customers/'); ?>">Customers / Loyalty</a>
    <?php endif; ?>
    <?php if ($menuOwner || TenantContext::can(Capabilities::REPORTS_VIEW)): ?>
      <a class="t-sublink <?php echo $menuOn('/reports'); ?>" href="<?php echo public_url('super/reports/'); ?>">Reports</a>
      <a class="t-sublink <?php echo $menuOn('/data'); ?>" href="<?php echo public_url('super/data/'); ?>">Data export</a>
    <?php endif; ?>
    <a class="t-sublink <?php echo $menuOn('/documents'); ?>" href="<?php echo $menuRoute('super/documents/', 'staff/documents/'); ?>">Documents</a>

    <?php if(TenantFeatures::enabled('services')):?>
    <div class="t-subsection"><i class="fas fa-concierge-bell me-1"></i>Services</div>
    <a class="t-sublink t-subsub <?php echo $menuOn('/services/sell'); ?>" href="<?php echo public_url('super/services/sell.php'); ?>">Sell services</a>
    <a class="t-sublink t-subsub <?php echo $menuOn('/services/appointments'); ?>" href="<?php echo public_url('super/services/appointments.php'); ?>">Appointments</a>
    <?php if ($menuOwner): ?>
      <a class="t-sublink t-subsub <?php echo $menuOn('/services/new'); ?>" href="<?php echo public_url('super/services/new.php'); ?>">Create services</a>
    <?php endif; ?>
    <a class="t-sublink t-subsub <?php echo strpos($menuUri, '/services/') !== false && strpos($menuUri, '/services/sell') === false && strpos($menuUri, '/services/appointments') === false && strpos($menuUri, '/services/new') === false ? 'active' : ''; ?>" href="<?php echo public_url('super/services/'); ?>">View services</a>
    <?php endif;?>
  </div>
</div>

<?php if(TenantFeatures::enabled('restaurant_menu')):?>
<div class="t-group <?php echo $restaurantOpen?'open':'';?>" data-nav-group>
  <button type="button" class="t-link t-group-toggle <?php echo $restaurantOpen?'active':'';?>" aria-expanded="<?php echo $restaurantOpen?'true':'false';?>"><i class="fas fa-utensils"></i><span>Restaurant</span><i class="fas fa-chevron-down t-group-caret"></i></button>
  <div class="t-subnav">
    <a class="t-sublink <?php echo $menuOn('/restaurant/new');?>" href="<?php echo $menuRoute('super/restaurant/new.php','staff/restaurant/new.php');?>">New order</a>
    <a class="t-sublink <?php echo $menuOn('/restaurant/');?>" href="<?php echo $menuRoute('super/restaurant/','staff/restaurant/');?>">Orders & payment</a>
    <a class="t-sublink <?php echo $menuOn('/restaurant/held');?>" href="<?php echo $menuRoute('super/restaurant/held.php','staff/restaurant/held.php');?>">Held orders</a>
  </div>
</div>
<?php endif;?>

<?php if ($menuOwner || TenantContext::can(Capabilities::INVENTORY_VIEW) || TenantContext::can(Capabilities::INVENTORY_EDIT) || TenantContext::can(Capabilities::STOCK_ENTER)): ?>
<div class="t-group <?php echo $inventoryOpen ? 'open' : ''; ?>" data-nav-group>
  <button type="button" class="t-link t-group-toggle <?php echo $inventoryOpen ? 'active' : ''; ?>" aria-expanded="<?php echo $inventoryOpen ? 'true' : 'false'; ?>">
    <i class="fas fa-warehouse"></i><span>Inventory</span><i class="fas fa-chevron-down t-group-caret"></i>
  </button>
  <div class="t-subnav">
    <?php if($shopEnabled):?><a class="t-sublink <?php echo $menuOn('/inventory'); ?>" href="<?php echo public_url('super/inventory/'); ?>">Shop Inventory</a><?php endif;?>
    <?php if($shopEnabled&&TenantFeatures::enabled('serials')):?><a class="t-sublink <?php echo $menuOn('/inventory/serials');?>" href="<?php echo public_url('super/inventory/serials.php');?>">Tracked Devices / Serials</a><?php endif;?>
    <?php if($shopEnabled&&TenantFeatures::enabled('store')):?><a class="t-sublink <?php echo $menuOn('/store'); ?>" href="<?php echo public_url('super/store/'); ?>">Store Warehouse</a><?php endif;?>
    <?php if(TenantFeatures::enabled('restaurant_menu')):?>
    <a class="t-sublink <?php echo $menuOn('/restaurant-stock'); ?>" href="<?php echo public_url('super/restaurant-stock/'); ?>">Restaurant Stock</a>
    <a class="t-sublink <?php echo $menuOn('/menu'); ?>" href="<?php echo public_url('super/menu/'); ?>">Menu, Variants & Recipes</a>
    <?php endif;?>
    <?php if($shopEnabled&&TenantFeatures::enabled('purchases')):?>
    <div class="t-subsection"><i class="fas fa-cart-shopping me-1"></i>Purchases</div>
    <a class="t-sublink t-subsub <?php echo $menuOn('/purchases/new'); ?>" href="<?php echo public_url('super/purchases/new.php'); ?>">Record purchase</a>
    <a class="t-sublink t-subsub <?php echo strpos($menuUri, '/purchases/') !== false && strpos($menuUri, '/purchases/new') === false && strpos($menuUri, '/purchases/track') === false && strpos($menuUri, '/purchases/transfer') === false ? 'active' : ''; ?>" href="<?php echo public_url('super/purchases/'); ?>">View purchases</a>
    <a class="t-sublink t-subsub <?php echo $menuOn('/purchases/track'); ?>" href="<?php echo public_url('super/purchases/track.php'); ?>">Track purchases</a>
    <a class="t-sublink t-subsub <?php echo $menuOn('/purchases/transfer'); ?>" href="<?php echo public_url('super/purchases/transfer.php'); ?>">Transfer purchases</a>
    <?php endif;?>
    <?php if($shopEnabled):?>
    <a class="t-sublink <?php echo $menuOn('/suppliers'); ?>" href="<?php echo public_url('super/suppliers/'); ?>">Suppliers</a>
    <a class="t-sublink <?php echo $menuOn('/stationery'); ?>" href="<?php echo public_url('super/stationery/new.php'); ?>">Record Stock</a>
    <a class="t-sublink <?php echo $menuOn('/stock'); ?>" href="<?php echo public_url('super/stock/new.php'); ?>">Bulk Stock</a>
    <a class="t-sublink <?php echo $menuOn('/inventory/low-stock'); ?>" href="<?php echo public_url('super/inventory/low-stock.php'); ?>">Low Stock Alerts</a>
    <a class="t-sublink <?php echo $menuOn('/publishers'); ?>" href="<?php echo public_url('super/publishers/'); ?>">Brands</a>
    <a class="t-sublink <?php echo $menuOn('/categories'); ?>" href="<?php echo public_url('super/categories/'); ?>">Categories</a>
    <?php endif;?>
  </div>
</div>
<?php endif; ?>

<?php if ($menuOwner): ?>
<div class="t-group <?php echo $financeOpen ? 'open' : ''; ?>" data-nav-group>
  <button type="button" class="t-link t-group-toggle <?php echo $financeOpen ? 'active' : ''; ?>" aria-expanded="<?php echo $financeOpen ? 'true' : 'false'; ?>">
    <i class="fas fa-coins"></i><span>Finances</span><i class="fas fa-chevron-down t-group-caret"></i>
  </button>
  <div class="t-subnav">
    <a class="t-sublink <?php echo $menuOn('/finances'); ?>" href="<?php echo public_url('super/finances/'); ?>">Finances</a>
    <a class="t-sublink <?php echo $menuOn('/expenses'); ?>" href="<?php echo public_url('super/expenses/'); ?>">Expenses</a>
    <?php if(TenantFeatures::enabled('payroll')):?><a class="t-sublink <?php echo $menuOn('/payroll'); ?>" href="<?php echo public_url('super/payroll/'); ?>">Salary & Payroll</a><?php endif;?>
    <?php if(TenantFeatures::enabled('commissions')):?><a class="t-sublink <?php echo $menuOn('/commissions'); ?>" href="<?php echo public_url('super/commissions/'); ?>">Commission</a><?php endif;?>
    <a class="t-sublink <?php echo $menuOn('/taxes'); ?>" href="<?php echo public_url('super/taxes/'); ?>">Taxes</a>
  </div>
</div>

<a class="t-link <?php echo $menuOn('/branches')?'active':'';?>" href="<?php echo public_url('super/branches/');?>"><i class="fas fa-code-branch"></i><span>Branches<?php if(\BranchContext::current()):?><small class="d-block opacity-75"><?php echo htmlspecialchars(\BranchContext::current()['name']);?></small><?php endif;?></span></a>
<a class="t-link <?php echo $menuOn('/features')?'active':'';?>" href="<?php echo public_url('super/features/');?>"><i class="fas fa-toggle-on"></i><span>Features & Offline</span></a>

<div class="t-group <?php echo $settingsOpen ? 'open' : ''; ?>" data-nav-group>
  <button type="button" class="t-link t-group-toggle <?php echo $settingsOpen ? 'active' : ''; ?>" aria-expanded="<?php echo $settingsOpen ? 'true' : 'false'; ?>">
    <i class="fas fa-gears"></i><span>Settings</span><i class="fas fa-chevron-down t-group-caret"></i>
  </button>
  <div class="t-subnav">
    <?php if ((int) ($__tenant['owner_user_id'] ?? 0) === (int) TenantContext::userId()): ?>
      <a class="t-sublink <?php echo $menuOn('/admins'); ?>" href="<?php echo public_url('super/admins/'); ?>">Admins</a>
    <?php endif; ?>
    <a class="t-sublink <?php echo $menuOn('/staff') && !$menuOn('/permissions'); ?>" href="<?php echo public_url('super/staff/'); ?>">Staff</a>
    <a class="t-sublink <?php echo $menuOn('/permissions'); ?>" href="<?php echo public_url('super/staff/permissions.php'); ?>">Permissions</a>
    <a class="t-sublink <?php echo $menuOn('/clean_migrations'); ?>" href="<?php echo public_url('clean_migrations.php'); ?>">Clean records</a>
    <a class="t-sublink <?php echo $menuOn('/settings'); ?>" href="<?php echo public_url('super/settings/'); ?>">Settings toggles</a>
  </div>
</div>
<?php else: ?>
<a class="t-link <?php echo $menuOn('/change-pin'); ?>" href="<?php echo public_url('staff/change-pin.php'); ?>">
  <i class="fas fa-key"></i><span>Change PIN</span>
</a>
<?php endif; ?>
<script>
(function(){
  var disabled=<?php echo json_encode(TenantFeatures::disabledRouteFragments());?>,root=document.currentScript.parentElement;
  root.querySelectorAll('a[href]').forEach(function(a){
    var path='';try{path=(new URL(a.href,location.href)).pathname.toLowerCase();}catch(e){}
    if(disabled.some(function(fragment){return path.indexOf(fragment)!==-1;}))a.remove();
  });
  root.querySelectorAll('[data-nav-group]').forEach(function(group){if(!group.querySelector('a[href]'))group.remove();});
})();
</script>
