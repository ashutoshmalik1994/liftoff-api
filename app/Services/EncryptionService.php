<?php

namespace App\Services;

class EncryptionService
{
    protected $cipher = 'AES-256-CBC';
    protected $key;
    protected $iv;

    public function __construct()
    {
        $this->key = substr(hash('sha256', config('app.key')), 0, 32);
        $this->iv = substr(hash('sha256', 'your-custom-iv'), 0, 16);
    }

    public function encrypt($data)
    {
        return base64_encode(openssl_encrypt(json_encode($data), $this->cipher, $this->key, 0, $this->iv));
    }

    public function decrypt($encryptedData)
    {
        return json_decode(openssl_decrypt(base64_decode($encryptedData), $this->cipher, $this->key, 0, $this->iv), true);
    }
}
