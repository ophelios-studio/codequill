<?php namespace Models\Account\Services;

use CURLFile;
use Zephyrus\Core\Configuration;

class WalrusService
{
    private string $apiKey;

    public function __construct()
    {
        $config = Configuration::read('services')['walrus'];
        $this->apiKey = $config['api_key'];
    }

    public function upload(string $imagePath) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.walrus.ai/upload',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => new CURLFile($imagePath)],
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $this->apiKey"
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true)['url'] ?? null;
    }
}
