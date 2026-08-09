<?php
class TestimonialModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    /**
     * Create new testimonial from customer feedback
     */
    public function create($data) {
        $sql = "INSERT INTO testimonials (customer_name, customer_email, customer_phone, customer_initial, 
                rating, testimonial_text, service_tag, role, status) 
                VALUES (:customer_name, :customer_email, :customer_phone, :customer_initial, 
                :rating, :testimonial_text, :service_tag, :role, 'pending')";
        $stmt = $this->db->prepare($sql);
        
        $result = $stmt->execute([
            ':customer_name' => $data['customer_name'],
            ':customer_email' => $data['customer_email'] ?? null,
            ':customer_phone' => $data['customer_phone'] ?? null,
            ':customer_initial' => $data['customer_initial'] ?? strtoupper(substr($data['customer_name'], 0, 1)),
            ':rating' => $data['rating'] ?? 5,
            ':testimonial_text' => $data['testimonial_text'],
            ':service_tag' => $data['service_tag'] ?? null,
            ':role' => $data['role'] ?? null
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Get all testimonials with filters
     */
    public function getAll($status = 'approved', $limit = null, $offset = 0, $featured = false) {
        $sql = "SELECT * FROM testimonials WHERE 1=1";
        $params = [];
        
        if ($status && $status !== 'all') {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        if ($featured) {
            $sql .= " AND is_featured = 1";
        }
        
        $sql .= " ORDER BY is_featured DESC, sort_order ASC, created_at DESC";
        
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
    
    /**
     * Get testimonial by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM testimonials WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update testimonial
     */
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['customer_name', 'customer_email', 'customer_phone', 'customer_initial', 
                   'rating', 'testimonial_text', 'service_tag', 'role', 'status', 
                   'is_featured', 'sort_order'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (isset($data['status']) && $data['status'] === 'approved' && empty($data['approved_at'])) {
            $fields[] = "approved_at = NOW()";
        }
        
        if (empty($fields)) return false;
        
        $sql = "UPDATE testimonials SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete testimonial
     */
    public function delete($id) {
        $sql = "DELETE FROM testimonials WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Approve testimonial
     */
    public function approve($id) {
        $sql = "UPDATE testimonials SET status = 'approved', approved_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Reject testimonial
     */
    public function reject($id) {
        $sql = "UPDATE testimonials SET status = 'rejected' WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Toggle featured status
     */
    public function toggleFeatured($id) {
        $testimonial = $this->getById($id);
        if (!$testimonial) return false;
        
        $newFeatured = $testimonial['is_featured'] ? 0 : 1;
        $sql = "UPDATE testimonials SET is_featured = :is_featured WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':is_featured' => $newFeatured]);
    }
    
    /**
     * Update sort order
     */
    public function updateSortOrder($id, $sort_order) {
        $sql = "UPDATE testimonials SET sort_order = :sort_order WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':sort_order' => $sort_order]);
    }
    
    /**
     * Get counts by status
     */
    public function getCounts() {
        $sql = "SELECT status, COUNT(*) as count FROM testimonials GROUP BY status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        foreach ($results as $row) {
            $counts[$row['status']] = $row['count'];
            $counts['total'] += $row['count'];
        }
        return $counts;
    }
    
    /**
     * Get average rating
     */
    public function getAverageRating() {
        $sql = "SELECT AVG(rating) as avg_rating FROM testimonials WHERE status = 'approved'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return round($result['avg_rating'] ?? 0, 1);
    }
    
    /**
     * Get total approved count
     */
    public function getApprovedCount() {
        $sql = "SELECT COUNT(*) as total FROM testimonials WHERE status = 'approved'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }
}
?>