<?php
// app/models/BlogModel.php

class BlogModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    // Create blog post
    public function create($data) {
        $sql = "INSERT INTO blogs (title, slug, excerpt, content, featured_image, category_id, author_id, status, meta_title, meta_description, meta_keywords, published_at) 
                VALUES (:title, :slug, :excerpt, :content, :featured_image, :category_id, :author_id, :status, :meta_title, :meta_description, :meta_keywords, :published_at)";
        
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($data)) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    // Get blog by ID
    public function getById($id) {
        $sql = "SELECT b.*, u.username as author_name, c.name as category_name, c.slug as category_slug, c.color as category_color
                FROM blogs b
                LEFT JOIN users u ON b.author_id = u.id
                LEFT JOIN blog_categories c ON b.category_id = c.id
                WHERE b.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get blog by slug
    public function getBySlug($slug) {
        $sql = "SELECT b.*, u.username as author_name, c.name as category_name, c.slug as category_slug, c.color as category_color
                FROM blogs b
                LEFT JOIN users u ON b.author_id = u.id
                LEFT JOIN blog_categories c ON b.category_id = c.id
                WHERE b.slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get all blogs
    public function getAll($status = null, $limit = null, $offset = 0, $orderBy = 'created_at DESC') {
        $sql = "SELECT b.*, u.username as author_name, c.name as category_name, c.color as category_color
                FROM blogs b
                LEFT JOIN users u ON b.author_id = u.id
                LEFT JOIN blog_categories c ON b.category_id = c.id";
        
        if ($status) {
            $sql .= " WHERE b.status = :status";
        }
        
        $sql .= " ORDER BY b.$orderBy";
        
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
    
    // Get featured blogs
    public function getFeatured($limit = 3) {
        $sql = "SELECT b.*, u.username as author_name, c.name as category_name, c.color as category_color
                FROM blogs b
                LEFT JOIN users u ON b.author_id = u.id
                LEFT JOIN blog_categories c ON b.category_id = c.id
                WHERE b.status = 'published' AND b.is_featured = 1
                ORDER BY b.published_at DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Update blog
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['title', 'slug', 'excerpt', 'content', 'featured_image', 'category_id', 'status', 'meta_title', 'meta_description', 'meta_keywords', 'is_featured'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (isset($data['published_at']) && $data['published_at']) {
            $fields[] = "published_at = :published_at";
            $params[':published_at'] = $data['published_at'];
        } elseif (isset($data['status']) && $data['status'] === 'published') {
            $fields[] = "published_at = NOW()";
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE blogs SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    // Delete blog
    public function delete($id) {
        $sql = "DELETE FROM blogs WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Update view count
    public function incrementViews($id) {
        $sql = "UPDATE blogs SET view_count = view_count + 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Get sections for blog
    public function getSections($blog_id) {
        $sql = "SELECT * FROM blog_sections WHERE blog_id = :blog_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':blog_id' => $blog_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add section to blog
    public function addSection($data) {
        $sql = "INSERT INTO blog_sections (blog_id, section_type, title, content, media_url, media_type, video_id, sort_order) 
                VALUES (:blog_id, :section_type, :title, :content, :media_url, :media_type, :video_id, :sort_order)";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($data)) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    // Update section
    public function updateSection($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = ['section_type', 'title', 'content', 'media_url', 'media_type', 'video_id', 'sort_order'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE blog_sections SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    // Delete section
    public function deleteSection($id) {
        $sql = "DELETE FROM blog_sections WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Get FAQs for blog
    public function getFaqs($blog_id) {
        $sql = "SELECT * FROM blog_faqs WHERE blog_id = :blog_id AND is_active = 1 ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':blog_id' => $blog_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add FAQ to blog
    public function addFaq($data) {
        $sql = "INSERT INTO blog_faqs (blog_id, question, answer, sort_order) 
                VALUES (:blog_id, :question, :answer, :sort_order)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    // Update FAQ
    public function updateFaq($id, $data) {
        $sql = "UPDATE blog_faqs SET question = :question, answer = :answer, sort_order = :sort_order WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $data[':id'] = $id;
        return $stmt->execute($data);
    }
    
    // Delete FAQ
    public function deleteFaq($id) {
        $sql = "DELETE FROM blog_faqs WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Get tags for blog
    public function getTags($blog_id) {
        $sql = "SELECT t.* FROM blog_tags t
                INNER JOIN blog_tag_relations tr ON t.id = tr.tag_id
                WHERE tr.blog_id = :blog_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':blog_id' => $blog_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add tags to blog
    public function addTags($blog_id, $tag_ids) {
        $sql = "INSERT INTO blog_tag_relations (blog_id, tag_id) VALUES (:blog_id, :tag_id)";
        $stmt = $this->db->prepare($sql);
        
        foreach ($tag_ids as $tag_id) {
            $stmt->execute([':blog_id' => $blog_id, ':tag_id' => $tag_id]);
        }
        return true;
    }
    
    // Remove all tags from blog
    public function removeTags($blog_id) {
        $sql = "DELETE FROM blog_tag_relations WHERE blog_id = :blog_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':blog_id' => $blog_id]);
    }
    
    // Get all categories
    public function getAllCategories() {
        $sql = "SELECT * FROM blog_categories ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Create category (without created_by to avoid errors)
    public function createCategory($name, $slug, $description = null, $color = '#667eea') {
        $sql = "INSERT INTO blog_categories (name, slug, description, color) 
                VALUES (:name, :slug, :description, :color)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description,
            ':color' => $color
        ]);
        return $this->db->lastInsertId();
    }
    
    // Update category
    public function updateCategory($id, $data) {
        $sql = "UPDATE blog_categories SET name = :name, slug = :slug, description = :description, color = :color WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':description' => $data['description'] ?? null,
            ':color' => $data['color'] ?? '#667eea'
        ]);
    }
    
    // Delete category
    public function deleteCategory($id) {
        $sql = "DELETE FROM blog_categories WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Get all tags
    public function getAllTags() {
        $sql = "SELECT * FROM blog_tags ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Create tag
    public function createTag($name, $slug) {
        $sql = "INSERT INTO blog_tags (name, slug) VALUES (:name, :slug)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $name, ':slug' => $slug]);
        return $this->db->lastInsertId();
    }
    
    // Get related posts
    public function getRelatedPosts($blog_id, $category_id, $limit = 3) {
        $sql = "SELECT b.*, u.username as author_name
                FROM blogs b
                LEFT JOIN users u ON b.author_id = u.id
                WHERE b.status = 'published' AND b.id != :blog_id AND b.category_id = :category_id
                ORDER BY b.published_at DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':blog_id', $blog_id);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Search blogs
    public function search($keyword, $limit = 10) {
        $sql = "SELECT b.*, u.username as author_name, c.name as category_name
                FROM blogs b
                LEFT JOIN users u ON b.author_id = u.id
                LEFT JOIN blog_categories c ON b.category_id = c.id
                WHERE b.status = 'published' 
                AND (b.title LIKE :keyword OR b.content LIKE :keyword OR b.excerpt LIKE :keyword)
                ORDER BY b.published_at DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $keyword = "%$keyword%";
        $stmt->bindParam(':keyword', $keyword);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>