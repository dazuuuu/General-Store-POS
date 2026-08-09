<?php
// app/models/ProjectModel.php (COMPLETE WITH ALL METHODS)
require_once __DIR__ . '/../config/db_connect.php';

class ProjectModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    // Category Methods
    public function createCategory($data) {
        $slug = $this->createSlug($data['category_name']);
        $sql = "INSERT INTO project_categories (category_name, category_slug, category_description, created_by) 
                VALUES (:name, :slug, :description, :created_by)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':name' => $data['category_name'],
            ':slug' => $slug,
            ':description' => $data['category_description'] ?? '',
            ':created_by' => $_SESSION['user_id']
        ]);
    }
    
    public function getCategories() {
        $sql = "SELECT c.*, COUNT(p.id) as project_count 
                FROM project_categories c 
                LEFT JOIN projects p ON c.id = p.category_id 
                GROUP BY c.id 
                ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getCategoryById($id) {
        $sql = "SELECT * FROM project_categories WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function updateCategory($id, $data) {
        $sql = "UPDATE project_categories SET category_name = :name, category_description = :description 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['category_name'],
            ':description' => $data['category_description'] ?? ''
        ]);
    }
    
    public function deleteCategory($id) {
        $sql = "DELETE FROM project_categories WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Project Methods
    public function createProject($data) {
        $slug = $this->createSlug($data['small_title'] . '-' . $data['major_title']);
        $sql = "INSERT INTO projects (category_id, small_title, major_title, project_slug, description, cover_image, status, created_by) 
                VALUES (:category_id, :small_title, :major_title, :slug, :description, :cover_image, :status, :created_by)";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':category_id' => $data['category_id'],
            ':small_title' => $data['small_title'],
            ':major_title' => $data['major_title'],
            ':slug' => $slug,
            ':description' => $data['description'],
            ':cover_image' => $data['cover_image'] ?? null,
            ':status' => $data['status'] ?? 'draft',
            ':created_by' => $_SESSION['user_id']
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    public function getProjects($status = null) {
        $sql = "SELECT p.*, c.category_name, u.username as author 
                FROM projects p 
                JOIN project_categories c ON p.category_id = c.id 
                LEFT JOIN users u ON p.created_by = u.id";
        
        if ($status) {
            $sql .= " WHERE p.status = :status";
            $sql .= " ORDER BY p.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':status' => $status]);
        } else {
            $sql .= " ORDER BY p.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }
    
    public function getProjectById($id) {
        $sql = "SELECT p.*, c.category_name, c.category_slug, u.username as author 
                FROM projects p 
                JOIN project_categories c ON p.category_id = c.id 
                LEFT JOIN users u ON p.created_by = u.id 
                WHERE p.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function getProjectBySlug($slug) {
        $sql = "SELECT p.*, c.category_name, c.category_slug, u.username as author 
                FROM projects p 
                JOIN project_categories c ON p.category_id = c.id 
                LEFT JOIN users u ON p.created_by = u.id 
                WHERE p.project_slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch();
    }
    
    public function updateProject($id, $data) {
        $sql = "UPDATE projects SET 
                category_id = :category_id,
                small_title = :small_title,
                major_title = :major_title,
                description = :description,
                cover_image = :cover_image,
                status = :status
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':category_id' => $data['category_id'],
            ':small_title' => $data['small_title'],
            ':major_title' => $data['major_title'],
            ':description' => $data['description'],
            ':cover_image' => $data['cover_image'],
            ':status' => $data['status']
        ]);
    }
    
    // Status update methods
    public function publishProject($id) {
        $sql = "UPDATE projects SET status = 'published' WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function saveAsDraft($id) {
        $sql = "UPDATE projects SET status = 'draft' WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function archiveProject($id) {
        $sql = "UPDATE projects SET status = 'archived' WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function deleteProject($id) {
        // Get project to delete cover image
        $project = $this->getProjectById($id);
        if ($project && $project['cover_image']) {
            $this->deleteFile($project['cover_image']);
        }
        
        $sql = "DELETE FROM projects WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function updateViewCount($id) {
        $sql = "UPDATE projects SET view_count = view_count + 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
// app/models/ProjectModel.php (Fix the addGalleryImage method)

public function addGalleryImage($project_id, $image_path, $title = '', $description = '') {
    try {
        // Get max sort order
        $sql = "SELECT MAX(sort_order) as max_order FROM project_gallery WHERE project_id = :project_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $project_id]);
        $result = $stmt->fetch();
        $sort_order = ($result['max_order'] ?? -1) + 1;
        
        $sql = "INSERT INTO project_gallery (project_id, image_path, image_title, image_description, sort_order) 
                VALUES (:project_id, :image_path, :title, :description, :sort_order)";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':project_id' => $project_id,
            ':image_path' => $image_path,
            ':title' => $title,
            ':description' => $description,
            ':sort_order' => $sort_order
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        return false;
    } catch (PDOException $e) {
        error_log("Database error in addGalleryImage: " . $e->getMessage());
        return false;
    }
}
    
    public function getGalleryImages($project_id) {
        $sql = "SELECT * FROM project_gallery WHERE project_id = :project_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $project_id]);
        return $stmt->fetchAll();
    }
    
    public function deleteGalleryImage($id) {
        // Get image path first
        $sql = "SELECT image_path FROM project_gallery WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $image = $stmt->fetch();
        
        if ($image) {
            $this->deleteFile($image['image_path']);
        }
        
        $sql = "DELETE FROM project_gallery WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function updateGallerySort($project_id, $orders) {
        foreach ($orders as $id => $order) {
            $sql = "UPDATE project_gallery SET sort_order = :order WHERE id = :id AND project_id = :project_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':order' => $order, ':id' => $id, ':project_id' => $project_id]);
        }
        return true;
    }
    

// app/models/ProjectModel.php (Fix the getVideoEmbedCode method)

private function getVideoEmbedCode($url, $type) {
    if ($type == 'youtube') {
        // Extract YouTube video ID from various URL formats
        $patterns = [
            '/(?:youtube\.com\/watch\?v=)([^&]+)/',
            '/(?:youtu\.be\/)([^?]+)/',
            '/(?:youtube\.com\/embed\/)([^?]+)/',
            '/(?:youtube\.com\/v\/)([^?]+)/'
        ];
        
        $video_id = '';
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $video_id = $matches[1];
                break;
            }
        }
        
        if ($video_id) {
            return '<iframe width="100%" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        }
    } elseif ($type == 'vimeo') {
        // Extract Vimeo video ID
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            $video_id = $matches[1];
            return '<iframe src="https://player.vimeo.com/video/' . $video_id . '" width="100%" height="315" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        }
    }
    
    // Return original URL if no embed code generated
    return '<div class="alert alert-warning">Unable to embed video. <a href="' . htmlspecialchars($url) . '" target="_blank">Click here to watch</a></div>';
}
    
    public function getVideos($project_id) {
        $sql = "SELECT * FROM project_videos WHERE project_id = :project_id ORDER BY sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $project_id]);
        return $stmt->fetchAll();
    }
    
    public function deleteVideo($id) {
        $sql = "DELETE FROM project_videos WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    // Tag Methods
    public function addTag($tag_name) {
        $slug = $this->createSlug($tag_name);
        $sql = "INSERT INTO project_tags (tag_name, tag_slug) VALUES (:name, :slug) 
                ON DUPLICATE KEY UPDATE tag_name = tag_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $tag_name, ':slug' => $slug]);
        return $this->db->lastInsertId();
    }
    
    public function getTagId($tag_name) {
        $sql = "SELECT id FROM project_tags WHERE tag_name = :name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $tag_name]);
        $tag = $stmt->fetch();
        return $tag ? $tag['id'] : null;
    }
    
    public function addProjectTags($project_id, $tags) {
        // First delete existing tags
        $sql = "DELETE FROM project_tag_relations WHERE project_id = :project_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $project_id]);
        
        // Add new tags
        foreach ($tags as $tag_name) {
            $tag_name = trim($tag_name);
            if (empty($tag_name)) continue;
            
            $tag_id = $this->getTagId($tag_name);
            if (!$tag_id) {
                $tag_id = $this->addTag($tag_name);
            }
            
            $sql = "INSERT INTO project_tag_relations (project_id, tag_id) VALUES (:project_id, :tag_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':project_id' => $project_id, ':tag_id' => $tag_id]);
        }
        return true;
    }
    
    public function getProjectTags($project_id) {
        $sql = "SELECT t.tag_name FROM project_tags t 
                JOIN project_tag_relations r ON t.id = r.tag_id 
                WHERE r.project_id = :project_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $project_id]);
        return $stmt->fetchAll();
    }
    
    // Helper Methods
    private function createSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
    
    private function deleteFile($file_path) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }
}
?>