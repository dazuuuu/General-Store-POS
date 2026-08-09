<?php
require_once __DIR__ . '/../models/TestimonialModel.php';

class TestimonialController {
    private $testimonialModel;
    
    public function __construct($pdo) {
        $this->testimonialModel = new TestimonialModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Submit feedback (public)
     */
    public function submit($data) {
        // Validate required fields
        if (empty($data['customer_name']) || empty($data['testimonial_text'])) {
            return ['success' => false, 'message' => 'Please provide your name and feedback'];
        }
        
        // Validate rating
        $rating = (int)($data['rating'] ?? 5);
        if ($rating < 1 || $rating > 5) {
            $rating = 5;
        }
        
        $testimonialData = [
            ':customer_name' => htmlspecialchars(strip_tags(trim($data['customer_name']))),
            ':customer_email' => !empty($data['customer_email']) ? filter_var(trim($data['customer_email']), FILTER_SANITIZE_EMAIL) : null,
            ':customer_phone' => !empty($data['customer_phone']) ? htmlspecialchars(strip_tags(trim($data['customer_phone']))) : null,
            ':customer_initial' => strtoupper(substr(trim($data['customer_name']), 0, 1)),
            ':rating' => $rating,
            ':testimonial_text' => htmlspecialchars(strip_tags(trim($data['testimonial_text']))),
            ':service_tag' => !empty($data['service_tag']) ? htmlspecialchars(strip_tags(trim($data['service_tag']))) : null,
            ':role' => !empty($data['role']) ? htmlspecialchars(strip_tags(trim($data['role']))) : null
        ];
        
        $testimonialId = $this->testimonialModel->create($testimonialData);
        
        if ($testimonialId) {
            return [
                'success' => true, 
                'message' => 'Thank you for your feedback! It will be reviewed and published soon.',
                'testimonial_id' => $testimonialId
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to submit feedback. Please try again.'];
    }
    
    /**
     * Get all testimonials (admin)
     */
    public function getAll($status = null, $limit = null, $offset = 0) {
        return $this->testimonialModel->getAll($status, $limit, $offset);
    }
    
    /**
     * Get approved testimonials for public display
     */
    public function getApproved($limit = null, $featured = false) {
        return $this->testimonialModel->getAll('approved', $limit, 0, $featured);
    }
    
    /**
     * Get single testimonial (admin)
     */
    public function getById($id) {
        return $this->testimonialModel->getById($id);
    }
    
    /**
     * Update testimonial (admin)
     */
    public function update($id, $data) {
        $result = $this->testimonialModel->update($id, $data);
        
        if ($result) {
            return ['success' => true, 'message' => 'Testimonial updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update testimonial'];
    }
    
    /**
     * Delete testimonial (admin)
     */
    public function delete($id) {
        $result = $this->testimonialModel->delete($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Testimonial deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete testimonial'];
    }
    
    /**
     * Approve testimonial (admin)
     */
    public function approve($id) {
        $result = $this->testimonialModel->approve($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Testimonial approved and published'];
        }
        
        return ['success' => false, 'message' => 'Failed to approve testimonial'];
    }
    
    /**
     * Reject testimonial (admin)
     */
    public function reject($id) {
        $result = $this->testimonialModel->reject($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Testimonial rejected'];
        }
        
        return ['success' => false, 'message' => 'Failed to reject testimonial'];
    }
    
    /**
     * Toggle featured status (admin)
     */
    public function toggleFeatured($id) {
        $result = $this->testimonialModel->toggleFeatured($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Featured status updated'];
        }
        
        return ['success' => false, 'message' => 'Failed to update featured status'];
    }
    
    /**
     * Update sort order (admin)
     */
    public function updateSortOrder($id, $sort_order) {
        $result = $this->testimonialModel->updateSortOrder($id, $sort_order);
        
        if ($result) {
            return ['success' => true];
        }
        
        return ['success' => false];
    }
    
    /**
     * Get counts by status (admin)
     */
    public function getCounts() {
        return $this->testimonialModel->getCounts();
    }
    
    /**
     * Get statistics for admin dashboard
     */
    public function getStats() {
        return [
            'average_rating' => $this->testimonialModel->getAverageRating(),
            'total_testimonials' => $this->testimonialModel->getApprovedCount()
        ];
    }
}
?>