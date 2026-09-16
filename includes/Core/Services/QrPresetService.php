<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;
use KaiMail\Core\Http\ApiException;

/**
 * QR Preset management service.
 * Handles CRUD operations for admin and public presets fetching.
 */
final class QrPresetService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * List active presets for public user page.
     */
    public function listPublicActive(): array
    {
        $sql = "SELECT id, title, content, category, sort_order, created_at, updated_at
                FROM qr_presets
                WHERE is_active = 1
                ORDER BY sort_order ASC, id DESC";
        $stmt = $this->db->query($sql);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * List all presets for admin panel.
     */
    public function listAll(): array
    {
        $sql = "SELECT id, title, content, category, sort_order, is_active, created_at, updated_at
                FROM qr_presets
                ORDER BY sort_order ASC, id DESC";
        $stmt = $this->db->query($sql);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, title, content, category, sort_order, is_active, created_at, updated_at FROM qr_presets WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(string $title, string $content, string $category = 'general', int $sortOrder = 0, int $isActive = 1): array
    {
        $title = trim($title);
        $content = trim($content);
        $category = trim($category) !== '' ? trim($category) : 'general';

        if ($title === '') {
            throw ApiException::badRequest('Tiêu đề mẫu QR không được để trống');
        }

        if ($content === '') {
            throw ApiException::badRequest('Nội dung mẫu QR không được để trống');
        }

        $stmt = $this->db->prepare("INSERT INTO qr_presets (title, content, category, sort_order, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $category, $sortOrder, $isActive ? 1 : 0]);

        $id = (int) $this->db->lastInsertId();
        return $this->find($id) ?? [];
    }

    public function update(int $id, array $data): array
    {
        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID mẫu QR cần cập nhật');
        }

        $existing = $this->find($id);
        if (!$existing) {
            throw ApiException::notFound('Mẫu QR không tồn tại');
        }

        $title = isset($data['title']) ? trim((string) $data['title']) : $existing['title'];
        $content = isset($data['content']) ? trim((string) $data['content']) : $existing['content'];
        $category = isset($data['category']) ? trim((string) $data['category']) : $existing['category'];
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : (int) $existing['sort_order'];
        $isActive = isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : (int) $existing['is_active'];

        if ($title === '') {
            throw ApiException::badRequest('Tiêu đề mẫu QR không được để trống');
        }

        if ($content === '') {
            throw ApiException::badRequest('Nội dung mẫu QR không được để trống');
        }

        $stmt = $this->db->prepare("UPDATE qr_presets SET title = ?, content = ?, category = ?, sort_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $content, $category, $sortOrder, $isActive, $id]);

        return $this->find($id) ?? [];
    }

    public function updateStatus(int $id, int $isActive): bool
    {
        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID mẫu QR');
        }

        $stmt = $this->db->prepare("UPDATE qr_presets SET is_active = ? WHERE id = ?");
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw ApiException::badRequest('Thiếu ID mẫu QR');
        }

        $stmt = $this->db->prepare("DELETE FROM qr_presets WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get the single primary QR preset text from settings.
     */
    public function getQrText(): string
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'qr_preset_text' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return is_string($val) ? $val : '';
    }

    /**
     * Save/update the single primary QR preset text in settings (Admin only).
     */
    public function saveQrText(string $text): string
    {
        $text = trim($text);
        $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('qr_preset_text', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$text]);
        return $text;
    }
}
