<?php

namespace Akami\Controllers;

class UploadController
{
    private string $uploadDir;

    public function __construct(?string $dir = null)
    {
        // Cargar config.php opcional (para entornos cPanel)
        $configFile = dirname(__DIR__, 2) . '/config.php';
        if (file_exists($configFile)) {
            require_once $configFile;
        }

        $defaultDir = dirname(__DIR__, 2) . '/uploads/products';
        if (!is_dir($defaultDir) && is_dir(dirname(__DIR__, 2) . '/../uploads/products')) {
            $defaultDir = dirname(__DIR__, 2) . '/../uploads/products';
        }
        $this->uploadDir = $dir ?: (defined('UPLOAD_DIR') ? UPLOAD_DIR : (getenv('UPLOAD_DIR') ?: $defaultDir));

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    public function handleUpload(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \RuntimeException('Parámetros de subida de archivo inválidos.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new \RuntimeException('No se ha enviado ningún archivo.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new \RuntimeException('El archivo supera el tamaño máximo permitido (25MB).');
            default:
                throw new \RuntimeException('Error desconocido al subir el archivo.');
        }

        if ($file['size'] > 25 * 1024 * 1024) {
            throw new \RuntimeException('El archivo supera los 25MB.');
        }

        // Validar tipo MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/x-png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
        ];

        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Formato no permitido. Solo se aceptan imágenes JPG, PNG, WebP o AVIF.');
        }

        $ext = $allowed[$mime];
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($originalName));
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        if (empty($slug)) {
            $slug = 'producto';
        }

        $filename = sprintf('%s-%s.%s', $slug, bin2hex(random_bytes(4)), $ext);
        $destination = rtrim($this->uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Error al guardar el archivo en el almacenamiento.');
        }

        return [
            'success' => true,
            'url' => '/uploads/products/' . $filename,
            'filename' => $filename,
            'size' => $file['size'],
            'mime' => $mime,
        ];
    }
}
