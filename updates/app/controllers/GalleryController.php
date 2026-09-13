<?php
require_once __DIR__ . '/../models/GalleryModel.php';

class GalleryController {
    private $galleryModel;
    
    public function __construct($pdo) {
        $this->galleryModel = new GalleryModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function create($data, $media_file = null) {
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }
        
        $file_path = null;
        $thumbnail_path = null;
        $media_type = $data['media_type'] ?? 'image';
        
        // Handle file upload
        if ($media_file && $media_file['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Ismano/public/uploads/gallery/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($media_file['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($media_file['tmp_name'], $target_file)) {
                $file_path = '/Ismano/public/uploads/gallery/' . $filename;
                
                // Determine media type
                if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov'])) {
                    $media_type = 'video';
                } else {
                    $media_type = 'image';
                }
                
                // Create thumbnail for images
                if ($media_type === 'image') {
                    $this->createThumbnail($target_file, $upload_dir . 'thumb_' . $filename);
                    $thumbnail_path = '/Ismano/public/uploads/gallery/thumb_' . $filename;
                }
            }
        }
        
        $galleryData = [
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':media_type' => $media_type,
            ':file_path' => $file_path,
            ':thumbnail_path' => $thumbnail_path,
            ':video_url' => $data['video_url'] ?? null,
            ':video_embed_code' => $data['video_embed_code'] ?? null,
            ':category' => $data['category'] ?? null,
            ':tags' => $data['tags'] ?? null,
            ':sort_order' => $data['sort_order'] ?? 0,
            ':is_featured' => isset($data['is_featured']) ? 1 : 0,
            ':status' => $data['status'] ?? 'active',
            ':created_by' => $_SESSION['user_id'] ?? null
        ];
        
        $galleryId = $this->galleryModel->create($galleryData);
        
        if ($galleryId) {
            return ['success' => true, 'message' => 'Gallery item created successfully', 'id' => $galleryId];
        }
        
        return ['success' => false, 'message' => 'Failed to create gallery item'];
    }
    
    private function createThumbnail($source, $destination, $width = 300, $height = 300) {
        $info = getimagesize($source);
        if ($info === false) return false;
        
        $src = null;
        switch ($info['mime']) {
            case 'image/jpeg': $src = imagecreatefromjpeg($source); break;
            case 'image/png': $src = imagecreatefrompng($source); break;
            case 'image/webp': $src = imagecreatefromwebp($source); break;
            default: return false;
        }
        
        $thumb = imagecreatetruecolor($width, $height);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        
        switch ($info['mime']) {
            case 'image/jpeg': imagejpeg($thumb, $destination, 80); break;
            case 'image/png': imagepng($thumb, $destination, 8); break;
            case 'image/webp': imagewebp($thumb, $destination, 80); break;
        }
        
        imagedestroy($src);
        imagedestroy($thumb);
        return true;
    }
    
    public function getAll($status = 'active', $limit = null, $offset = 0) {
        return $this->galleryModel->getAll($status, $limit, $offset);
    }
    
    public function getById($id) {
        $item = $this->galleryModel->getById($id);
        if ($item) {
            $this->galleryModel->incrementViews($id);
        }
        return $item;
    }
    
    public function update($id, $data, $media_file = null) {
        $updateData = [];
        
        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['category'])) $updateData['category'] = $data['category'];
        if (isset($data['tags'])) $updateData['tags'] = $data['tags'];
        if (isset($data['sort_order'])) $updateData['sort_order'] = $data['sort_order'];
        if (isset($data['is_featured'])) $updateData['is_featured'] = $data['is_featured'] ? 1 : 0;
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        
        // Handle new file upload
        if ($media_file && $media_file['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Ismano/public/uploads/gallery/';
            $ext = pathinfo($media_file['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($media_file['tmp_name'], $target_file)) {
                $updateData['file_path'] = '/Ismano/public/uploads/gallery/' . $filename;
            }
        }
        
        $result = $this->galleryModel->update($id, $updateData);
        
        if ($result) {
            return ['success' => true, 'message' => 'Gallery item updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update gallery item'];
    }
    
    public function delete($id) {
        $result = $this->galleryModel->delete($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Gallery item deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete gallery item'];
    }
    
    public function getCategories() {
        return $this->galleryModel->getCategories();
    }
}
?>