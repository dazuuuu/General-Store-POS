<?php
namespace Models;
class ProductSerialModel extends Model
{
    protected string $table='product_serials';
    public function __construct(?\PDO $db=null){parent::__construct($db);$this->ensureSchema();}
    public static function parse(string $text): array
    {
        return array_values(array_unique(array_filter(array_map('trim',preg_split('/[\\r\\n,]+/',$text)))));
    }
    public function add(int $productId,string $text,bool $increaseStock=true): array
    {
        $serials=self::parse($text);
        if(!$serials)return ['ok'=>false,'count'=>0,'error'=>'Enter at least one serial number or IMEI.'];
        $tid=\TenantContext::tenantId();
        $ownsTransaction=!$this->db->inTransaction();
        try{if($ownsTransaction)$this->db->beginTransaction();$p=$this->db->prepare('SELECT id,is_menu_item FROM products WHERE id=? AND tenant_id=? FOR UPDATE');$p->execute([$productId,$tid]);$product=$p->fetch();if(!$product)throw new \RuntimeException('Product not found.');if(!empty($product['is_menu_item']))throw new \RuntimeException('Serial numbers apply to inventory products, not restaurant menu items.');
          $ins=$this->db->prepare("INSERT INTO product_serials(tenant_id,product_id,serial_number,status) VALUES(?,?,?,'in_stock')");
          $count=0;foreach($serials as $serial){try{$ins->execute([$tid,$productId,$serial]);$count++;}catch(\PDOException $e){if($e->getCode()==='23000')throw new \RuntimeException('Serial number "'.$serial.'" is already recorded.');throw $e;}}
          if(!$count)throw new \RuntimeException('Those serial numbers are already recorded.');
          $this->db->prepare('UPDATE products SET serial_tracking=1,quantity=quantity+? WHERE id=? AND tenant_id=?')->execute([$increaseStock?$count:0,$productId,$tid]);
          if($ownsTransaction)$this->db->commit();return ['ok'=>true,'count'=>$count,'error'=>null];
        }catch(\Throwable $e){if($ownsTransaction&&$this->db->inTransaction())$this->db->rollBack();return ['ok'=>false,'count'=>0,'error'=>$e->getMessage()];}
    }
    public function forProduct(int $productId): array {$st=$this->db->prepare('SELECT * FROM product_serials WHERE tenant_id=? AND product_id=? ORDER BY id DESC');$st->execute([\TenantContext::tenantId(),$productId]);return $st->fetchAll();}
    public function findInStock(string $serial): ?array
    {
        $st=$this->db->prepare("SELECT ps.*,p.name product_name FROM product_serials ps JOIN products p ON p.id=ps.product_id AND p.tenant_id=ps.tenant_id WHERE ps.tenant_id=? AND ps.serial_number=? AND ps.status='in_stock' AND p.serial_tracking=1 AND COALESCE(p.is_menu_item,0)=0 LIMIT 1");
        $st->execute([\TenantContext::tenantId(),trim($serial)]);return$st->fetch()?:null;
    }
    public function countsForProducts(array $productIds): array
    {
        $ids=array_values(array_filter(array_map('intval',$productIds)));if(!$ids)return[];$in=implode(',',array_fill(0,count($ids),'?'));$st=$this->db->prepare("SELECT product_id,SUM(status='in_stock') in_stock,SUM(status='sold') sold,COUNT(*) total FROM product_serials WHERE tenant_id=? AND product_id IN ($in) GROUP BY product_id");$st->execute(array_merge([\TenantContext::tenantId()],$ids));$out=[];foreach($st->fetchAll() as $r)$out[(int)$r['product_id']]=$r;return$out;
    }
    private function ensureSchema(): void
    {
      $this->db->exec("CREATE TABLE IF NOT EXISTS product_serials(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,product_id INT NOT NULL,serial_number VARCHAR(190) NOT NULL,status ENUM('in_stock','sold','returned') NOT NULL DEFAULT 'in_stock',order_item_id INT NULL,sold_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_tenant_serial(tenant_id,serial_number),KEY idx_serial_product(tenant_id,product_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      try{$this->db->query('SELECT serial_tracking FROM products LIMIT 1');}catch(\PDOException $e){$this->db->exec('ALTER TABLE products ADD COLUMN serial_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER is_menu_item');}
    }
}
