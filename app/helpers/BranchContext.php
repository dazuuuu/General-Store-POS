<?php
class BranchContext
{
    private static ?array $branch=null;private static bool $loaded=false;
    public static function id(): ?int
    {
        self::load();return self::$branch?(int)self::$branch['id']:null;
    }
    public static function current(): ?array {self::load();return self::$branch;}
    public static function isIndependent(): bool {return (self::current()['inventory_mode']??'shared')==='independent';}
    public static function select(int $branchId): bool
    {
        $tid=TenantContext::tenantId();if(!$tid)return false;$st=Database::pdo()->prepare('SELECT * FROM branches WHERE id=? AND tenant_id=? AND is_active=1');$st->execute([$branchId,$tid]);$row=$st->fetch();if(!$row)return false;$_SESSION['branch_id']=$branchId;self::$branch=$row;self::$loaded=true;return true;
    }
    public static function reset(): void {self::$branch=null;self::$loaded=false;}
    private static function load(): void
    {
        if(self::$loaded)return;self::$loaded=true;$tid=TenantContext::tenantId();if(!$tid)return;
        try{$id=(int)($_SESSION['branch_id']??0);if(!$id&&TenantContext::userId()){$u=Database::pdo()->prepare('SELECT branch_id FROM users WHERE id=? AND tenant_id=?');$u->execute([TenantContext::userId(),$tid]);$id=(int)$u->fetchColumn();}$sql=$id?'SELECT * FROM branches WHERE id=? AND tenant_id=? AND is_active=1':'SELECT * FROM branches WHERE tenant_id=? AND is_active=1 ORDER BY is_default DESC,id LIMIT 1';$st=Database::pdo()->prepare($sql);$st->execute($id?[$id,$tid]:[$tid]);self::$branch=$st->fetch()?:null;if(self::$branch)$_SESSION['branch_id']=(int)self::$branch['id'];}catch(Throwable $e){self::$branch=null;}
    }
}
