<?php

class Encryption
{
    private const CIPHER_METHOD = 'aes-256-cbc';

    public static function encrypt($data)
    {
        $key = Configuration::Instance()->GetKey('encryption.key');
        if (empty($key)) {
            throw new \RuntimeException('Encryption key is not configured.');
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER_METHOD));
        $encrypted = openssl_encrypt($data, self::CIPHER_METHOD, $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($data)
    {
        $key = Configuration::Instance()->GetKey('encryption.key');
        if (empty($key)) {
            throw new \RuntimeException('Encryption key is not configured.');
        }

        $data = base64_decode($data);
        $ivSize = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = substr($data, 0, $ivSize);
        $encrypted = substr($data, $ivSize);

        return openssl_decrypt($encrypted, self::CIPHER_METHOD, $key, 0, $iv);
    }
} 
