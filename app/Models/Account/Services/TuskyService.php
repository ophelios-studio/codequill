<?php namespace Models\Account\Services;

use GuzzleHttp\Client;
use Tracy\Debugger;
use Zephyrus\Core\Configuration;

class TuskyService
{
    private const TUSKY_API = 'https://api.tusky.io';
    private string $apiKey;
    private Client $client;

    public function __construct()
    {
        $config = Configuration::read('services')['tusky'];
        $this->apiKey = $config['api_key'];
        $this->client = new Client([
            'base_uri' => self::TUSKY_API,
            'timeout' => 30.0,
            'http_errors' => false,
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    public function getFile(string $fileId): ?array
    {
        try {
            $response = $this->client->request('GET', "/files/{$fileId}", [
                'headers' => [
                    'Api-Key' => $this->apiKey,
                    'Accept' => 'application/json'
                ]
            ]);

            $status = $response->getStatusCode();
            if ($status !== 200) {
                throw new \RuntimeException("Tusky API returned status {$status}");
            }

            $body = json_decode($response->getBody()->getContents(), true);
            Debugger::barDump($body);
            return $body; // contains blobId and metadata
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to fetch file {$fileId}: " . $e->getMessage(), 0, $e);
        }
    }

    public function upload(string $path, string $vaultId): ?string
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $fileSize = filesize($path);
        $fileName = basename($path);

        try {
            $response = $this->client->request('POST', "/uploads?vaultId={$vaultId}", [
                'headers' => [
                    'Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => $fileSize,
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                ],
                'body' => fopen($path, 'r'),
            ]);

            // Tusky returns the upload location in headers
            $location = $response->getHeaderLine('location');
            if (!empty($location)) {
                return basename($location); // same as .split('/').pop()
            }
            return null;
        } catch (\Exception $e) {
            throw new \RuntimeException("Upload failed: " . $e->getMessage(), 0, $e);
        }
    }

}
