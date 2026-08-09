<?php
// app/controllers/BlogController.php

require_once __DIR__ . '/../models/BlogModel.php';

class BlogController {
    private $blogModel;
    
    public function __construct($pdo) {
        $this->blogModel = new BlogModel($pdo);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // Generate slug
    private function generateSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
    
    // Create blog post
    public function create($data, $featured_image = null) {
        if (empty($data['title']) || empty($data['content'])) {
            return ['success' => false, 'message' => 'Title and content are required'];
        }
        
        $slug = $this->generateSlug($data['title']);
        
        // Check if slug exists
        $existing = $this->blogModel->getBySlug($slug);
        if ($existing) {
            $slug = $slug . '-' . time();
        }
        
        // Handle category_id (convert empty string to null)
        $category_id = !empty($data['category_id']) ? $data['category_id'] : null;
        
        $blogData = [
            ':title' => $data['title'],
            ':slug' => $slug,
            ':excerpt' => $data['excerpt'] ?? substr(strip_tags($data['content']), 0, 200),
            ':content' => $data['content'],
            ':featured_image' => $featured_image,
            ':category_id' => $category_id,
            ':author_id' => $_SESSION['user_id'],
            ':status' => $data['status'] ?? 'draft',
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':meta_keywords' => $data['meta_keywords'] ?? null,
            ':published_at' => ($data['status'] ?? '') === 'published' ? date('Y-m-d H:i:s') : null
        ];
        
        $blogId = $this->blogModel->create($blogData);
        
        if ($blogId) {
            // Handle tags
            if (!empty($data['tags'])) {
                $tagIds = [];
                foreach ($data['tags'] as $tagName) {
                    $tagName = trim($tagName);
                    if (empty($tagName)) continue;
                    
                    $tagSlug = $this->generateSlug($tagName);
                    
                    // Check if tag exists
                    $allTags = $this->blogModel->getAllTags();
                    $existingTag = null;
                    foreach ($allTags as $tag) {
                        if ($tag['slug'] === $tagSlug) {
                            $existingTag = $tag;
                            break;
                        }
                    }
                    
                    if ($existingTag) {
                        $tagIds[] = $existingTag['id'];
                    } else {
                        $newTagId = $this->blogModel->createTag($tagName, $tagSlug);
                        $tagIds[] = $newTagId;
                    }
                }
                
                if (!empty($tagIds)) {
                    $this->blogModel->addTags($blogId, $tagIds);
                }
            }
            
            // Handle sections
            if (!empty($data['sections'])) {
                foreach ($data['sections'] as $index => $section) {
                    $this->addSection($blogId, $section, $index);
                }
            }
            
            // Handle FAQs
            if (!empty($data['faqs'])) {
                foreach ($data['faqs'] as $index => $faq) {
                    $this->addFaq($blogId, $faq, $index);
                }
            }
            
            return ['success' => true, 'message' => 'Blog post created successfully', 'blog_id' => $blogId];
        }
        
        return ['success' => false, 'message' => 'Failed to create blog post'];
    }
    
    // Get blog with all details
    public function getBlogDetails($identifier) {
        $blog = is_numeric($identifier) ? 
                $this->blogModel->getById($identifier) : 
                $this->blogModel->getBySlug($identifier);
        
        if ($blog) {
            $blog['sections'] = $this->blogModel->getSections($blog['id']);
            $blog['faqs'] = $this->blogModel->getFaqs($blog['id']);
            $blog['tags'] = $this->blogModel->getTags($blog['id']);
            $blog['related_posts'] = $this->blogModel->getRelatedPosts($blog['id'], $blog['category_id']);
        }
        
        return $blog;
    }
    
    // Update blog
    public function update($id, $data, $featured_image = null) {
        $updateData = [];
        
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
            $updateData['slug'] = $this->generateSlug($data['title']);
        }
        
        if (isset($data['excerpt'])) $updateData['excerpt'] = $data['excerpt'];
        if (isset($data['content'])) $updateData['content'] = $data['content'];
        if ($featured_image) $updateData['featured_image'] = $featured_image;
        
        // Handle category_id (convert empty string to null)
        if (isset($data['category_id'])) {
            $updateData['category_id'] = !empty($data['category_id']) ? $data['category_id'] : null;
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['is_featured'])) $updateData['is_featured'] = $data['is_featured'];
        if (isset($data['meta_title'])) $updateData['meta_title'] = $data['meta_title'];
        if (isset($data['meta_description'])) $updateData['meta_description'] = $data['meta_description'];
        if (isset($data['meta_keywords'])) $updateData['meta_keywords'] = $data['meta_keywords'];
        
        $result = $this->blogModel->update($id, $updateData);
        
        if ($result && isset($data['tags'])) {
            // Update tags
            $this->blogModel->removeTags($id);
            
            $tagIds = [];
            foreach ($data['tags'] as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) continue;
                
                $tagSlug = $this->generateSlug($tagName);
                $allTags = $this->blogModel->getAllTags();
                $existingTag = null;
                foreach ($allTags as $tag) {
                    if ($tag['slug'] === $tagSlug) {
                        $existingTag = $tag;
                        break;
                    }
                }
                
                if ($existingTag) {
                    $tagIds[] = $existingTag['id'];
                } else {
                    $newTagId = $this->blogModel->createTag($tagName, $tagSlug);
                    $tagIds[] = $newTagId;
                }
            }
            
            if (!empty($tagIds)) {
                $this->blogModel->addTags($id, $tagIds);
            }
        }
        
        if ($result) {
            return ['success' => true, 'message' => 'Blog post updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update blog post'];
    }
    
    // Add section
    public function addSection($blog_id, $data, $sort_order = 0) {
        $media_url = $data['media_url'] ?? null;
        $video_id = $data['video_id'] ?? null;
        $media_type = $data['media_type'] ?? 'image';
        
        // Handle file upload
        if (isset($data['media_file']) && $data['media_file']['error'] === 0) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Ismano/public/uploads/blog/sections/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($data['media_file']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($data['media_file']['tmp_name'], $target_file)) {
                $media_url = '/Ismano/public/uploads/blog/sections/' . $filename;
                
                // Determine media type
                if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
                    $media_type = 'video';
                } else {
                    $media_type = 'image';
                }
            }
        }
        
        // Extract YouTube video ID
        if ($data['section_type'] === 'youtube' && $media_url) {
            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $media_url, $matches);
            $video_id = $matches[1] ?? null;
            $media_type = 'youtube';
        }
        
        $sectionData = [
            ':blog_id' => $blog_id,
            ':section_type' => $data['section_type'],
            ':title' => $data['title'] ?? null,
            ':content' => $data['content'] ?? null,
            ':media_url' => $media_url,
            ':media_type' => $media_type,
            ':video_id' => $video_id,
            ':sort_order' => $sort_order
        ];
        
        return $this->blogModel->addSection($sectionData);
    }
    
    // Update section
    public function updateSection($section_id, $data) {
        return $this->blogModel->updateSection($section_id, $data);
    }
    
    // Delete section
    public function deleteSection($section_id) {
        return $this->blogModel->deleteSection($section_id);
    }
    
    // Add FAQ
    public function addFaq($blog_id, $data, $sort_order = 0) {
        $faqData = [
            ':blog_id' => $blog_id,
            ':question' => $data['question'],
            ':answer' => $data['answer'],
            ':sort_order' => $sort_order
        ];
        
        return $this->blogModel->addFaq($faqData);
    }
    
    // Update FAQ
    public function updateFaq($faq_id, $data) {
        return $this->blogModel->updateFaq($faq_id, $data);
    }
    
    // Delete FAQ
    public function deleteFaq($faq_id) {
        return $this->blogModel->deleteFaq($faq_id);
    }
    
    // Delete blog
    public function delete($id) {
        $blog = $this->blogModel->getById($id);
        if ($blog && $blog['featured_image']) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $blog['featured_image'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $result = $this->blogModel->delete($id);
        
        if ($result) {
            return ['success' => true, 'message' => 'Blog post deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete blog post'];
    }
    
    // Increment view count
    public function incrementViews($id) {
        return $this->blogModel->incrementViews($id);
    }
    
    // Get all blogs for listing
    public function getAllBlogs($status = 'published', $limit = 12, $offset = 0) {
        return $this->blogModel->getAll($status, $limit, $offset);
    }
    
    // Get categories
    public function getCategories() {
        return $this->blogModel->getAllCategories();
    }
    
// Create category (updated without created_by)
public function createCategory($name, $description = null, $color = '#667eea') {
    if (empty($name)) {
        return ['success' => false, 'message' => 'Category name is required'];
    }
    
    $slug = $this->generateSlug($name);
    
    // Check if category already exists
    $categories = $this->blogModel->getAllCategories();
    foreach ($categories as $cat) {
        if ($cat['slug'] === $slug) {
            return ['success' => false, 'message' => 'Category already exists'];
        }
    }
    
    $category_id = $this->blogModel->createCategory($name, $slug, $description, $color);
    
    if ($category_id) {
        return ['success' => true, 'message' => 'Category created successfully', 'category_id' => $category_id];
    }
    
    return ['success' => false, 'message' => 'Failed to create category'];
}
    
    // Update category
    public function updateCategory($id, $data) {
        $slug = $this->generateSlug($data['name']);
        $data['slug'] = $slug;
        
        if ($this->blogModel->updateCategory($id, $data)) {
            return ['success' => true, 'message' => 'Category updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update category'];
    }
    
    // Delete category
    public function deleteCategory($id) {
        if ($this->blogModel->deleteCategory($id)) {
            return ['success' => true, 'message' => 'Category deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete category'];
    }
    
    // Get all tags
    public function getAllTags() {
        return $this->blogModel->getAllTags();
    }
    
    // Get featured blogs
    public function getFeaturedBlogs($limit = 3) {
        return $this->blogModel->getFeatured($limit);
    }
    
    // Search blogs
    public function searchBlogs($keyword) {
        return $this->blogModel->search($keyword);
    }
}
?>