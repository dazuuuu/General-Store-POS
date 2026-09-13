<?php
// app/controllers/ServiceController.php

class ServiceController {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // Helper function to generate slug
    private function generateSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
    
    // Create service
    public function create($data, $cover_image = null) {
        // Validate required fields
        if (empty($data['title']) || empty($data['short_description'])) {
            return ['success' => false, 'message' => 'Title and short description are required'];
        }
        
        $slug = $this->generateSlug($data['title']);
        
        // Check if slug exists
        $checkSql = "SELECT id FROM services WHERE slug = :slug";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([':slug' => $slug]);
        if ($checkStmt->fetch()) {
            $slug = $slug . '-' . time();
        }
        
        $sql = "INSERT INTO services (title, slug, short_description, cover_image, status, created_by, created_at) 
                VALUES (:title, :slug, :short_description, :cover_image, :status, :created_by, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $slug,
            ':short_description' => $data['short_description'],
            ':cover_image' => $cover_image,
            ':status' => $data['status'] ?? 'draft',
            ':created_by' => $_SESSION['user_id'] ?? null
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Service created successfully', 'service_id' => $this->db->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Failed to create service'];
    }
    
    // Get service by ID with all details
    public function getServiceDetails($id) {
        // Get basic service info
        $sql = "SELECT s.*, u.username as creator_name 
                FROM services s 
                LEFT JOIN users u ON s.created_by = u.id 
                WHERE s.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service) {
            return null;
        }
        
        // Get sections
        $sql = "SELECT * FROM service_sections WHERE service_id = :service_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $id]);
        $service['sections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get gallery
        $sql = "SELECT * FROM service_gallery WHERE service_id = :service_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $id]);
        $service['gallery'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get benefits
        $sql = "SELECT * FROM service_benefits WHERE service_id = :service_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $id]);
        $service['benefits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get FAQs
        $sql = "SELECT * FROM service_faqs WHERE service_id = :service_id AND is_active = 1 ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $id]);
        $service['faqs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $service;
    }
    
    // Get service by slug
    public function getServiceBySlug($slug) {
        $sql = "SELECT id FROM services WHERE slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            return $this->getServiceDetails($service['id']);
        }
        
        return null;
    }
    
    // Update view count
    public function updateViewCount($id) {
        $sql = "UPDATE services SET view_count = view_count + 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Update service
    public function update($id, $data, $cover_image = null) {
        $fields = [];
        $params = [':id' => $id];
        
        if (isset($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = $data['title'];
            
            // Update slug if title changed
            $slug = $this->generateSlug($data['title']);
            $fields[] = "slug = :slug";
            $params[':slug'] = $slug;
        }
        
        if (isset($data['short_description'])) {
            $fields[] = "short_description = :short_description";
            $params[':short_description'] = $data['short_description'];
        }
        
        if ($cover_image) {
            $fields[] = "cover_image = :cover_image";
            $params[':cover_image'] = $cover_image;
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        
        if (empty($fields)) {
            return ['success' => false, 'message' => 'No data to update'];
        }
        
        $sql = "UPDATE services SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($params)) {
            return ['success' => true, 'message' => 'Service updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update service'];
    }
    
    // Add section to service
    public function addSection($service_id, $data, $media_file = null) {
        $media_url = $data['media_url'] ?? null;
        
        if ($media_file && $media_file['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Ismano/public/uploads/services/sections/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = time() . '_' . basename($media_file['name']);
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($media_file['tmp_name'], $target_file)) {
                $media_url = '/Ismano/public/uploads/services/sections/' . $filename;
            }
        }
        
        $sql = "INSERT INTO service_sections (service_id, section_type, title, content, media_url, media_type, sort_order) 
                VALUES (:service_id, :section_type, :title, :content, :media_url, :media_type, :sort_order)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':service_id' => $service_id,
            ':section_type' => $data['section_type'],
            ':title' => $data['title'] ?? null,
            ':content' => $data['content'] ?? null,
            ':media_url' => $media_url,
            ':media_type' => $data['media_type'] ?? 'image',
            ':sort_order' => $data['sort_order'] ?? 0
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Section added successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to add section'];
    }
    
    // Add gallery image
    public function addGalleryImage($service_id, $image_file, $image_title = null, $image_description = null) {
        if (!$image_file || $image_file['error'] !== 0) {
            return ['success' => false, 'message' => 'Image file is required'];
        }
        
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Ismano/public/uploads/services/gallery/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = time() . '_' . basename($image_file['name']);
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($image_file['tmp_name'], $target_file)) {
            $sql = "INSERT INTO service_gallery (service_id, image_path, image_title, image_description, sort_order) 
                    VALUES (:service_id, :image_path, :image_title, :image_description, :sort_order)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':service_id' => $service_id,
                ':image_path' => '/Ismano/public/uploads/services/gallery/' . $filename,
                ':image_title' => $image_title,
                ':image_description' => $image_description,
                ':sort_order' => 0
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Gallery image added successfully'];
            }
        }
        
        return ['success' => false, 'message' => 'Failed to upload gallery image'];
    }
    
    // Add benefit
    public function addBenefit($service_id, $data) {
        if (empty($data['benefit_title'])) {
            return ['success' => false, 'message' => 'Benefit title is required'];
        }
        
        $sql = "INSERT INTO service_benefits (service_id, benefit_title, benefit_description, icon_class, sort_order) 
                VALUES (:service_id, :benefit_title, :benefit_description, :icon_class, :sort_order)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':service_id' => $service_id,
            ':benefit_title' => $data['benefit_title'],
            ':benefit_description' => $data['benefit_description'] ?? null,
            ':icon_class' => $data['icon_class'] ?? null,
            ':sort_order' => $data['sort_order'] ?? 0
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Benefit added successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to add benefit'];
    }
    
    // Add FAQ
    public function addFaq($service_id, $data) {
        if (empty($data['question']) || empty($data['answer'])) {
            return ['success' => false, 'message' => 'Question and answer are required'];
        }
        
        $sql = "INSERT INTO service_faqs (service_id, question, answer, sort_order, is_active) 
                VALUES (:service_id, :question, :answer, :sort_order, :is_active)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':service_id' => $service_id,
            ':question' => $data['question'],
            ':answer' => $data['answer'],
            ':sort_order' => $data['sort_order'] ?? 0,
            ':is_active' => $data['is_active'] ?? 1
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'FAQ added successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to add FAQ'];
    }
    
    // Delete section
    public function deleteSection($id) {
        $sql = "DELETE FROM service_sections WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute([':id' => $id])) {
            return ['success' => true, 'message' => 'Section deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete section'];
    }
    
    // Delete gallery image
    public function deleteGalleryImage($id) {
        // Get image path first
        $sql = "SELECT image_path FROM service_gallery WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            // Delete file from server
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $sql = "DELETE FROM service_gallery WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute([':id' => $id])) {
            return ['success' => true, 'message' => 'Gallery image deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete gallery image'];
    }
    
    // Delete benefit
    public function deleteBenefit($id) {
        $sql = "DELETE FROM service_benefits WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute([':id' => $id])) {
            return ['success' => true, 'message' => 'Benefit deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete benefit'];
    }
    
    // Delete FAQ
    public function deleteFaq($id) {
        $sql = "DELETE FROM service_faqs WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute([':id' => $id])) {
            return ['success' => true, 'message' => 'FAQ deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete FAQ'];
    }
    
    // Delete service
    public function deleteService($id) {
        // Delete cover image file
        $sql = "SELECT cover_image FROM services WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service && $service['cover_image']) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $service['cover_image'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $sql = "DELETE FROM services WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute([':id' => $id])) {
            return ['success' => true, 'message' => 'Service deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete service'];
    }
}
?>