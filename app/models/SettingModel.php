<?php
// app/models/SettingModel.php
//
// Read/write access to site settings, hero slides and page headers.
// Uses plain PDO (same connection your public pages already use via
// db_connect.php). Upserts are written DB-agnostically (SELECT then
// INSERT/UPDATE) so they behave identically on MySQL and during tests.

class SettingModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ============================================================
       KEY / VALUE SINGLETONS  (logo, site name, ...)
       ============================================================ */

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT setting_value FROM site_settings WHERE setting_key = ?"
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return $default;
        }
        return $row['setting_value'] ?? $default;
    }

    public function set(string $key, ?string $value): bool
    {
        $check = $this->pdo->prepare(
            "SELECT 1 FROM site_settings WHERE setting_key = ?"
        );
        $check->execute([$key]);

        if ($check->fetchColumn()) {
            $stmt = $this->pdo->prepare(
                "UPDATE site_settings SET setting_value = ? WHERE setting_key = ?"
            );
            return $stmt->execute([$value, $key]);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)"
        );
        return $stmt->execute([$key, $value]);
    }

    public function all(): array
    {
        $rows = $this->pdo
            ->query("SELECT setting_key, setting_value FROM site_settings")
            ->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    }

    /* ============================================================
       HERO SLIDES  (homepage)
       ============================================================ */

    public function getHeroSlides(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM hero_slides";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHeroSlide(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM hero_slides WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function addHeroSlide(string $imagePath, ?string $caption = null, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO hero_slides (image_path, caption, sort_order)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$imagePath, $caption, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateHeroSlide(int $id, array $fields): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE hero_slides
                SET caption = ?, sort_order = ?, is_active = ?
              WHERE id = ?"
        );
        return $stmt->execute([
            $fields['caption']    ?? null,
            (int) ($fields['sort_order'] ?? 0),
            (int) ($fields['is_active']  ?? 1),
            $id,
        ]);
    }

    /** Deletes the slide and returns its stored image_path (so the caller
     *  can remove the physical file), or null if it did not exist. */
    public function deleteHeroSlide(int $id): ?string
    {
        $slide = $this->getHeroSlide($id);
        if ($slide === null) {
            return null;
        }
        $this->pdo->prepare("DELETE FROM hero_slides WHERE id = ?")->execute([$id]);
        return $slide['image_path'];
    }

    /* ============================================================
       PAGE HEADERS  (per public page banner)
       ============================================================ */

    public function getPageHeader(string $pageKey): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM page_headers WHERE page_key = ?");
        $stmt->execute([$pageKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function getAllPageHeaders(): array
    {
        return $this->pdo
            ->query("SELECT * FROM page_headers ORDER BY page_key ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Insert or update a page header. If image_path is not supplied (null
     *  and key absent) the existing image is preserved. */
    public function upsertPageHeader(string $pageKey, array $fields): bool
    {
        $existing = $this->getPageHeader($pageKey);

        if ($existing !== null) {
            $image = (array_key_exists('image_path', $fields) && $fields['image_path'] !== null)
                ? $fields['image_path']
                : $existing['image_path'];

            $stmt = $this->pdo->prepare(
                "UPDATE page_headers
                    SET title = ?, subtitle = ?, image_path = ?
                  WHERE page_key = ?"
            );
            return $stmt->execute([
                $fields['title']    ?? $existing['title'],
                $fields['subtitle'] ?? $existing['subtitle'],
                $image,
                $pageKey,
            ]);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO page_headers (page_key, title, subtitle, image_path)
             VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([
            $pageKey,
            $fields['title']      ?? null,
            $fields['subtitle']   ?? null,
            $fields['image_path'] ?? null,
        ]);
    }
}