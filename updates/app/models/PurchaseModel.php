<?php
// app/models/PurchaseModel.php
// Purchases holding area: record supplier buys (receipt optional), view/track,
// then transfer into Store warehouse with optional wholesale/retail prices.
namespace Models;

class PurchaseModel extends Model
{
    protected string $table = 'purchases';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    /**
     * @param array $header shop_name, supplier_id, receipt_number, receipt_image_path,
     *                      purchase_date, notes, staff_id
     * @param array $items  product lines (all fields optional)
     * @return array{ok:bool,purchase_id:?int,errors:array}
     */
    public function create(array $header, array $items): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'purchase_id' => null, 'errors' => ['_' => 'No shop in context.']];
        }

        $staffId = (int) ($header['staff_id'] ?? 0);
        $normalized = [];
        foreach ($items as $item) {
            $line = $this->normalizeItem($item);
            if ($line !== null) {
                $normalized[] = $line;
            }
        }
        if (!$normalized) {
            // Allow a header-only purchase record (receipt/shop note) with one blank line.
            $normalized[] = $this->normalizeItem(['name' => '']) ?? [
                'name' => 'Purchase ' . date('j M H:i'),
                'category_id' => null,
                'brand_id' => null,
                'barcode' => null,
                'unit' => 'piece',
                'package_unit' => null,
                'package_quantity' => null,
                'units_per_package' => 1.0,
                'variant_label' => null,
                'colors' => null,
                'quantity' => 0.0,
                'faulty_quantity' => 0.0,
                'buying_price' => 0.0,
                'package_buying_price' => null,
                'wholesale_price' => null,
                'package_price' => null,
                'retail_price' => null,
                'retail_pack_price' => null,
                'image_path' => null,
                'notes' => null,
            ];
        }

        $supplierId = (int) ($header['supplier_id'] ?? 0);
        if ($supplierId > 0 && !$this->supplierBelongsToTenant($supplierId)) {
            $supplierId = 0;
        }

        try {
            $this->db->beginTransaction();
            $this->db->prepare(
                'INSERT INTO purchases
                    (tenant_id, supplier_id, shop_name, receipt_number, receipt_image_path, purchase_date, notes, staff_id, transfer_destination, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $tid,
                $supplierId > 0 ? $supplierId : null,
                $this->nullIfBlank($header['shop_name'] ?? null),
                $this->nullIfBlank($header['receipt_number'] ?? null),
                $this->nullIfBlank($header['receipt_image_path'] ?? null),
                $this->dateOrNull($header['purchase_date'] ?? null),
                $this->nullIfBlank($header['notes'] ?? null),
                $staffId > 0 ? $staffId : null,
                ($header['transfer_destination'] ?? '') === 'store' ? 'store' : 'shop',
                'recorded',
            ]);
            $purchaseId = (int) $this->db->lastInsertId();
            if ($purchaseId <= 0) {
                throw new \RuntimeException('Could not save purchase header.');
            }

            $ins = $this->db->prepare(
                'INSERT INTO purchase_items
                    (tenant_id, purchase_id, name, category_id, brand_id, barcode, unit, package_unit,
                     package_quantity, units_per_package, variant_label, colors, quantity, faulty_quantity,
                     buying_price, package_buying_price, wholesale_price, package_price, retail_price,
                     retail_pack_price, image_path, notes, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach ($normalized as $line) {
                $ins->execute([
                    $tid, $purchaseId, $line['name'], $line['category_id'], $line['brand_id'], $line['barcode'],
                    $line['unit'], $line['package_unit'], $line['package_quantity'], $line['units_per_package'],
                    $line['variant_label'], $line['colors'], $line['quantity'], $line['faulty_quantity'],
                    $line['buying_price'], $line['package_buying_price'], $line['wholesale_price'],
                    $line['package_price'], $line['retail_price'], $line['retail_pack_price'],
                    $line['image_path'], $line['notes'], 'pending',
                ]);
            }

            $this->db->commit();
            return ['ok' => true, 'purchase_id' => $purchaseId, 'errors' => []];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('PurchaseModel::create failed: ' . $e->getMessage());
            return ['ok' => false, 'purchase_id' => null, 'errors' => ['_' => 'Could not save this purchase. Please try again.']];
        }
    }

    /** List purchases with supplier + item counts for view/track pages. */
    public function listWithMeta(array $filters = [], int $limit = 100): array
    {
        $tid = \TenantContext::tenantId();
        $where = ['p.tenant_id = ?'];
        $params = [$tid];

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, ['recorded', 'partial', 'transferred'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $where[] = 'p.supplier_id = ?';
            $params[] = $supplierId;
        }
        $shop = trim((string) ($filters['shop_name'] ?? ''));
        if ($shop !== '') {
            $where[] = '(p.shop_name LIKE ? OR s.name LIKE ?)';
            $params[] = '%' . $shop . '%';
            $params[] = '%' . $shop . '%';
        }
        $receipt = trim((string) ($filters['receipt_number'] ?? ''));
        if ($receipt !== '') {
            $where[] = 'p.receipt_number LIKE ?';
            $params[] = '%' . $receipt . '%';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.shop_name LIKE ? OR p.receipt_number LIKE ? OR p.notes LIKE ? OR s.name LIKE ?
                        OR EXISTS (
                            SELECT 1 FROM purchase_items pi
                             WHERE pi.purchase_id = p.id AND pi.tenant_id = p.tenant_id
                               AND (pi.name LIKE ? OR pi.barcode LIKE ? OR pi.variant_label LIKE ?)
                        ))';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        $from = $this->dateOrNull($filters['date_from'] ?? null);
        if ($from) {
            $where[] = 'COALESCE(p.purchase_date, DATE(p.created_at)) >= ?';
            $params[] = $from;
        }
        $to = $this->dateOrNull($filters['date_to'] ?? null);
        if ($to) {
            $where[] = 'COALESCE(p.purchase_date, DATE(p.created_at)) <= ?';
            $params[] = $to;
        }

        $sql = "SELECT p.*, s.name AS supplier_name, u.username AS staff_name,
                       (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS item_count,
                       (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.id AND pi.status = 'pending') AS pending_count,
                       (SELECT COALESCE(SUM(
                            CASE
                              WHEN pi.package_buying_price IS NOT NULL AND pi.package_quantity IS NOT NULL
                                THEN pi.package_buying_price * pi.package_quantity
                              ELSE pi.buying_price * pi.quantity
                            END
                       ), 0) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS cost_total,
                       (SELECT GROUP_CONCAT(DISTINCT
                            TRIM(CONCAT(COALESCE(pi.name, ''), IF(pi.variant_label IS NULL OR pi.variant_label = '', '', CONCAT(' ', pi.variant_label))))
                            SEPARATOR ', '
                        ) FROM purchase_items pi WHERE pi.purchase_id = p.id AND COALESCE(pi.name, '') <> '') AS product_names
                  FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN users u ON u.id = p.staff_id
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY COALESCE(p.purchase_date, DATE(p.created_at)) DESC, p.id DESC
                 LIMIT " . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findWithMeta(int $id): ?array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT p.*, s.name AS supplier_name, u.username AS staff_name
               FROM purchases p
          LEFT JOIN suppliers s ON s.id = p.supplier_id
          LEFT JOIN users u ON u.id = p.staff_id
              WHERE p.id = ? AND p.tenant_id = ?
              LIMIT 1"
        );
        $stmt->execute([$id, $tid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function itemsFor(int $purchaseId, ?string $status = null): array
    {
        $tid = \TenantContext::tenantId();
        $sql = "SELECT pi.*, c.name AS category_name, br.name AS brand_name
                  FROM purchase_items pi
             LEFT JOIN categories c ON c.id = pi.category_id
             LEFT JOIN book_attributes br ON br.id = pi.brand_id
                 WHERE pi.purchase_id = ? AND pi.tenant_id = ?";
        $params = [$purchaseId, $tid];
        if ($status !== null) {
            $sql .= ' AND pi.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY pi.id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Pending items across purchases for the transfer screen (+ advanced search). */
    public function pendingItems(array $filters = [], int $limit = 200): array
    {
        $tid = \TenantContext::tenantId();
        $where = ["pi.tenant_id = ?", "pi.status = 'pending'"];
        $params = [$tid];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(pi.name LIKE ? OR pi.barcode LIKE ? OR pi.variant_label LIKE ? OR p.shop_name LIKE ? OR p.receipt_number LIKE ? OR s.name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        $purchaseId = (int) ($filters['purchase_id'] ?? 0);
        if ($purchaseId > 0) {
            $where[] = 'pi.purchase_id = ?';
            $params[] = $purchaseId;
        }
        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $where[] = 'p.supplier_id = ?';
            $params[] = $supplierId;
        }

        $sql = "SELECT pi.*, p.shop_name, p.receipt_number, p.purchase_date, p.transfer_destination, p.created_at AS purchase_created_at,
                       p.supplier_id, s.name AS supplier_name, c.name AS category_name, br.name AS brand_name
                  FROM purchase_items pi
                  JOIN purchases p ON p.id = pi.purchase_id AND p.tenant_id = pi.tenant_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN categories c ON c.id = pi.category_id
             LEFT JOIN book_attributes br ON br.id = pi.brand_id
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY p.created_at DESC, pi.id ASC
                 LIMIT " . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Transfer selected purchase items into Store warehouse.
     * Sell prices can be overridden per line at transfer time (all optional).
     * Supports partial transfers (e.g. move 2000kg of 10000kg, or 2 of 6 bales).
     * Optional quantity-discount tiers are saved onto the resulting inventory product
     * after Store → Inventory (stored on store product notes JSON + applied when
     * the inventory product is created/updated).
     *
     * @param array $selections [purchase_item_id => override fields]
     */
    public function transferToStore(array $selections, int $staffId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', array_keys($selections)))));
        if (!$ids) {
            return ['ok' => false, 'created' => 0, 'error' => 'Select at least one purchase item to transfer.'];
        }
        $tid = \TenantContext::tenantId();
        $store = new StoreProductModel($this->db);

        try {
            $this->db->beginTransaction();
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db->prepare(
                "SELECT pi.*, p.supplier_id, p.shop_name
                   FROM purchase_items pi
                   JOIN purchases p ON p.id = pi.purchase_id AND p.tenant_id = pi.tenant_id
                  WHERE pi.tenant_id = ? AND pi.status = 'pending' AND pi.id IN ($in)
               FOR UPDATE"
            );
            $stmt->execute(array_merge([$tid], $ids));
            $rows = $stmt->fetchAll();
            if (!$rows) {
                $this->db->rollBack();
                return ['ok' => false, 'created' => 0, 'error' => 'No pending purchase items found for transfer.'];
            }

            $storeItems = [];
            $touchedPurchases = [];
            $tierPlans = []; // store_product match key => tiers
            $partialUpdates = []; // purchase_item_id => remaining fields

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $over = is_array($selections[$id] ?? null) ? $selections[$id] : [];
                $split = $this->splitTransferQuantity($row, $over);
                if ($split['transfer_qty'] <= 0) {
                    continue;
                }
                $merged = $this->mergeTransferOverrides($row, $over, $split['transfer_qty'], $split['transfer_packages']);
                $storeItems[] = $merged;
                $touchedPurchases[(int) $row['purchase_id']] = true;
                $tierPlans[] = [
                    'name' => $merged['name'],
                    'barcode' => $merged['barcode'] ?? '',
                    'tiers' => $this->normalizeTierInput($over['tiers'] ?? []),
                ];
                $partialUpdates[$id] = [
                    'remaining_qty' => $split['remaining_qty'],
                    'remaining_packages' => $split['remaining_packages'],
                    'merged' => $merged,
                ];
            }

            if (!$storeItems) {
                $this->db->rollBack();
                return ['ok' => false, 'created' => 0, 'error' => 'Enter how much to transfer for at least one selected item.'];
            }

            $res = $store->createMany($storeItems, $staffId);
            if (!$res['ok']) {
                $this->db->rollBack();
                return ['ok' => false, 'created' => 0, 'error' => $res['error'] ?? 'Could not create store products.'];
            }

            $markDone = $this->db->prepare(
                "UPDATE purchase_items
                    SET status = 'transferred', store_product_id = ?, quantity = ?, package_quantity = ?,
                        wholesale_price = ?, package_price = ?, retail_price = ?, retail_pack_price = ?,
                        transferred_at = NOW()
                  WHERE id = ? AND tenant_id = ?"
            );
            $keepPending = $this->db->prepare(
                "UPDATE purchase_items
                    SET quantity = ?, package_quantity = ?,
                        wholesale_price = COALESCE(?, wholesale_price),
                        package_price = COALESCE(?, package_price),
                        retail_price = COALESCE(?, retail_price),
                        retail_pack_price = COALESCE(?, retail_pack_price)
                  WHERE id = ? AND tenant_id = ? AND status = 'pending'"
            );
            $insertTransferred = $this->db->prepare(
                'INSERT INTO purchase_items
                    (tenant_id, purchase_id, name, category_id, brand_id, barcode, unit, package_unit,
                     package_quantity, units_per_package, variant_label, colors, quantity, faulty_quantity,
                     buying_price, package_buying_price, wholesale_price, package_price, retail_price,
                     retail_pack_price, image_path, notes, status, store_product_id, transferred_at)
                 SELECT tenant_id, purchase_id, name, category_id, brand_id, barcode, unit, package_unit,
                        ?, units_per_package, variant_label, colors, ?, 0,
                        buying_price, package_buying_price, ?, ?, ?,
                        ?, image_path, notes, \'transferred\', ?, NOW()
                   FROM purchase_items WHERE id = ? AND tenant_id = ? LIMIT 1'
            );

            foreach ($partialUpdates as $id => $info) {
                $merged = $info['merged'];
                $storeId = $this->findLatestStoreProductId($merged['name'], $merged['barcode'] ?? null);
                $ws = $merged['wholesale_price'] !== '' && $merged['wholesale_price'] !== null ? (float) $merged['wholesale_price'] : null;
                $pp = $merged['package_price'] !== '' && $merged['package_price'] !== null ? (float) $merged['package_price'] : null;
                $rp = $merged['retail_price'] !== '' && $merged['retail_price'] !== null ? (float) $merged['retail_price'] : null;
                $rpp = $merged['retail_pack_price'] !== '' && $merged['retail_pack_price'] !== null ? (float) $merged['retail_pack_price'] : null;

                if ($info['remaining_qty'] > 0.0001) {
                    // Keep original line pending with remaining stock; record transferred portion separately
                    // so purchase status becomes partial.
                    $keepPending->execute([
                        $info['remaining_qty'],
                        $info['remaining_packages'],
                        $ws, $pp, $rp, $rpp,
                        $id, $tid,
                    ]);
                    $insertTransferred->execute([
                        $merged['package_quantity'],
                        (float) $merged['quantity'],
                        $ws, $pp, $rp, $rpp,
                        $storeId,
                        $id, $tid,
                    ]);
                } else {
                    $markDone->execute([
                        $storeId,
                        (float) $merged['quantity'],
                        $merged['package_quantity'],
                        $ws, $pp, $rp, $rpp,
                        $id, $tid,
                    ]);
                }

                // Stash tiers on the store product notes as JSON so Inventory transfer can apply them.
                if ($storeId && !empty($tierPlans)) {
                    foreach ($tierPlans as $plan) {
                        if ($plan['name'] !== $merged['name']) {
                            continue;
                        }
                        if (($plan['barcode'] ?? '') !== '' && ($merged['barcode'] ?? '') !== '' && $plan['barcode'] !== $merged['barcode']) {
                            continue;
                        }
                        if ($plan['tiers']) {
                            $this->attachTiersToStoreProduct((int) $storeId, $plan['tiers']);
                        }
                        break;
                    }
                }
            }

            foreach (array_keys($touchedPurchases) as $purchaseId) {
                $this->refreshPurchaseStatus((int) $purchaseId);
            }

            $this->db->commit();
            return [
                'ok' => true,
                'created' => (int) ($res['created'] ?? count($storeItems)),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('PurchaseModel::transferToStore failed: ' . $e->getMessage());
            return ['ok' => false, 'created' => 0, 'error' => 'Could not transfer purchases to Store. ' . $e->getMessage()];
        }
    }

    /** Route each purchase according to the destination chosen while recording it. */
    public function transferSelected(array $selections, int $staffId): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',array_keys($selections)))));
        if(!$ids)return ['ok'=>false,'created'=>0,'error'=>'Select at least one purchase item to transfer.'];
        $in=implode(',',array_fill(0,count($ids),'?'));
        $st=$this->db->prepare("SELECT pi.id,COALESCE(p.transfer_destination,'shop') destination FROM purchase_items pi JOIN purchases p ON p.id=pi.purchase_id AND p.tenant_id=pi.tenant_id WHERE pi.tenant_id=? AND pi.id IN ($in)");
        $st->execute(array_merge([\TenantContext::tenantId()],$ids));
        $groups=['store'=>[],'shop'=>[]];
        foreach($st->fetchAll() as $row){$d=$row['destination']==='store'?'store':'shop';$groups[$d][(int)$row['id']]=$selections[(int)$row['id']];}
        $created=0;
        foreach($groups as $destination=>$lines){
            if(!$lines)continue;
            $res=$destination==='store'?$this->transferToStore($lines,$staffId):$this->transferToShop($lines,$staffId);
            if(!$res['ok'])return $res;
            $created+=(int)$res['created'];
        }
        return ['ok'=>true,'created'=>$created,'error'=>null];
    }

    /** Transfer purchase lines directly into Shop Inventory, skipping Store Warehouse. */
    public function transferToShop(array $selections,int $staffId): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',array_keys($selections)))));
        if(!$ids)return ['ok'=>false,'created'=>0,'error'=>'Select at least one purchase item to transfer.'];
        $tid=\TenantContext::tenantId();
        // Constructors may perform compatibility DDL; run them before the transaction.
        $store=new StoreProductModel($this->db);
        $products=new ProductModel($this->db);
        try{
            $this->db->beginTransaction();
            $in=implode(',',array_fill(0,count($ids),'?'));
            $st=$this->db->prepare("SELECT pi.*,p.supplier_id,p.shop_name FROM purchase_items pi JOIN purchases p ON p.id=pi.purchase_id AND p.tenant_id=pi.tenant_id WHERE pi.tenant_id=? AND pi.status='pending' AND pi.id IN ($in) FOR UPDATE");
            $st->execute(array_merge([$tid],$ids));$rows=$st->fetchAll();
            $keep=$this->db->prepare("UPDATE purchase_items SET quantity=?,package_quantity=?,wholesale_price=COALESCE(?,wholesale_price),package_price=COALESCE(?,package_price),retail_price=COALESCE(?,retail_price),retail_pack_price=COALESCE(?,retail_pack_price) WHERE id=? AND tenant_id=? AND status='pending'");
            $done=$this->db->prepare("UPDATE purchase_items SET status='transferred',product_id=?,store_product_id=NULL,quantity=?,package_quantity=?,wholesale_price=?,package_price=?,retail_price=?,retail_pack_price=?,transferred_at=NOW() WHERE id=? AND tenant_id=?");
            $copy=$this->db->prepare("INSERT INTO purchase_items(tenant_id,purchase_id,name,category_id,brand_id,barcode,unit,package_unit,package_quantity,units_per_package,variant_label,colors,quantity,faulty_quantity,buying_price,package_buying_price,wholesale_price,package_price,retail_price,retail_pack_price,image_path,notes,status,product_id,transferred_at) SELECT tenant_id,purchase_id,name,category_id,brand_id,barcode,unit,package_unit,?,units_per_package,variant_label,colors,?,0,buying_price,package_buying_price,?,?,?,?,image_path,notes,'transferred',?,NOW() FROM purchase_items WHERE id=? AND tenant_id=?");
            $created=0;$purchases=[];
            foreach($rows as $row){
                $id=(int)$row['id'];$over=(array)($selections[$id]??[]);$split=$this->splitTransferQuantity($row,$over);
                if($split['transfer_qty']<=0)continue;
                $item=$this->mergeTransferOverrides($row,$over,$split['transfer_qty'],$split['transfer_packages']);
                $productId=$store->upsertDirectInventory($products,$item,$this->normalizeTierInput($over['tiers']??[]));
                $vals=[
                    $item['wholesale_price']!==''?(float)$item['wholesale_price']:null,
                    $item['package_price']!==''?(float)$item['package_price']:null,
                    $item['retail_price']!==''?(float)$item['retail_price']:null,
                    $item['retail_pack_price']!==''?(float)$item['retail_pack_price']:null,
                ];
                if($split['remaining_qty']>0.0001){
                    $keep->execute([$split['remaining_qty'],$split['remaining_packages'],$vals[0],$vals[1],$vals[2],$vals[3],$id,$tid]);
                    $copy->execute([$item['package_quantity'],$item['quantity'],$vals[0],$vals[1],$vals[2],$vals[3],$productId,$id,$tid]);
                }else{
                    $done->execute([$productId,$item['quantity'],$item['package_quantity'],$vals[0],$vals[1],$vals[2],$vals[3],$id,$tid]);
                }
                $purchases[(int)$row['purchase_id']]=true;$created++;
            }
            if(!$created)throw new \RuntimeException('Enter how much to transfer for at least one item.');
            foreach(array_keys($purchases) as $purchaseId)$this->refreshPurchaseStatus($purchaseId);
            $this->db->commit();return ['ok'=>true,'created'=>$created,'error'=>null];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return ['ok'=>false,'created'=>0,'error'=>'Could not transfer purchases to Shop Inventory. '.$e->getMessage()];}
    }

    /** @return array{transfer_qty:float,transfer_packages:?float,remaining_qty:float,remaining_packages:?float} */
    private function splitTransferQuantity(array $row, array $over): array
    {
        $availableQty = (float) ($row['quantity'] ?? 0);
        $inside = max(0.01, (float) ($row['units_per_package'] ?? 1));
        $availablePkgs = ($row['package_quantity'] ?? '') !== '' ? (float) $row['package_quantity'] : null;
        if (($availablePkgs === null || $availablePkgs <= 0) && $availableQty > 0 && !empty($row['package_unit'])) {
            $availablePkgs = round($availableQty / $inside, 4);
        }

        $wantQty = ($over['transfer_quantity'] ?? '') !== '' ? max(0, (float) $over['transfer_quantity']) : null;
        $wantPkgs = ($over['transfer_packages'] ?? '') !== '' ? max(0, (float) $over['transfer_packages']) : null;

        // Default: transfer everything remaining.
        if ($wantQty === null && $wantPkgs === null) {
            $wantQty = $availableQty;
            $wantPkgs = $availablePkgs;
        } elseif ($wantPkgs !== null && $wantQty === null) {
            $wantQty = round($wantPkgs * $inside, 4);
        } elseif ($wantQty !== null && $wantPkgs === null && $availablePkgs !== null && $inside > 0) {
            $wantPkgs = round($wantQty / $inside, 4);
        }

        $transferQty = min($availableQty, (float) $wantQty);
        if ($transferQty <= 0) {
            return ['transfer_qty' => 0.0, 'transfer_packages' => null, 'remaining_qty' => $availableQty, 'remaining_packages' => $availablePkgs];
        }
        $transferPkgs = $wantPkgs !== null ? min((float) ($availablePkgs ?? $wantPkgs), (float) $wantPkgs) : null;
        if ($transferPkgs !== null && $transferPkgs > 0 && ($wantQty === null || ($over['transfer_quantity'] ?? '') === '')) {
            $transferQty = min($availableQty, round($transferPkgs * $inside, 4));
        }
        $remainingQty = max(0, round($availableQty - $transferQty, 4));
        $remainingPkgs = null;
        if ($availablePkgs !== null) {
            $usedPkgs = $transferPkgs !== null ? $transferPkgs : ($inside > 0 ? round($transferQty / $inside, 4) : 0);
            $remainingPkgs = max(0, round($availablePkgs - $usedPkgs, 4));
            $transferPkgs = $usedPkgs;
        }

        return [
            'transfer_qty' => $transferQty,
            'transfer_packages' => $transferPkgs,
            'remaining_qty' => $remainingQty,
            'remaining_packages' => $remainingPkgs,
        ];
    }

    private function normalizeTierInput($tiers): array
    {
        if (!is_array($tiers)) {
            return [];
        }
        $clean = [];
        foreach ($tiers as $t) {
            if (!is_array($t)) {
                continue;
            }
            $min = (float) ($t['min_qty'] ?? 0);
            if ($min <= 0) {
                continue;
            }
            $unitPrice = ($t['unit_price'] ?? '') !== '' ? max(0, (float) $t['unit_price']) : null;
            $discountAmount = ($t['discount_amount'] ?? '') !== '' ? max(0, (float) $t['discount_amount']) : null;
            if (($unitPrice === null || $unitPrice < 0) && ($discountAmount === null || $discountAmount <= 0)) {
                continue;
            }
            $clean[] = [
                'min_qty' => $min,
                'max_qty' => ($t['max_qty'] ?? '') !== '' ? max(0, (float) $t['max_qty']) : null,
                'unit_price' => $unitPrice ?? 0.0,
                'discount_amount' => $discountAmount,
                'label' => trim((string) ($t['label'] ?? '')) ?: null,
            ];
        }
        return $clean;
    }

    private function attachTiersToStoreProduct(int $storeProductId, array $tiers): void
    {
        $tid = \TenantContext::tenantId();
        $payload = json_encode(['quantity_discounts' => $tiers]);
        // Append marker into notes without wiping user notes.
        $stmt = $this->db->prepare('SELECT notes FROM store_products WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$storeProductId, $tid]);
        $notes = (string) ($stmt->fetchColumn() ?: '');
        $notes = preg_replace('/\n?\[QDISC\].*$/s', '', $notes);
        $notes = trim($notes);
        $notes = ($notes !== '' ? $notes . "\n" : '') . '[QDISC]' . $payload;
        $this->db->prepare('UPDATE store_products SET notes = ? WHERE id = ? AND tenant_id = ?')
            ->execute([$notes, $storeProductId, $tid]);
    }

    public function summary(): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS purchase_count,
                SUM(CASE WHEN status = 'recorded' THEN 1 ELSE 0 END) AS recorded_count,
                SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) AS partial_count,
                SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) AS transferred_count
               FROM purchases WHERE tenant_id = ?"
        );
        $stmt->execute([$tid]);
        $header = $stmt->fetch() ?: [];

        $items = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_items,
                SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) AS transferred_items,
                COALESCE(SUM(
                    CASE WHEN status = 'pending' THEN
                        CASE
                          WHEN package_buying_price IS NOT NULL AND package_quantity IS NOT NULL
                            THEN package_buying_price * package_quantity
                          ELSE buying_price * quantity
                        END
                    ELSE 0 END
                ), 0) AS pending_cost
               FROM purchase_items WHERE tenant_id = ?"
        );
        $items->execute([$tid]);
        $itemRow = $items->fetch() ?: [];

        return [
            'purchase_count' => (int) ($header['purchase_count'] ?? 0),
            'recorded_count' => (int) ($header['recorded_count'] ?? 0),
            'partial_count' => (int) ($header['partial_count'] ?? 0),
            'transferred_count' => (int) ($header['transferred_count'] ?? 0),
            'pending_items' => (int) ($itemRow['pending_items'] ?? 0),
            'transferred_items' => (int) ($itemRow['transferred_items'] ?? 0),
            'pending_cost' => round((float) ($itemRow['pending_cost'] ?? 0), 2),
        ];
    }

    private function mergeTransferOverrides(array $row, array $over, ?float $transferQty = null, ?float $transferPackages = null): array
    {
        $inside = max(0.01, (float) ($over['units_per_package'] ?? $row['units_per_package'] ?? 1));
        $pkgQty = $transferPackages !== null
            ? max(0, $transferPackages)
            : (($over['package_quantity'] ?? '') !== ''
                ? max(0, (float) $over['package_quantity'])
                : (($row['package_quantity'] ?? '') !== '' ? (float) $row['package_quantity'] : null));
        $qty = $transferQty !== null
            ? max(0, $transferQty)
            : (($over['quantity'] ?? '') !== ''
                ? max(0, (float) $over['quantity'])
                : (float) ($row['quantity'] ?? 0));
        if ($qty <= 0 && $pkgQty !== null && $pkgQty > 0) {
            $qty = round($pkgQty * $inside, 2);
        }

        $pkgBuy = ($over['package_buying_price'] ?? '') !== ''
            ? max(0, (float) $over['package_buying_price'])
            : (($row['package_buying_price'] ?? '') !== '' ? (float) $row['package_buying_price'] : null);
        $unitBuy = ($over['buying_price'] ?? '') !== ''
            ? max(0, (float) $over['buying_price'])
            : (float) ($row['buying_price'] ?? 0);
        if ($unitBuy <= 0 && $pkgBuy !== null && $pkgBuy > 0) {
            $unitBuy = round($pkgBuy / $inside, 2);
        }

        $packagePrice = ($over['package_price'] ?? '') !== ''
            ? max(0, (float) $over['package_price'])
            : (($row['package_price'] ?? '') !== '' ? (float) $row['package_price'] : null);
        $retailPack = ($over['retail_pack_price'] ?? '') !== ''
            ? max(0, (float) $over['retail_pack_price'])
            : (($row['retail_pack_price'] ?? '') !== '' ? (float) $row['retail_pack_price'] : null);
        $retail = ($over['retail_price'] ?? '') !== ''
            ? max(0, (float) $over['retail_price'])
            : (($row['retail_price'] ?? '') !== '' ? (float) $row['retail_price'] : 0.0);
        $wholesale = ($over['wholesale_price'] ?? '') !== ''
            ? max(0, (float) $over['wholesale_price'])
            : (($row['wholesale_price'] ?? '') !== '' ? (float) $row['wholesale_price'] : 0.0);

        // If only package wholesale/retail given, derive per-item wholesale when blank.
        if ($wholesale <= 0 && $packagePrice !== null && $packagePrice > 0) {
            $wholesale = round($packagePrice / $inside, 2);
        }

        $name = trim((string) ($over['name'] ?? $row['name'] ?? ''));
        $variant = trim((string) ($over['variant_label'] ?? $row['variant_label'] ?? ''));
        if ($name === '') {
            $name = 'Purchase item';
        }
        if ($variant !== '' && stripos($name, $variant) === false) {
            $name .= ' ' . $variant;
        }

        return [
            'name' => $name,
            'category_id' => (int) ($over['category_id'] ?? $row['category_id'] ?? 0),
            'brand_id' => (int) ($over['brand_id'] ?? $row['brand_id'] ?? 0),
            'supplier_id' => (int) ($row['supplier_id'] ?? 0),
            'barcode' => trim((string) ($over['barcode'] ?? $row['barcode'] ?? '')),
            'unit' => trim((string) ($over['unit'] ?? $row['unit'] ?? 'piece')) ?: 'piece',
            'package_unit' => trim((string) ($over['package_unit'] ?? $row['package_unit'] ?? '')) ?: null,
            'package_quantity' => $pkgQty,
            'units_per_package' => $inside,
            'package_price' => $packagePrice,
            'retail_pack_price' => $retailPack,
            'colors' => trim((string) ($over['colors'] ?? $row['colors'] ?? '')),
            'quantity' => $qty,
            'faulty_quantity' => max(0, (float) ($over['faulty_quantity'] ?? $row['faulty_quantity'] ?? 0)),
            'buying_price' => $unitBuy,
            'package_buying_price' => $pkgBuy,
            'retail_price' => $retail,
            'wholesale_price' => $wholesale,
            'image_path' => trim((string) ($row['image_path'] ?? '')),
            'notes' => trim((string) ($over['notes'] ?? $row['notes'] ?? $row['shop_name'] ?? '')),
        ];
    }

    private function findLatestStoreProductId(string $name, ?string $barcode): ?int
    {
        $tid = \TenantContext::tenantId();
        if ($barcode) {
            $stmt = $this->db->prepare(
                "SELECT id FROM store_products
                  WHERE tenant_id = ? AND status = 'stored' AND barcode = ?
               ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$tid, $barcode]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }
        $stmt = $this->db->prepare(
            "SELECT id FROM store_products
              WHERE tenant_id = ? AND status = 'stored' AND name = ?
           ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$tid, $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function refreshPurchaseStatus(int $purchaseId): void
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) AS transferred_count
               FROM purchase_items WHERE purchase_id = ? AND tenant_id = ?"
        );
        $stmt->execute([$purchaseId, $tid]);
        $row = $stmt->fetch() ?: ['pending_count' => 0, 'transferred_count' => 0];
        $pending = (int) ($row['pending_count'] ?? 0);
        $transferred = (int) ($row['transferred_count'] ?? 0);
        if ($pending <= 0 && $transferred > 0) {
            $status = 'transferred';
            $this->db->prepare('UPDATE purchases SET status = ?, transferred_at = NOW() WHERE id = ? AND tenant_id = ?')
                ->execute([$status, $purchaseId, $tid]);
        } elseif ($pending > 0 && $transferred > 0) {
            $this->db->prepare('UPDATE purchases SET status = ? WHERE id = ? AND tenant_id = ?')
                ->execute(['partial', $purchaseId, $tid]);
        } else {
            $this->db->prepare('UPDATE purchases SET status = ? WHERE id = ? AND tenant_id = ?')
                ->execute(['recorded', $purchaseId, $tid]);
        }
    }

    private function normalizeItem(array $item): ?array
    {
        $name = trim((string) ($item['name'] ?? ''));
        $variant = trim((string) ($item['variant_label'] ?? ''));
        $inside = ($item['units_per_package'] ?? '') !== '' ? max(0.01, (float) $item['units_per_package']) : 1.0;
        $pkgQty = ($item['package_quantity'] ?? '') !== '' ? max(0, (float) $item['package_quantity']) : null;
        $qty = (float) ($item['quantity'] ?? 0);
        if ($qty <= 0 && $pkgQty !== null && $pkgQty > 0) {
            $qty = round($pkgQty * $inside, 2);
        }
        $pkgBuy = ($item['package_buying_price'] ?? '') !== '' ? max(0, (float) $item['package_buying_price']) : null;
        // buying_price on the form is typically "per package"; convert to unit cost.
        $formBuy = ($item['buying_price'] ?? '') !== '' ? max(0, (float) $item['buying_price']) : 0.0;
        $unitBuy = (float) ($item['unit_buying_price'] ?? 0);
        if ($unitBuy <= 0 && $pkgBuy !== null && $pkgBuy > 0) {
            $unitBuy = round($pkgBuy / $inside, 2);
        } elseif ($unitBuy <= 0 && $formBuy > 0 && $pkgQty !== null) {
            // Form sent package buying as buying_price.
            $pkgBuy = $pkgBuy ?? $formBuy;
            $unitBuy = round($formBuy / $inside, 2);
        } elseif ($unitBuy <= 0) {
            $unitBuy = $formBuy;
        }

        $packagePrice = ($item['package_price'] ?? $item['wholesale_pack_price'] ?? '') !== ''
            ? max(0, (float) ($item['package_price'] ?? $item['wholesale_pack_price']))
            : null;
        $retailPack = ($item['retail_pack_price'] ?? '') !== '' ? max(0, (float) $item['retail_pack_price']) : null;
        $retail = ($item['retail_price'] ?? $item['selling_price'] ?? '') !== ''
            ? max(0, (float) ($item['retail_price'] ?? $item['selling_price']))
            : null;
        $wholesale = ($item['wholesale_price'] ?? '') !== '' ? max(0, (float) $item['wholesale_price']) : null;

        $hasContent = $name !== '' || $variant !== '' || $qty > 0 || ($pkgQty !== null && $pkgQty > 0)
            || $unitBuy > 0 || ($pkgBuy !== null && $pkgBuy > 0)
            || ($packagePrice !== null && $packagePrice > 0) || ($retailPack !== null && $retailPack > 0)
            || ($retail !== null && $retail > 0) || ($wholesale !== null && $wholesale > 0)
            || trim((string) ($item['barcode'] ?? '')) !== '';
        if (!$hasContent) {
            return null;
        }

        if ($name === '') {
            $p = $retailPack ?: $packagePrice ?: $pkgBuy ?: $retail ?: $unitBuy ?: 0;
            $name = $p > 0 ? ('Product KES ' . number_format((float) $p, 0)) : ('Item ' . date('j M H:i'));
        }
        if ($variant !== '' && stripos($name, $variant) === false) {
            // Keep base name separate; variant stored in its own column and shown in UI.
        }

        $units = ProductModel::UNITS;
        $unit = in_array($item['unit'] ?? '', $units, true) ? $item['unit'] : 'piece';
        $packageUnit = in_array($item['package_unit'] ?? '', $units, true) ? $item['package_unit'] : null;
        if ($packageUnit === 'piece') {
            $packageUnit = null;
        }

        return [
            'name' => $name,
            'category_id' => (int) ($item['category_id'] ?? 0) ?: null,
            'brand_id' => (int) ($item['brand_id'] ?? 0) ?: null,
            'barcode' => $this->nullIfBlank($item['barcode'] ?? null),
            'unit' => $unit,
            'package_unit' => $packageUnit,
            'package_quantity' => $pkgQty,
            'units_per_package' => $inside,
            'variant_label' => $this->nullIfBlank($variant),
            'colors' => $this->nullIfBlank(is_array($item['colors'] ?? null) ? implode(', ', $item['colors']) : ($item['colors'] ?? null)),
            'quantity' => $qty,
            'faulty_quantity' => max(0, (float) ($item['faulty_quantity'] ?? 0)),
            'buying_price' => $unitBuy,
            'package_buying_price' => $pkgBuy,
            'wholesale_price' => $wholesale,
            'package_price' => $packagePrice,
            'retail_price' => $retail,
            'retail_pack_price' => $retailPack,
            'image_path' => $this->nullIfBlank($item['image_path'] ?? null),
            'notes' => $this->nullIfBlank($item['notes'] ?? $item['remark'] ?? null),
        ];
    }

    private function ensureSchema(): void
    {
        $this->ensureTable('purchases', "
            CREATE TABLE IF NOT EXISTS purchases (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                supplier_id INT NULL,
                shop_name VARCHAR(160) NULL,
                receipt_number VARCHAR(80) NULL,
                receipt_image_path VARCHAR(255) NULL,
                purchase_date DATE NULL,
                notes VARCHAR(255) NULL,
                staff_id INT NULL,
                transfer_destination ENUM('store','shop') NOT NULL DEFAULT 'shop',
                status ENUM('recorded','partial','transferred') NOT NULL DEFAULT 'recorded',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                transferred_at DATETIME NULL,
                KEY idx_purchases_tenant (tenant_id, created_at),
                KEY idx_purchases_status (tenant_id, status),
                KEY idx_purchases_supplier (tenant_id, supplier_id),
                KEY idx_purchases_receipt (tenant_id, receipt_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        try{$this->db->query('SELECT transfer_destination FROM purchases LIMIT 1');}
        catch(\PDOException $e){try{$this->db->exec("ALTER TABLE purchases ADD COLUMN transfer_destination ENUM('store','shop') NOT NULL DEFAULT 'shop' AFTER staff_id");}catch(\PDOException $ignored){}}
        $this->ensureTable('purchase_items', "
            CREATE TABLE IF NOT EXISTS purchase_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                purchase_id INT NOT NULL,
                name VARCHAR(160) NULL,
                category_id INT NULL,
                brand_id INT NULL,
                barcode VARCHAR(64) NULL,
                unit VARCHAR(20) NULL DEFAULT 'piece',
                package_unit VARCHAR(20) NULL,
                package_quantity DECIMAL(12,2) NULL,
                units_per_package DECIMAL(12,2) NULL,
                variant_label VARCHAR(40) NULL,
                colors VARCHAR(255) NULL,
                quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                faulty_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                buying_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                package_buying_price DECIMAL(12,2) NULL,
                wholesale_price DECIMAL(12,2) NULL,
                package_price DECIMAL(12,2) NULL,
                retail_price DECIMAL(12,2) NULL,
                retail_pack_price DECIMAL(12,2) NULL,
                image_path VARCHAR(255) NULL,
                notes VARCHAR(255) NULL,
                status ENUM('pending','transferred') NOT NULL DEFAULT 'pending',
                store_product_id INT NULL,
                product_id INT NULL,
                transferred_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_purchase_items_purchase (tenant_id, purchase_id),
                KEY idx_purchase_items_status (tenant_id, status),
                KEY idx_purchase_items_name (tenant_id, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensureTable(string $table, string $sql): void
    {
        try {
            $this->db->query("SELECT 1 FROM `{$table}` LIMIT 1");
        } catch (\PDOException $e) {
            try {
                $this->db->exec($sql);
            } catch (\PDOException $ignored) {
            }
        }
    }

    private function nullIfBlank($value): ?string
    {
        $v = trim((string) ($value ?? ''));
        return $v !== '' ? $v : null;
    }

    private function dateOrNull($value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return null;
        }
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function supplierBelongsToTenant(int $id): bool
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM suppliers WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tid]);
        return (bool) $stmt->fetchColumn();
    }
}
