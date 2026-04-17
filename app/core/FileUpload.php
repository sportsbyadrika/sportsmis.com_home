<?php
namespace Core;

class FileUpload
{
    private array $cfg;

    public function __construct()
    {
        $app = require CONFIG_ROOT . '/app.php';
        $this->cfg = $app['upload'];
    }

    public function upload(array $file, string $subDir = '', bool $imageOnly = false): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload error: ' . $file['error']);
        }

        $maxSize = $imageOnly ? $this->cfg['photo_size'] : $this->cfg['max_size'];
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('File too large. Max ' . round($maxSize / 1024 / 1024) . ' MB allowed.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = $imageOnly ? $this->cfg['img_only'] : $this->cfg['allowed'];
        if (!in_array($ext, $allowed, true)) {
            throw new \RuntimeException('File type not allowed. Allowed: ' . implode(', ', $allowed));
        }

        // Validate image mime for images
        if ($imageOnly) {
            $mime = mime_content_type($file['tmp_name']);
            if (!str_starts_with($mime, 'image/')) {
                throw new \RuntimeException('File is not a valid image.');
            }
        }

        $dir = rtrim($this->cfg['path'] . $subDir, '/') . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return '/' . ltrim(rtrim($this->cfg['url'], '/') . '/' . ltrim($subDir . '/' . $filename, '/'), '/');
    }

    public function delete(string $relativePath): void
    {
        $abs = PUBLIC_ROOT . '/' . ltrim($relativePath, '/');
        if (file_exists($abs)) unlink($abs);
    }
}
