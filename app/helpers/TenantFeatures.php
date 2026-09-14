<?php
/** Tenant-selectable business modules. Missing configuration means all legacy modules stay enabled. */
class TenantFeatures
{
    public const MODULES = [
        'shop_pos'=>'Shop POS & Retail Sales','store'=>'Store Warehouse','purchases'=>'Purchases','commissions'=>'Commission',
        'payroll'=>'Salary & Payroll','returns'=>'Returns','services'=>'Services',
        'restaurant_menu'=>'Restaurant Menu & Orders','serials'=>'Serial Numbers',
    ];
    public const VERSION_MARKER='__feature_settings_v2';
    public const PAGES = [
        'pos'=>'Shop checkout','sales'=>'Sales history','invoices'=>'Invoices','credit_sales'=>'Credit sales & held sales',
        'customers'=>'Customers & loyalty','reports'=>'Reports','data_export'=>'Data export','documents'=>'Documents',
        'inventory'=>'Shop inventory','suppliers'=>'Suppliers','stock_entry'=>'Record stock','low_stock'=>'Low stock alerts',
        'brands_categories'=>'Brands & categories','finances'=>'Finances','expenses'=>'Expenses','taxes'=>'Taxes',
        'branches'=>'Branches','admins'=>'Admins','staff'=>'Staff','permissions'=>'Permissions',
        'clean_records'=>'Clean records','settings'=>'Business settings','features'=>'Feature status page',
    ];
    public const PAGE_VERSION_MARKER='__page_settings_v1';
    /** More-specific paths must come first. Dashboard and authentication are always available. */
    public const PAGE_ROUTES = [
        '/orders/held'=>'credit_sales','/orders/new'=>'credit_sales','/orders/'=>'credit_sales',
        '/inventory/low-stock'=>'low_stock','/inventory/serials'=>'inventory','/inventory/'=>'inventory',
        '/stationery/'=>'stock_entry','/stock/'=>'stock_entry','/suppliers/'=>'suppliers',
        '/publishers/'=>'brands_categories','/categories/'=>'brands_categories','/subcategories/'=>'brands_categories',
        '/sales/'=>'sales','/invoices/'=>'invoices','/customers/'=>'customers','/reports/'=>'reports',
        '/data/'=>'data_export','/documents/'=>'documents','/finances/'=>'finances','/expenses/'=>'expenses',
        '/taxes/'=>'taxes','/branches/'=>'branches','/admins/'=>'admins','/staff/permissions'=>'permissions',
        '/staff/'=>'staff','/clean_migrations'=>'clean_records','/settings/'=>'settings','/features/'=>'features',
        '/shop/'=>'pos','/staff/dashboard/'=>'pos',
    ];
    private static ?array $enabled=null;
    private static ?array $pages=null;
    private static ?bool $offline=null;
    private static int $revision=0;

    public static function enabled(string $module): bool
    {
        self::load();
        return in_array($module,self::$enabled??[],true);
    }
    public static function offlineEnabled(): bool { self::load();return self::$offline===true; }
    public static function pageEnabled(string $page): bool { self::load();return in_array($page,self::$pages??[],true); }
    public static function revision(): int { self::load();return self::$revision; }
    public static function pageForPath(string $path): ?string
    {
        $path=strtolower((string)(parse_url($path,PHP_URL_PATH)??$path));
        foreach(self::PAGE_ROUTES as $fragment=>$page)if(strpos($path,$fragment)!==false)return $page;
        return null;
    }
    public static function pathEnabled(string $path): bool
    {
        $page=self::pageForPath($path);
        return $page===null||self::pageEnabled($page);
    }
    public static function disabledRouteFragments(): array
    {
        self::load();$out=[];
        foreach(self::PAGE_ROUTES as $fragment=>$page)if(!self::pageEnabled($page))$out[]=$fragment;
        return $out;
    }
    public static function reset(): void { self::$enabled=null;self::$pages=null;self::$offline=null;self::$revision=0; }

    private static function load(): void
    {
        if(self::$enabled!==null)return;
        self::$enabled=array_keys(self::MODULES);self::$pages=array_keys(self::PAGES);self::$offline=false;self::$revision=0;
        $tid=TenantContext::tenantId();if(!$tid)return;
        try{
            $st=Database::pdo()->prepare('SELECT enabled_modules,enabled_pages,offline_enabled,settings_revision FROM tenants WHERE id=?');
            $st->execute([$tid]);$row=$st->fetch();
            if($row&&$row['enabled_modules']!==null&&$row['enabled_modules']!==''){
                $decoded=json_decode($row['enabled_modules'],true);
                if(is_array($decoded)){
                    if(!in_array(self::VERSION_MARKER,$decoded,true)&&!in_array('shop_pos',$decoded,true))$decoded[]='shop_pos';
                    self::$enabled=array_values(array_intersect(array_keys(self::MODULES),$decoded));
                }
            }
            if($row&&$row['enabled_pages']!==null&&$row['enabled_pages']!==''){
                $decoded=json_decode($row['enabled_pages'],true);
                if(is_array($decoded))self::$pages=array_values(array_intersect(array_keys(self::PAGES),$decoded));
            }
            self::$offline=!empty($row['offline_enabled']);
            self::$revision=(int)($row['settings_revision']??0);
        }catch(Throwable $ignored){}
    }
}
