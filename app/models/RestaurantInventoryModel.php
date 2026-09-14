<?php
namespace Models;

class RestaurantInventoryModel extends Model
{
    protected string $table='restaurant_ingredients';
    public function __construct(?\PDO $db=null){parent::__construct($db);$this->ensureSchema();}

    public function ingredients(): array
    {
        $st=$this->db->prepare('SELECT * FROM restaurant_ingredients WHERE tenant_id=? AND is_active=1 ORDER BY name');
        $st->execute([\TenantContext::tenantId()]);return $st->fetchAll();
    }

    public function receive(array $in,int $userId): array
    {
        $tid=\TenantContext::tenantId();$name=trim((string)($in['name']??''));$packages=max(0,(float)($in['packages']??0));$per=max(0,(float)($in['units_per_package']??1));$cost=max(0,(float)($in['total_cost']??0));$qty=round($packages*$per,4);
        if(!$tid||$name===''||$qty<=0)return ['ok'=>false,'error'=>'Enter the stock name, package count and units in each package.'];
        $unit=trim((string)($in['base_unit']??'piece'))?:'piece';$packageUnit=trim((string)($in['package_unit']??'packet'))?:'packet';
        try{$this->db->beginTransaction();$st=$this->db->prepare('SELECT * FROM restaurant_ingredients WHERE tenant_id=? AND LOWER(name)=LOWER(?) FOR UPDATE');$st->execute([$tid,$name]);$row=$st->fetch();$batchUnit=$qty>0?$cost/$qty:0;
          if($row){$oldQty=(float)$row['quantity'];$oldCost=(float)$row['avg_unit_cost'];$newQty=$oldQty+$qty;$avg=$newQty>0?(($oldQty*$oldCost)+$cost)/$newQty:0;$id=(int)$row['id'];$this->db->prepare('UPDATE restaurant_ingredients SET quantity=?,avg_unit_cost=?,package_unit=?,units_per_package=?,base_unit=? WHERE id=?')->execute([$newQty,$avg,$packageUnit,$per,$unit,$id]);}
          else{$this->db->prepare('INSERT INTO restaurant_ingredients(tenant_id,name,package_unit,units_per_package,base_unit,quantity,avg_unit_cost) VALUES(?,?,?,?,?,?,?)')->execute([$tid,$name,$packageUnit,$per,$unit,$qty,$batchUnit]);$id=(int)$this->db->lastInsertId();}
          $this->db->prepare('INSERT INTO restaurant_stock_intakes(tenant_id,ingredient_id,package_quantity,units_per_package,quantity_received,total_cost,unit_cost,received_by) VALUES(?,?,?,?,?,?,?,?)')->execute([$tid,$id,$packages,$per,$qty,$cost,$batchUnit,$userId]);$this->db->commit();return ['ok'=>true,'id'=>$id,'quantity'=>$qty,'error'=>null];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return ['ok'=>false,'error'=>$e->getMessage()];}
    }

    public function variantsForProduct(int $productId): array
    {
        $st=$this->db->prepare("SELECT v.*,(SELECT COUNT(*) FROM menu_recipe_items r WHERE r.variant_id=v.id) recipe_count FROM menu_variants v WHERE v.tenant_id=? AND v.menu_product_id=? AND v.is_active=1 ORDER BY v.sort_order,v.id");
        $st->execute([\TenantContext::tenantId(),$productId]);return $st->fetchAll();
    }
    public function variantsForProducts(array $productIds): array
    {
        $productIds=array_values(array_filter(array_map('intval',$productIds)));if(!$productIds)return[];
        $in=implode(',',array_fill(0,count($productIds),'?'));$st=$this->db->prepare("SELECT v.* FROM menu_variants v WHERE v.tenant_id=? AND v.menu_product_id IN ($in) AND v.is_active=1 ORDER BY v.sort_order,v.id");$st->execute(array_merge([\TenantContext::tenantId()],$productIds));$out=[];foreach($st->fetchAll() as $r)$out[(int)$r['menu_product_id']][]=$r;return$out;
    }
    public function saveVariant(int $productId,array $in): array
    {
        $tid=\TenantContext::tenantId();$label=trim((string)($in['label']??''));$price=max(0,(float)($in['price']??0));if($label===''||$price<=0)return['ok'=>false,'error'=>'Enter the portion/variant name and selling price.'];
        $st=$this->db->prepare('SELECT is_menu_item FROM products WHERE id=? AND tenant_id=?');$st->execute([$productId,$tid]);if(!(int)$st->fetchColumn())return['ok'=>false,'error'=>'Menu item not found.'];
        $id=(int)($in['variant_id']??0);if($id){$this->db->prepare('UPDATE menu_variants SET label=?,retail_price=? WHERE id=? AND tenant_id=? AND menu_product_id=?')->execute([$label,$price,$id,$tid,$productId]);}
        else{$this->db->prepare('INSERT INTO menu_variants(tenant_id,menu_product_id,label,retail_price,sort_order) VALUES(?,?,?,?,?)')->execute([$tid,$productId,$label,$price,(int)($in['sort_order']??0)]);$id=(int)$this->db->lastInsertId();}
        return['ok'=>true,'id'=>$id,'error'=>null];
    }
    public function saveRecipe(int $variantId,array $lines): array
    {
        $tid=\TenantContext::tenantId();$v=$this->db->prepare('SELECT id FROM menu_variants WHERE id=? AND tenant_id=?');$v->execute([$variantId,$tid]);if(!$v->fetchColumn())return['ok'=>false,'error'=>'Variant not found.'];
        $clean=[];foreach($lines as $line){$iid=(int)($line['ingredient_id']??0);$qty=max(0,(float)($line['quantity']??0));if($iid&&$qty>0)$clean[$iid]=$qty;}if(!$clean)return['ok'=>false,'error'=>'Add at least one stock ingredient and quantity.'];
        try{$this->db->beginTransaction();$this->db->prepare('DELETE FROM menu_recipe_items WHERE tenant_id=? AND variant_id=?')->execute([$tid,$variantId]);$ins=$this->db->prepare('INSERT INTO menu_recipe_items(tenant_id,variant_id,ingredient_id,quantity) VALUES(?,?,?,?)');foreach($clean as $iid=>$qty)$ins->execute([$tid,$variantId,$iid,$qty]);$this->db->commit();return['ok'=>true,'error'=>null];}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return['ok'=>false,'error'=>$e->getMessage()];}
    }
    public function recipe(int $variantId): array {$st=$this->db->prepare('SELECT r.*,i.name,i.base_unit,i.quantity stock_quantity FROM menu_recipe_items r JOIN restaurant_ingredients i ON i.id=r.ingredient_id WHERE r.tenant_id=? AND r.variant_id=? ORDER BY i.name');$st->execute([\TenantContext::tenantId(),$variantId]);return$st->fetchAll();}
    public function variant(int $variantId,int $productId): ?array {$st=$this->db->prepare('SELECT * FROM menu_variants WHERE id=? AND tenant_id=? AND menu_product_id=? AND is_active=1');$st->execute([$variantId,\TenantContext::tenantId(),$productId]);return$st->fetch()?:null;}

    /** Called inside OrderModel's active transaction. */
    public function consume(int $variantId,float $portions,int $orderItemId): array
    {
        $tid=\TenantContext::tenantId();$recipe=$this->recipe($variantId);if(!$recipe)return['ok'=>false,'error'=>'This menu portion has no stock recipe.'];
        $total=0.0;$movement=$this->db->prepare("INSERT INTO restaurant_ingredient_movements(tenant_id,ingredient_id,order_item_id,delta_quantity,unit_cost,movement_type) VALUES(?,?,?,?,?,'sale')");
        foreach($recipe as $line){$need=round((float)$line['quantity']*$portions,4);$lock=$this->db->prepare('SELECT name,quantity,avg_unit_cost FROM restaurant_ingredients WHERE id=? AND tenant_id=? FOR UPDATE');$lock->execute([(int)$line['ingredient_id'],$tid]);$ingredient=$lock->fetch();if(!$ingredient||$need>(float)$ingredient['quantity']+.00001)return['ok'=>false,'error'=>'Not enough '.$line['name'].' stock. Need '.$need.' '.$line['base_unit'].'.'];$this->db->prepare('UPDATE restaurant_ingredients SET quantity=quantity-? WHERE id=? AND tenant_id=? AND quantity>=?')->execute([$need,(int)$line['ingredient_id'],$tid,$need]);$movement->execute([$tid,(int)$line['ingredient_id'],$orderItemId,-$need,(float)$ingredient['avg_unit_cost']]);$total+=($need*(float)$ingredient['avg_unit_cost']);}
        return['ok'=>true,'cogs'=>round($total,2),'error'=>null];
    }
    public function restoreOrderItem(int $orderItemId): bool
    {
        $tid=\TenantContext::tenantId();$st=$this->db->prepare("SELECT ingredient_id,SUM(delta_quantity) net,MAX(unit_cost) unit_cost FROM restaurant_ingredient_movements WHERE tenant_id=? AND order_item_id=? GROUP BY ingredient_id HAVING net<0");$st->execute([$tid,$orderItemId]);$rows=$st->fetchAll();if(!$rows)return false;$ins=$this->db->prepare("INSERT INTO restaurant_ingredient_movements(tenant_id,ingredient_id,order_item_id,delta_quantity,unit_cost,movement_type) VALUES(?,?,?,?,?,'restore')");foreach($rows as $r){$qty=abs((float)$r['net']);$this->db->prepare('UPDATE restaurant_ingredients SET quantity=quantity+? WHERE id=? AND tenant_id=?')->execute([$qty,(int)$r['ingredient_id'],$tid]);$ins->execute([$tid,(int)$r['ingredient_id'],$orderItemId,$qty,(float)$r['unit_cost']]);}return true;
    }
    public function summary(string $period='week'): array
    {
        $date=$period==='today'?'DATE(o.paid_at)=CURDATE()':($period==='month'?'o.paid_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)':'o.paid_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)');
        $st=$this->db->prepare("SELECT COALESCE(SUM(oi.line_total),0) revenue,COALESCE(SUM(oi.cogs_total),0) cogs,COALESCE(SUM(oi.quantity),0) portions FROM order_items oi JOIN orders o ON o.id=oi.order_id AND o.tenant_id=oi.tenant_id WHERE oi.tenant_id=? AND o.channel='restaurant' AND o.status='paid' AND $date");$st->execute([\TenantContext::tenantId()]);$r=$st->fetch()?:[];$r['profit']=round((float)($r['revenue']??0)-(float)($r['cogs']??0),2);return$r;
    }
    private function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS restaurant_ingredients(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,name VARCHAR(160) NOT NULL,package_unit VARCHAR(40) NOT NULL DEFAULT 'packet',units_per_package DECIMAL(12,4) NOT NULL DEFAULT 1,base_unit VARCHAR(40) NOT NULL DEFAULT 'piece',quantity DECIMAL(14,4) NOT NULL DEFAULT 0,avg_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_restaurant_ingredient(tenant_id,name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS restaurant_stock_intakes(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,ingredient_id INT NOT NULL,package_quantity DECIMAL(12,4) NOT NULL,units_per_package DECIMAL(12,4) NOT NULL,quantity_received DECIMAL(14,4) NOT NULL,total_cost DECIMAL(14,2) NOT NULL,unit_cost DECIMAL(14,4) NOT NULL,received_by INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_restaurant_intakes(tenant_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS menu_variants(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,menu_product_id INT NOT NULL,label VARCHAR(100) NOT NULL,retail_price DECIMAL(12,2) NOT NULL,sort_order INT NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_menu_variant(tenant_id,menu_product_id,label)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS menu_recipe_items(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,variant_id INT NOT NULL,ingredient_id INT NOT NULL,quantity DECIMAL(12,4) NOT NULL,UNIQUE KEY uq_menu_recipe(tenant_id,variant_id,ingredient_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS restaurant_ingredient_movements(id BIGINT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,ingredient_id INT NOT NULL,order_item_id INT NOT NULL,delta_quantity DECIMAL(14,4) NOT NULL,unit_cost DECIMAL(14,4) NOT NULL,movement_type VARCHAR(20) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_restaurant_movement(tenant_id,order_item_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach(['menu_variant_id'=>"ALTER TABLE order_items ADD COLUMN menu_variant_id INT NULL AFTER product_id",'unit_cogs'=>"ALTER TABLE order_items ADD COLUMN unit_cogs DECIMAL(14,4) NULL AFTER line_total",'cogs_total'=>"ALTER TABLE order_items ADD COLUMN cogs_total DECIMAL(14,2) NULL AFTER unit_cogs"] as $c=>$sql){try{$this->db->query("SELECT `$c` FROM order_items LIMIT 1");}catch(\PDOException $e){$this->db->exec($sql);}}
    }
}
