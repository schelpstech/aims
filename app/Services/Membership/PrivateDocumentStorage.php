<?php

declare(strict_types=1);

namespace App\Services\Membership;

use RuntimeException;

final class PrivateDocumentStorage implements DocumentStorageInterface
{
    /** @param list<string> $allowedMimeTypes */
    public function __construct(
        private readonly string $rootPath,
        private readonly int $maximumBytes,
        private readonly array $allowedMimeTypes,
    ) {
    }

    public function store(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }

        $temporaryPath = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('The uploaded document could not be verified.');
        }
        $actualSize = filesize($temporaryPath);
        $size = is_int($actualSize) ? $actualSize : 0;
        if ($size < 1 || $size > max(1, $this->maximumBytes)) {
            throw new RuntimeException('The document exceeds the permitted file size.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mime) || !in_array($mime, $this->allowedMimeTypes, true)) {
            throw new RuntimeException('Only PDF, JPEG, and PNG documents are accepted.');
        }

        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new RuntimeException('The document type is not permitted.'),
        };
        $originalName = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'document')));
        $originalName = mb_substr(preg_replace('/[\x00-\x1F\x7F]+/u', '', $originalName) ?? 'document', 0, 255);
        $relativePath = date('Y/m') . '/' . bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Secure document storage is unavailable.');
        }

        $digest = hash_file('sha256', $temporaryPath);
        if (!is_string($digest) || !move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('The document could not be stored securely.');
        }
        @chmod($destination, 0600);

        return [
            'original_name' => $originalName !== '' ? $originalName : 'document.' . $extension,
            'storage_path' => $relativePath,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => $digest,
        ];
    }

    public function delete(string $relativePath): void
    {
        if (preg_match('#^[0-9]{4}/[0-9]{2}/[a-f0-9]{48}\.(?:pdf|jpg|png)$#D', $relativePath) !== 1) {
            return;
        }
        $path = $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function read(string $relativePath): string
    {
        if (preg_match('#^[0-9]{4}/[0-9]{2}/[a-f0-9]{48}\.(?:pdf|jpg|png)$#D', $relativePath) !== 1) {
            throw new RuntimeException('The requested document is unavailable.');
        }
        $path = $this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($path)) {
            throw new RuntimeException('The requested document is unavailable.');
        }
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException('The requested document is unavailable.');
        }

        return $contents;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The document exceeds the permitted file size.',
            UPLOAD_ERR_PARTIAL => 'The document upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Select a document to upload.',
            default => 'The document upload failed.',
        };
    }
}
