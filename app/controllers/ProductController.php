<?php
require_once __DIR__ . '/../models/ProductModel.php';

class ProductController {
    private $productModel;
    
    public function __construct($pdo) {
        $this->productModel = new ProductModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function create($data, $featured_image = null) {
        if (empty($data['name']) || empty($data['price'])) {
            return ['success' => false, 'message' => 'Name and price are required'];
        }
        
        $featured_image_path = null;
        if ($featured_image && $featured_image['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Ismano/public/uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($featured_image['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($featured_image['tmp_name'], $target_file)) {
                $featured_image_path = '/Ismano/public/uploads/products/' . $filename;
            }
        }
        
        $productData = [
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':price' => $data['price'],
            ':compare_price' => !empty($data['compare_price']) ? $data['compare_price'] : null,
            ':sku' => $data['sku'] ?? null,
            ':stock_quantity' => $data['stock_quantity'] ?? 0,
            ':category_id' => !empty($data['category_id']) ? $data['category_id'] : null,
            ':featured_image' => $featured_image_path,
            ':gallery_images' => null,
            ':status' => $data['status'] ?? 'draft',
            ':is_featured' => isset($data['is_featured']) ? 1 : 0,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':meta_keywords' => $data['meta_keywords'] ?? null,
            ':created_by' => $_SESSION['user_id'] ?? null
        ];
        
        $productId = $this->productModel->create($productData);
        
        if ($productId) {
            return ['success' => true, 'message' => 'Product created successfully', 'product_id' => $productId];
        }
        
        return ['success' => false, 'message' => 'Failed to create product'];
    }
    
    public function getAll($status = 'active', $limit = 12, $offset = 0, $category_id = null) {
        return $this->productModel->getAll($status, $limit, $offset, $category_id);
    }
    
    public function getProductDetails($identifier) {
        $product = is_numeric($identifier) ? 
                   $this->productModel->getById($identifier) : 
                   $this->productModel->getBySlug($identifier);
        
        if ($product) {
            $this->productModel->incrementViews($product['id']);
        }
        
        return $product;
    }
    
    public function update($id, $data, $featured_image = null) {
        $updateData = [];
        
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['price'])) $updateData['price'] = $data['price'];
        if (isset($data['compare_price'])) $updateData['compare_price'] = $data['compare_price'];
        if (isset($data['sku'])) $updateData['sku'] = $data['sku'];
        if (isset($data['stock_quantity'])) $updateData['stock_quantity'] = $data['stock_quantity'];
        if (isset($data['category_id'])) $updateData['category_id'] = $data['category_id'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['is_featured'])) $updateData['is_featured'] = $data['is_featured'] ? 1 : 0;
        if (isset($data['meta_title'])) $updateData['meta_title'] = $data['meta_title'];
        if (isset($data['meta_description'])) $updateData['meta_description'] = $data['meta_description'];
        if (isset($data['meta_keywords'])) $updateData['meta_keywords'] = $data['meta_keywords'];
        
        if ($featured_image && $featured_image['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Ismano/public/uploads/products/';
            $ext = pathinfo($featured_image['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($featured_image['tmp_name'], $target_file)) {
                $updateData['featured_image'] = '/Ismano/public/uploads/products/' . $filename;
            }
        }
        
        $result = $this->productModel->update($id, $updateData);
        
        if ($result) {
            return ['success' => true, 'message' => 'Product updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update product'];
    }
    
    public function delete($id) {
        $result = $this->productModel->delete($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Product deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete product'];
    }
    
    public function getFeatured($limit = 8) {
        return $this->productModel->getFeatured($limit);
    }
    
    public function search($keyword) {
        return $this->productModel->search($keyword);
    }
    
    // Category Management
    public function createCategory($name, $description = null, $parent_id = null) {
        if (empty($name)) {
            return ['success' => false, 'message' => 'Category name is required'];
        }
        
        $slug = $this->generateSlug($name);
        
        $sql = "INSERT INTO product_categories (name, slug, description, parent_id) 
                VALUES (:name, :slug, :description, :parent_id)";
        $stmt = $this->productModel->db->prepare($sql);
        $result = $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description,
            ':parent_id' => $parent_id
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Category created successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to create category'];
    }
    
    public function getAllCategories() {
        $sql = "SELECT * FROM product_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
        $stmt = $this->productModel->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateCategory($id, $data) {
        $sql = "UPDATE product_categories SET name = :name, description = :description, 
                parent_id = :parent_id, sort_order = :sort_order WHERE id = :id";
        $stmt = $this->productModel->db->prepare($sql);
        $result = $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':parent_id' => $data['parent_id'] ?? null,
            ':sort_order' => $data['sort_order'] ?? 0
        ]);
        
        return $result;
    }
    
    public function deleteCategory($id) {
        // Check if category has products
        $sql = "SELECT COUNT(*) FROM products WHERE category_id = :id";
        $stmt = $this->productModel->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            return ['success' => false, 'message' => "Cannot delete category with {$count} products. Reassign products first."];
        }
        
        $sql = "DELETE FROM product_categories WHERE id = :id";
        $stmt = $this->productModel->db->prepare($sql);
        $result = $stmt->execute([':id' => $id]);
        
        return ['success' => $result, 'message' => $result ? 'Category deleted' : 'Delete failed'];
    }
    
    private function generateSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}
?>