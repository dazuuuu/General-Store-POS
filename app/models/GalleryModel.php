<?php
class GalleryModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function create($data) {
        $sql = "INSERT INTO gallery (title, description, media_type, file_path, thumbnail_path,
                video_url, video_embed_code, category, tags, sort_order, is_featured, status, created_by)
                VALUES (:title, :description, :media_type, :file_path, :thumbnail_path,
                :video_url, :video_embed_code, :category, :tags, :sort_order, :is_featured, :status, :created_by)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data) ? $this->db->lastInsertId() : false;
    }

    /**
     * Flexible query used by both the public site and the API.
     * Supported keys: status, category, media_type, featured, limit, offset.
     * Any key left out / null is simply not filtered on.
     */
    public function getItems(array $f = []) {
        $sql = "SELECT g.*, u.username AS creator_name
                FROM gallery g
                LEFT JOIN users u ON g.created_by = u.id";

        $where  = [];
        $params = [];

        if (!empty($f['status'])) {
            $where[] = "g.status = :status";
            $params[':status'] = $f['status'];
        }
        if (!empty($f['category'])) {
            $where[] = "g.category = :category";
            $params[':category'] = $f['category'];
        }
        if (!empty($f['media_type'])) {
            $where[] = "g.media_type = :media_type";
            $params[':media_type'] = $f['media_type'];
        }
        // featured can legitimately be 0, so check existence rather than emptiness.
        if (array_key_exists('featured', $f) && $f['featured'] !== null && $f['featured'] !== '') {
            $where[] = "g.is_featured = :featured";
            $params[':featured'] = (int) $f['featured'];
        }

        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY g.sort_order ASC, g.created_at DESC";

        $limit  = isset($f['limit'])  ? (int) $f['limit']  : null;
        $offset = isset($f['offset']) ? (int) $f['offset'] : 0;
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null && $limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kept for backward compatibility with existing admin code.
     * Now delegates to getItems().
     */
    public function getAll($status = null, $limit = null, $offset = 0) {
        return $this->getItems([
            'status' => $status,
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    public function getById($id) {
        $sql = "SELECT * FROM gallery WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['title', 'description', 'media_type', 'file_path', 'thumbnail_path',
                   'video_url', 'video_embed_code', 'category', 'tags', 'sort_order', 'is_featured', 'status'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE gallery SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete($id) {
        $item = $this->getById($id);
        if ($item && !empty($item['file_path'])) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $item['file_path'];
            if (is_file($file_path)) @unlink($file_path);
            if (!empty($item['thumbnail_path']) && is_file($_SERVER['DOCUMENT_ROOT'] . $item['thumbnail_path'])) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . $item['thumbnail_path']);
            }
        }

        $sql = "DELETE FROM gallery WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function incrementViews($id) {
        $sql = "UPDATE gallery SET view_count = view_count + 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function getCategories() {
        $sql = "SELECT DISTINCT category FROM gallery WHERE category IS NOT NULL AND category != '' ORDER BY category";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}