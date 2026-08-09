<?php
// app/models/SubcategoryModel.php
namespace Models;

class SubcategoryModel extends Model
{
    protected string $table = 'subcategories';

    public function create(int $categoryId, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'id' => null, 'error' => 'Subcategory name is required.'];
        }
        if (!$this->categoryBelongsToTenant($categoryId)) {
            return ['ok' => false, 'id' => null, 'error' => 'Choose a valid category.'];
        }
        if ($this->nameTaken($categoryId, $name)) {
            return ['ok' => false, 'id' => null, 'error' => 'That subcategory already exists in this category.'];
        }
        try {
            $id = $this->insert(['category_id' => $categoryId, 'name' => $name, 'status' => 'active']);
            return ['ok' => true, 'id' => $id, 'error' => null];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'id' => null, 'error' => 'That subcategory already exists in this category.'];
            }
            throw $e;
        }
    }

    public function rename(int $id, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Subcategory name is required.'];
        }
        $row = $this->find($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'Subcategory not found.'];
        }
        if ($this->nameTaken((int) $row['category_id'], $name, $id)) {
            return ['ok' => false, 'error' => 'That subcategory already exists in this category.'];
        }
        $this->update($id, ['name' => $name]);
        return ['ok' => true, 'error' => null];
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'draft'], true)) {
            return false;
        }
        return $this->update($id, ['status' => $status]);
    }

    public function deleteSafe(int $id): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM products WHERE subcategory_id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tid]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'This subcategory still has products. Move or delete them first.'];
        }
        $this->delete($id);
        return ['ok' => true, 'error' => null];
    }

    public function listForCategory(int $categoryId): array
    {
        return $this->all(['category_id' => $categoryId], 'name ASC');
    }

    public function nameTaken(int $categoryId, string $name, ?int $exceptId = null): bool
    {
        foreach ($this->all(['category_id' => $categoryId, 'name' => trim($name)]) as $row) {
            if ($exceptId === null || (int) $row['id'] !== $exceptId) {
                return true;
            }
        }
        return false;
    }

    private function categoryBelongsToTenant(int $categoryId): bool
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare('SELECT 1 FROM categories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$categoryId, $tid]);
        return (bool) $stmt->fetchColumn();
    }
}