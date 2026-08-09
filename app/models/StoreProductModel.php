<?php
class StoreProductModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    private function generateSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
    
    public function create($data) {
        $slug = $this->generateSlug($data['name']);
        $originalSlug = $slug;
        $counter = 1;
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter++;
        }
        
        $sql = "INSERT INTO store_products (name, slug, description, price, compare_price, sku, 
                stock_quantity, category_id, featured_image, gallery_images, status, is_featured, 
                meta_title, meta_description, sort_order, created_by) 
                VALUES (:name, :slug, :description, :price, :compare_price, :sku, :stock_quantity, 
                :category_id, :featured_image, :gallery_images, :status, :is_featured, 
                :meta_title, :meta_description, :sort_order, :created_by)";
        
        $stmt = $this->db->prepare($sql);
        $data['slug'] = $slug;
        return $stmt->execute($data) ? $this->db->lastInsertId() : false;
    }
    
    private function slugExists($slug) {
        $sql = "SELECT id FROM store_products WHERE slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch();
    }
    
    public function getAll($status = 'active', $limit = null, $offset = 0, $category_id = null, $sort = 'newest') {
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug
                FROM store_products p
                LEFT JOIN store_categories c ON p.category_id = c.id
                WHERE 1=1";
        
        $params = [];
        
        if ($status) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $status;
        }
        
        if ($category_id) {
            $sql .= " AND p.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }
        
        // Sorting
        switch ($sort) {
            case 'price_low':
                $sql .= " ORDER BY p.price ASC";
                break;
            case 'price_high':
                $sql .= " ORDER BY p.price DESC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY p.name ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY p.name DESC";
                break;
            case 'oldest':
                $sql .= " ORDER BY p.created_at ASC";
                break;
            default: // newest
                $sql .= " ORDER BY p.created_at DESC";
                break;
        }
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => &$val) {
            if ($key == ':limit' || $key == ':offset') {
                $stmt->bindParam($key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindParam($key, $val);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug
                FROM store_products p
                LEFT JOIN store_categories c ON p.category_id = c.id
                WHERE p.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getBySlug($slug) {
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug
                FROM store_products p
                LEFT JOIN store_categories c ON p.category_id = c.id
                WHERE p.slug = :slug AND p.status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'description', 'price', 'compare_price', 'sku', 'stock_quantity', 
                   'category_id', 'featured_image', 'gallery_images', 'status', 'is_featured', 
                   'meta_title', 'meta_description', 'sort_order'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (isset($data['name'])) {
            $fields[] = "slug = :slug";
            $params[':slug'] = $this->generateSlug($data['name']);
        }
        
        if (empty($fields)) return false;
        
        $sql = "UPDATE store_products SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        $product = $this->getById($id);
        if ($product && $product['featured_image']) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $product['featured_image'];
            if (file_exists($file_path)) unlink($file_path);
        }
        
        $sql = "DELETE FROM store_products WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function updateStock($id, $quantity) {
        $sql = "UPDATE store_products SET stock_quantity = stock_quantity - :quantity 
                WHERE id = :id AND stock_quantity >= :quantity";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':quantity' => $quantity]);
    }
    
    public function getFeatured($limit = 8) {
        $sql = "SELECT p.*, c.name as category_name
                FROM store_products p
                LEFT JOIN store_categories c ON p.category_id = c.id
                WHERE p.status = 'active' AND p.is_featured = 1
                ORDER BY p.sort_order ASC, p.created_at DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function incrementViews($id) {
        $sql = "UPDATE store_products SET view_count = view_count + 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function search($keyword) {
        $sql = "SELECT p.*, c.name as category_name
                FROM store_products p
                LEFT JOIN store_categories c ON p.category_id = c.id
                WHERE p.status = 'active' 
                AND (p.name LIKE :keyword OR p.description LIKE :keyword OR p.sku LIKE :keyword)
                ORDER BY p.name ASC
                LIMIT 30";
        $stmt = $this->db->prepare($sql);
        $keyword = "%$keyword%";
        $stmt->bindParam(':keyword', $keyword);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRelatedProducts($product_id, $category_id, $limit = 4) {
        $sql = "SELECT p.*, c.name as category_name
                FROM store_products p
                LEFT JOIN store_categories c ON p.category_id = c.id
                WHERE p.status = 'active' AND p.id != :product_id AND p.category_id = :category_id
                ORDER BY p.created_at DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>