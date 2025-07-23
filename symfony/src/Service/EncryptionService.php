<?php

namespace App\Service;

class EncryptionService
{
    private const CIPHER_METHOD = 'aes-256-cbc';
    private string $key;

    public function __construct(string $legacyEncryptionKey)
    {
        $this->key = $legacyEncryptionKey;
    }

    public function decrypt(?string $data): ?string
    {
        if ($data === null) {
            return null;
        }

        $decodedData = base64_decode($data, true);
        if ($decodedData === false) {
            // Not a valid base64 string
            return null;
        }

        $ivSize = openssl_cipher_iv_length(self::CIPHER_METHOD);
        if (strlen($decodedData) < $ivSize) {
            // Data is too short to contain an IV
            return null;
        }
        
        $iv = substr($decodedData, 0, $ivSize);
        $encrypted = substr($decodedData, $ivSize);

        $decrypted = openssl_decrypt($encrypted, self::CIPHER_METHOD, $this->key, 0, $iv);

        return $decrypted === false ? null : $decrypted;
    }
} 
