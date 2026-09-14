<?php

/** Platform-only write service for tenant access and account lifecycle. */
class TenantSupportService
{
    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        $st=$this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME IN ('enabled_pages','settings_revision')");
        return (int)$st->fetchColumn()===2;
    }

    public function tenant(int $id): ?array
    {
        $st=$this->db->prepare(
            "SELECT t.*,u.username owner_name,u.email owner_email,
                    (SELECT COUNT(*) FROM users x WHERE x.tenant_id=t.id) user_count,
                    (SELECT COUNT(*) FROM branches b WHERE b.tenant_id=t.id) branch_count
               FROM tenants t LEFT JOIN users u ON u.id=t.owner_user_id
              WHERE t.id=? LIMIT 1"
        );
        $st->execute([$id]);$row=$st->fetch();
        return $row?:null;
    }

    public function saveAccess(int $tenantId,array $modules,array $pages,bool $offline,int $actorId): void
    {
        $modules=array_values(array_intersect(array_keys(TenantFeatures::MODULES),$modules));
        $pages=array_values(array_intersect(array_keys(TenantFeatures::PAGES),$pages));
        $moduleJson=json_encode(array_merge($modules,[TenantFeatures::VERSION_MARKER]));
        $pageJson=json_encode(array_merge($pages,[TenantFeatures::PAGE_VERSION_MARKER]));
        $this->db->beginTransaction();
        try{
            $st=$this->db->prepare('UPDATE tenants SET enabled_modules=?,enabled_pages=?,offline_enabled=?,settings_revision=settings_revision+1 WHERE id=?');
            $st->execute([$moduleJson,$pageJson,$offline?1:0,$tenantId]);
            if(!$st->rowCount()&&!$this->tenant($tenantId))throw new RuntimeException('Business account not found.');
            $this->audit($actorId,$tenantId,'tenant_access_updated',json_encode(['modules'=>$modules,'pages'=>$pages,'offline_enabled'=>$offline]));
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function setStatus(int $tenantId,string $status,int $actorId): void
    {
        if(!in_array($status,['active','suspended'],true))throw new InvalidArgumentException('Unsupported account status.');
        $this->db->beginTransaction();
        try{
            $st=$this->db->prepare('UPDATE tenants SET status=?,settings_revision=settings_revision+1 WHERE id=?');
            $st->execute([$status,$tenantId]);
            if(!$st->rowCount()&&!$this->tenant($tenantId))throw new RuntimeException('Business account not found.');
            // Branch data is preserved; access is closed while the tenant is suspended.
            $this->db->prepare('UPDATE branches SET is_active=? WHERE tenant_id=?')->execute([$status==='active'?1:0,$tenantId]);
            $this->audit($actorId,$tenantId,$status==='active'?'tenant_reopened':'tenant_suspended',null);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function audit(int $actorId,int $tenantId,string $action,?string $details): void
    {
        $this->db->prepare('INSERT INTO platform_audit_log(platform_user_id,tenant_id,action,details) VALUES (?,?,?,?)')
            ->execute([$actorId?:null,$tenantId?:null,$action,$details]);
    }
}
