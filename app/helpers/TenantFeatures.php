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
    private static ?array $enabled=null;
    private static ?bool $offline=null;

    public static function enabled(string $module): bool
    {
        self::load();
        return in_array($module,self::$enabled??[],true);
    }
    public static function offlineEnabled(): bool { self::load();return self::$offline===true; }
    public static function reset(): void { self::$enabled=null;self::$offline=null; }

    private static function load(): void
    {
        if(self::$enabled!==null)return;
        self::$enabled=array_keys(self::MODULES);self::$offline=false;
        $tid=TenantContext::tenantId();if(!$tid)return;
        try{
            $st=Database::pdo()->prepare('SELECT enabled_modules,offline_enabled FROM tenants WHERE id=?');
            $st->execute([$tid]);$row=$st->fetch();
            if($row&&$row['enabled_modules']!==null&&$row['enabled_modules']!==''){
                $decoded=json_decode($row['enabled_modules'],true);
                if(is_array($decoded)){
                    if(!in_array(self::VERSION_MARKER,$decoded,true)&&!in_array('shop_pos',$decoded,true))$decoded[]='shop_pos';
                    self::$enabled=array_values(array_intersect(array_keys(self::MODULES),$decoded));
                }
            }
            self::$offline=!empty($row['offline_enabled']);
        }catch(Throwable $ignored){}
    }
}
