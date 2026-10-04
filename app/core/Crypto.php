<?php

class Crypto {
    private const CIPHER = 'aes-256-gcm';

    /**
     * Retrieve and hash the app_key to ensure exactly 32 bytes for AES-256
     */
    private static function getKey(): string {
        $key = config('security.app_key');
        if (empty($key) || strlen($key) < 16) {
            die('CRITICAL: Insecure or missing security.app_key in configuration.');
        }
        return substr(hash('sha256', $key, true), 0, 32);
    }

    /**
     * Encrypt a plaintext string securely
     */
    public static function encrypt(string $plaintext): string {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = "";
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::getKey(), OPENSSL_RAW_DATA, $iv, $tag);
        // Pack the IV, Auth Tag, and Ciphertext together
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a payload. Returns null if tampering is detected or decryption fails.
     */
    public static function decrypt(string $payload): ?string {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null; // Invalid base64
        }
        
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $tagLen = 16; // GCM tag is always 16 bytes
        
        if (strlen($decoded) < $ivLen + $tagLen) {
            return null; // Malformed payload
        }

        $iv = substr($decoded, 0, $ivLen);
        $tag = substr($decoded, $ivLen, $tagLen);
        $ciphertext = substr($decoded, $ivLen + $tagLen);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::getKey(), OPENSSL_RAW_DATA, $iv, $tag);
        
        return $plaintext === false ? null : $plaintext;
    }
}
