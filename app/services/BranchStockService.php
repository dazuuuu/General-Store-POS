<?php
class BranchStockService
{
    private PDO $db;private ?int $tid;private ?int $branchId;private bool $independent;
    public function __construct(?PDO $db=null){$this->db=$db?:Database::pdo();new Models\BranchModel($this->db);BranchContext::reset();$this->tid=TenantContext::tenantId();$this->branchId=BranchContext::id();$this->independent=BranchContext::isIndependent();}
    public function independent(): bool{return$this->independent;}
    public function branchId(): ?int{return$this->branchId;}
    public function forBranch(?int $branchId): self
    {
        $copy=clone $this;if(!$branchId)return$copy;$st=$this->db->prepare('SELECT inventory_mode FROM branches WHERE id=? AND tenant_id=?');$st->execute([$branchId,$this->tid]);$mode=$st->fetchColumn();if($mode!==false){$copy->branchId=$branchId;$copy->independent=$mode==='independent';}return$copy;
    }
    public function available(int $productId,bool $lock=false): float
    {
        if(!$this->independent){$st=$this->db->prepare('SELECT quantity FROM products WHERE id=? AND tenant_id=?'.($lock?' FOR UPDATE':''));$st->execute([$productId,$this->tid]);return(float)$st->fetchColumn();}
        $this->ensureRow($productId);$st=$this->db->prepare('SELECT quantity FROM branch_stock WHERE tenant_id=? AND branch_id=? AND product_id=?'.($lock?' FOR UPDATE':''));$st->execute([$this->tid,$this->branchId,$productId]);return(float)$st->fetchColumn();
    }
    public function adjust(int $productId,float $delta): bool
    {
        if(!$this->independent){if($delta<0){$need=abs($delta);$st=$this->db->prepare('UPDATE products SET quantity=quantity-? WHERE id=? AND tenant_id=? AND quantity>=?');$st->execute([$need,$productId,$this->tid,$need]);}else{$st=$this->db->prepare('UPDATE products SET quantity=quantity+? WHERE id=? AND tenant_id=?');$st->execute([$delta,$productId,$this->tid]);}return$st->rowCount()===1;}
        $this->ensureRow($productId);if($delta<0){$need=abs($delta);$st=$this->db->prepare('UPDATE branch_stock SET quantity=quantity-? WHERE tenant_id=? AND branch_id=? AND product_id=? AND quantity>=?');$st->execute([$need,$this->tid,$this->branchId,$productId,$need]);}else{$st=$this->db->prepare('UPDATE branch_stock SET quantity=quantity+? WHERE tenant_id=? AND branch_id=? AND product_id=?');$st->execute([$delta,$this->tid,$this->branchId,$productId]);}return$st->rowCount()===1;
    }
    public function overlay(array $products,bool $positiveOnly=false): array
    {
        if(!$this->independent||!$products)return$products;$ids=array_values(array_unique(array_map(fn($p)=>(int)$p['id'],$products)));$in=implode(',',array_fill(0,count($ids),'?'));$st=$this->db->prepare("SELECT product_id,quantity,faulty_quantity FROM branch_stock WHERE tenant_id=? AND branch_id=? AND product_id IN ($in)");$st->execute(array_merge([$this->tid,$this->branchId],$ids));$stock=[];foreach($st->fetchAll() as $r)$stock[(int)$r['product_id']]=$r;foreach($products as &$p){$row=$stock[(int)$p['id']]??null;$p['quantity']=(float)($row['quantity']??0);$p['faulty_quantity']=(float)($row['faulty_quantity']??0);}unset($p);return$positiveOnly?array_values(array_filter($products,fn($p)=>(float)$p['quantity']>0)):$products;
    }
    private function ensureRow(int $productId): void {$this->db->prepare('INSERT IGNORE INTO branch_stock(tenant_id,branch_id,product_id,quantity) VALUES(?,?,?,0)')->execute([$this->tid,$this->branchId,$productId]);}
}
