<?php
namespace Models;
class ProductSerialModel extends Model
{
    protected string $table='product_serials';
    public function __construct(?\PDO $db=null){parent::__construct($db);$this->ensureSchema();}
    public function add(int $productId,string $text,bool $increaseStock=true): array
    {
        $serials=array_values(array_unique(array_filter(array_map('trim',preg_split('/[\\r\\n,]+/',$text)))));
        if(!$serials)return ['ok'=>false,'count'=>0,'error'=>'Enter at least one serial number or IMEI.'];
        $tid=\TenantContext::tenantId();
        try{$this->db->beginTransaction();$p=$this->db->prepare('SELECT id FROM products WHERE id=? AND tenant_id=? FOR UPDATE');$p->execute([$productId,$tid]);if(!$p->fetchColumn())throw new \RuntimeException('Product not found.');
          $ins=$this->db->prepare("INSERT INTO product_serials(tenant_id,product_id,serial_number,status) VALUES(?,?,?,'in_stock')");
          $count=0;foreach($serials as $serial){try{$ins->execute([$tid,$productId,$serial]);$count++;}catch(\PDOException $e){if($e->getCode()!=='23000')throw $e;}}
          if(!$count)throw new \RuntimeException('Those serial numbers are already recorded.');
          $this->db->prepare('UPDATE products SET serial_tracking=1,quantity=quantity+? WHERE id=? AND tenant_id=?')->execute([$increaseStock?$count:0,$productId,$tid]);
          $this->db->commit();return ['ok'=>true,'count'=>$count,'error'=>null];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return ['ok'=>false,'count'=>0,'error'=>$e->getMessage()];}
    }
    public function forProduct(int $productId): array {$st=$this->db->prepare('SELECT * FROM product_serials WHERE tenant_id=? AND product_id=? ORDER BY id DESC');$st->execute([\TenantContext::tenantId(),$productId]);return $st->fetchAll();}
    private function ensureSchema(): void
    {
      $this->db->exec("CREATE TABLE IF NOT EXISTS product_serials(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,product_id INT NOT NULL,serial_number VARCHAR(190) NOT NULL,status ENUM('in_stock','sold','returned') NOT NULL DEFAULT 'in_stock',order_item_id INT NULL,sold_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_tenant_serial(tenant_id,serial_number),KEY idx_serial_product(tenant_id,product_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      try{$this->db->query('SELECT serial_tracking FROM products LIMIT 1');}catch(\PDOException $e){$this->db->exec('ALTER TABLE products ADD COLUMN serial_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER is_menu_item');}
    }
}
