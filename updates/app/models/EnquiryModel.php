<?php
class EnquiryModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    /**
     * Create new enquiry
     */
    public function create($data) {
        $sql = "INSERT INTO enquiries (name, email, phone, service, message, priority) 
                VALUES (:name, :email, :phone, :service, :message, :priority)";
        $stmt = $this->db->prepare($sql);
        
        $result = $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':service' => $data['service'] ?? null,
            ':message' => $data['message'],
            ':priority' => $data['priority'] ?? 'medium'
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Get all enquiries with filters
     */
    public function getAll($status = null, $limit = null, $offset = 0, $search = null) {
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM enquiry_replies WHERE enquiry_id = e.id) as reply_count
                FROM enquiries e
                WHERE 1=1";
        $params = [];
        
        if ($status && $status !== 'all') {
            $sql .= " AND e.status = :status";
            $params[':status'] = $status;
        }
        
        if ($search) {
            $sql .= " AND (e.name LIKE :search OR e.email LIKE :search OR e.phone LIKE :search OR e.message LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        $sql .= " ORDER BY 
                    CASE e.status 
                        WHEN 'new' THEN 1 
                        WHEN 'read' THEN 2 
                        WHEN 'contacted' THEN 3 
                        WHEN 'closed' THEN 4 
                    END ASC,
                    e.created_at DESC";
        
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
     * Get enquiry by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM enquiries WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get enquiry with replies
     */
    public function getWithReplies($id) {
        $enquiry = $this->getById($id);
        if (!$enquiry) return null;
        
        $sql = "SELECT r.*, u.username as admin_name 
                FROM enquiry_replies r
                LEFT JOIN users u ON r.admin_id = u.id
                WHERE r.enquiry_id = :enquiry_id
                ORDER BY r.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':enquiry_id' => $id]);
        $enquiry['replies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $enquiry;
    }
    
    /**
     * Update enquiry status
     */
    public function updateStatus($id, $status) {
        $data = [':id' => $id, ':status' => $status];
        $fields = ["status = :status"];
        
        if ($status === 'contacted') {
            $fields[] = "contacted_at = NOW()";
        }
        if ($status === 'closed') {
            $fields[] = "closed_at = NOW()";
        }
        
        $sql = "UPDATE enquiries SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * Add reply to enquiry
     */
    public function addReply($enquiry_id, $admin_id, $reply) {
        $sql = "INSERT INTO enquiry_replies (enquiry_id, admin_id, reply) 
                VALUES (:enquiry_id, :admin_id, :reply)";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':enquiry_id' => $enquiry_id,
            ':admin_id' => $admin_id,
            ':reply' => $reply
        ]);
        
        if ($result) {
            // Update enquiry status to 'read' if it's 'new'
            $this->updateStatus($enquiry_id, 'read');
        }
        
        return $result;
    }
    
    /**
     * Add note to enquiry
     */
    public function addNote($id, $note) {
        $sql = "UPDATE enquiries SET notes = CONCAT(IFNULL(notes, ''), :note) WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':note' => "\n[" . date('Y-m-d H:i:s') . "] " . $note
        ]);
    }
    
    /**
     * Update priority
     */
    public function updatePriority($id, $priority) {
        $sql = "UPDATE enquiries SET priority = :priority WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':priority' => $priority]);
    }
    
    /**
     * Delete enquiry
     */
    public function delete($id) {
        $sql = "DELETE FROM enquiries WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Get counts by status
     */
    public function getCounts() {
        $sql = "SELECT status, COUNT(*) as count FROM enquiries GROUP BY status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $counts = ['new' => 0, 'read' => 0, 'contacted' => 0, 'closed' => 0, 'total' => 0];
        foreach ($results as $row) {
            $counts[$row['status']] = $row['count'];
            $counts['total'] += $row['count'];
        }
        return $counts;
    }
    
    /**
     * Mark as read
     */
    public function markAsRead($id) {
        $enquiry = $this->getById($id);
        if ($enquiry && $enquiry['status'] === 'new') {
            return $this->updateStatus($id, 'read');
        }
        return false;
    }
}
?>