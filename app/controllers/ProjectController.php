<?php
// app/controllers/ProjectController.php (Complete fixed version)
require_once __DIR__ . '/../models/ProjectModel.php';

class ProjectController {
    private $projectModel;
    
    public function __construct($pdo) {
        $this->projectModel = new ProjectModel($pdo);
    }
    
    // Category Operations
    public function createCategory($data) {
        if (empty($data['category_name'])) {
            return ['success' => false, 'message' => 'Category name is required'];
        }
        
        if ($this->projectModel->createCategory($data)) {
            return ['success' => true, 'message' => 'Category created successfully'];
        }
        return ['success' => false, 'message' => 'Failed to create category'];
    }
    
    public function getCategories() {
        return $this->projectModel->getCategories();
    }
    
    public function getCategoryById($id) {
        return $this->projectModel->getCategoryById($id);
    }
    
    public function updateCategory($id, $data) {
        if ($this->projectModel->updateCategory($id, $data)) {
            return ['success' => true, 'message' => 'Category updated successfully'];
        }
        return ['success' => false, 'message' => 'Failed to update category'];
    }
    
    public function deleteCategory($id) {
        $categories = $this->projectModel->getCategories();
        foreach ($categories as $cat) {
            if ($cat['id'] == $id && $cat['project_count'] > 0) {
                return ['success' => false, 'message' => 'Cannot delete category with existing projects. Delete or move projects first.'];
            }
        }
        
        if ($this->projectModel->deleteCategory($id)) {
            return ['success' => true, 'message' => 'Category deleted successfully'];
        }
        return ['success' => false, 'message' => 'Failed to delete category'];
    }
    
    // Project Operations
    public function createProject($data, $files) {
        // Validate required fields
        if (empty($data['category_id']) || empty($data['small_title']) || empty($data['major_title'])) {
            return ['success' => false, 'message' => 'Category, small title, and major title are required'];
        }
        
        // Handle cover image upload
        $cover_image = null;
        if (isset($files['cover_image']) && $files['cover_image']['error'] == 0) {
            $upload_result = $this->uploadFile($files['cover_image'], 'projects/covers');
            if ($upload_result['success']) {
                $cover_image = $upload_result['path'];
            }
        }
        
        $data['cover_image'] = $cover_image;
        $project_id = $this->projectModel->createProject($data);
        
        if ($project_id) {
            // Handle tags if provided
            if (!empty($data['tags'])) {
                $tags = explode(',', $data['tags']);
                $this->projectModel->addProjectTags($project_id, $tags);
            }
            
            return ['success' => true, 'message' => 'Project created successfully', 'project_id' => $project_id];
        }
        return ['success' => false, 'message' => 'Failed to create project'];
    }
    
    public function getProjects($status = null) {
        return $this->projectModel->getProjects($status);
    }
    
    public function getProject($id) {
        return $this->projectModel->getProjectById($id);
    }
    
    public function updateProject($id, $data, $files) {
        // Get existing project
        $existingProject = $this->getProject($id);
        if (!$existingProject) {
            return ['success' => false, 'message' => 'Project not found'];
        }
        
        // Handle cover image upload if new image provided
        $cover_image = $existingProject['cover_image']; // Keep existing by default
        if (isset($files['cover_image']) && $files['cover_image']['error'] == 0) {
            $upload_result = $this->uploadFile($files['cover_image'], 'projects/covers');
            if ($upload_result['success']) {
                $cover_image = $upload_result['path'];
                // Delete old cover image if exists
                if ($existingProject['cover_image']) {
                    $old_file = $_SERVER['DOCUMENT_ROOT'] . $existingProject['cover_image'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
            }
        }
        
        // Prepare data for update
        $updateData = [
            'category_id' => $data['category_id'] ?? $existingProject['category_id'],
            'small_title' => $data['small_title'] ?? $existingProject['small_title'],
            'major_title' => $data['major_title'] ?? $existingProject['major_title'],
            'description' => $data['description'] ?? $existingProject['description'],
            'cover_image' => $cover_image,
            'status' => $data['status'] ?? $existingProject['status']
        ];
        
        if ($this->projectModel->updateProject($id, $updateData)) {
            // Update tags
            if (isset($data['tags'])) {
                $tags = explode(',', $data['tags']);
                $this->projectModel->addProjectTags($id, $tags);
            }
            return ['success' => true, 'message' => 'Project updated successfully'];
        }
        return ['success' => false, 'message' => 'Failed to update project'];
    }
    
    public function publishProject($id) {
        // Get existing project data
        $project = $this->getProject($id);
        if (!$project) {
            return ['success' => false, 'message' => 'Project not found'];
        }
        
        // Use the model method to publish
        if ($this->projectModel->publishProject($id)) {
            return ['success' => true, 'message' => 'Project published successfully! It is now visible to the public.'];
        }
        return ['success' => false, 'message' => 'Failed to publish project'];
    }
    
    public function saveAsDraft($id) {
        // Get existing project data
        $project = $this->getProject($id);
        if (!$project) {
            return ['success' => false, 'message' => 'Project not found'];
        }
        
        // Use the model method to save as draft
        if ($this->projectModel->saveAsDraft($id)) {
            return ['success' => true, 'message' => 'Project saved as draft. It is now hidden from the public.'];
        }
        return ['success' => false, 'message' => 'Failed to save as draft'];
    }
    
    public function deleteProject($id) {
        if ($this->projectModel->deleteProject($id)) {
            return ['success' => true, 'message' => 'Project deleted successfully'];
        }
        return ['success' => false, 'message' => 'Failed to delete project'];
    }
    
    // Gallery Operations
    public function uploadGalleryImage($project_id, $file) {
        $upload_result = $this->uploadFile($file, 'projects/gallery');
        if ($upload_result['success']) {
            if ($this->projectModel->addGalleryImage($project_id, $upload_result['path'])) {
                return ['success' => true, 'message' => 'Image uploaded successfully', 'image' => $upload_result['path']];
            }
        }
        return ['success' => false, 'message' => 'Failed to upload image'];
    }
    
    public function getGalleryImages($project_id) {
        return $this->projectModel->getGalleryImages($project_id);
    }
    
    public function deleteGalleryImage($id) {
        if ($this->projectModel->deleteGalleryImage($id)) {
            return ['success' => true, 'message' => 'Image deleted successfully'];
        }
        return ['success' => false, 'message' => 'Failed to delete image'];
    }
    
    // Video Operations
    public function addVideo($project_id, $data) {
        if (empty($data['video_url'])) {
            return ['success' => false, 'message' => 'Video URL is required'];
        }
        
        if ($this->projectModel->addVideo($project_id, $data)) {
            return ['success' => true, 'message' => 'Video added successfully'];
        }
        return ['success' => false, 'message' => 'Failed to add video'];
    }
    
    public function getVideos($project_id) {
        return $this->projectModel->getVideos($project_id);
    }
    
    public function deleteVideo($id) {
        if ($this->projectModel->deleteVideo($id)) {
            return ['success' => true, 'message' => 'Video deleted successfully'];
        }
        return ['success' => false, 'message' => 'Failed to delete video'];
    }
    
    // Tag Operations
    public function getProjectTags($project_id) {
        return $this->projectModel->getProjectTags($project_id);
    }
    
    // File Upload Helper
    private function uploadFile($file, $subdirectory) {
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/Ismano/public/uploads/" . $subdirectory . "/";
        
        // Create directory if not exists
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP'];
        }
        
        $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return ['success' => true, 'path' => "/Ismano/public/uploads/" . $subdirectory . "/" . $new_filename];
        }
        
        return ['success' => false, 'message' => 'Failed to upload file'];
    }

    // Add this public method to update view count
public function incrementViewCount($project_id) {
    return $this->projectModel->updateViewCount($project_id);
}
}
?>