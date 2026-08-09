<?php
// app/models/ServiceModel.php

class ServiceModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    // Get all services
    public function getAllServices($status = null, $limit = null, $offset = 0) {
        $sql = "SELECT s.*, u.username as creator_name 
                FROM services s 
                LEFT JOIN users u ON s.created_by = u.id";
        
        if ($status) {
            $sql .= " WHERE s.status = :status";
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->db->prepare($sql);
        
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get service by slug
    public function getServiceBySlug($slug) {
        $sql = "SELECT s.*, u.username as creator_name 
                FROM services s 
                LEFT JOIN users u ON s.created_by = u.id 
                WHERE s.slug = :slug AND s.status = 'published'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get service by ID
    public function getServiceById($id) {
        $sql = "SELECT s.*, u.username as creator_name 
                FROM services s 
                LEFT JOIN users u ON s.created_by = u.id 
                WHERE s.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Create service
    public function createService($data) {
        $sql = "INSERT INTO services (title, slug, short_description, cover_image, status, created_by) 
                VALUES (:title, :slug, :short_description, :cover_image, :status, :created_by)";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($data)) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    // Update service
    public function updateService($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['title', 'slug', 'short_description', 'cover_image', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE services SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    // Delete service
    public function deleteService($id) {
        $sql = "DELETE FROM services WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Update view count
    public function updateViewCount($id) {
        $sql = "UPDATE services SET view_count = view_count + 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Get sections for service
    public function getSectionsByServiceId($service_id) {
        $sql = "SELECT * FROM service_sections WHERE service_id = :service_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $service_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add section
    public function addSection($data) {
        $sql = "INSERT INTO service_sections (service_id, section_type, title, content, media_url, media_type, sort_order) 
                VALUES (:service_id, :section_type, :title, :content, :media_url, :media_type, :sort_order)";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($data)) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    // Get gallery images
    public function getGalleryByServiceId($service_id) {
        $sql = "SELECT * FROM service_gallery WHERE service_id = :service_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $service_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add gallery image
    public function addGalleryImage($data) {
        $sql = "INSERT INTO service_gallery (service_id, image_path, image_title, image_description, sort_order) 
                VALUES (:service_id, :image_path, :image_title, :image_description, :sort_order)";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($data)) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    // Get benefits
    public function getBenefitsByServiceId($service_id) {
        $sql = "SELECT * FROM service_benefits WHERE service_id = :service_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $service_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add benefit
    public function addBenefit($data) {
        $sql = "INSERT INTO service_benefits (service_id, benefit_title, benefit_description, icon_class, sort_order) 
                VALUES (:service_id, :benefit_title, :benefit_description, :icon_class, :sort_order)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    // Get FAQs
    public function getFaqsByServiceId($service_id) {
        $sql = "SELECT * FROM service_faqs WHERE service_id = :service_id AND is_active = 1 ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $service_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add FAQ
    public function addFaq($data) {
        $sql = "INSERT INTO service_faqs (service_id, question, answer, sort_order, is_active) 
                VALUES (:service_id, :question, :answer, :sort_order, :is_active)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
}
?>