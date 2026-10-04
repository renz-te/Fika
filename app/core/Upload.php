<?php

class Upload {
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf'
    ];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB limit

    /**
     * Handle a secure file upload with MIME verification and randomized filename.
     * Returns the generated filename on success, or null on failure.
     */
    public static function handle(array $fileUploadInfo, string $destinationDir): ?string {
        if (!isset($fileUploadInfo['error']) || $fileUploadInfo['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        if ($fileUploadInfo['size'] > self::MAX_SIZE_BYTES) {
            return null;
        }

        // Validate MIME type strictly via finfo, NOT via $_FILES['type']
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fileUploadInfo['tmp_name']);
        finfo_close($finfo);

        if (!array_key_exists($mime, self::ALLOWED_MIMES)) {
            return null;
        }

        $extension = self::ALLOWED_MIMES[$mime];
        
        // Generate a cryptographically secure random filename
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $targetPath = rtrim($destinationDir, '/') . '/' . $safeFilename;
        
        if (move_uploaded_file($fileUploadInfo['tmp_name'], $targetPath)) {
            return $safeFilename;
        }

        return null;
    }

    /**
     * Proxy serve a protected file back to the browser safely.
     * Useful for serving documents/ID photos without exposing the direct upload directory.
     */
    public static function serve(string $absoluteFilePath, string $mimeType = 'application/octet-stream'): void {
        if (!file_exists($absoluteFilePath)) {
            http_response_code(404);
            die(json_encode(["error" => "File not found."]));
        }

        header("Content-Type: " . $mimeType);
        header("Content-Length: " . filesize($absoluteFilePath));
        header("Cache-Control: private, max-age=31536000"); // 1 year cache for static assets
        header("Content-Disposition: inline; filename=\"" . basename($absoluteFilePath) . "\"");

        // Use readfile to dump the file content to output buffer safely
        readfile($absoluteFilePath);
        exit;
    }
}
