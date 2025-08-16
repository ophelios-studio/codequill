<?php namespace Models\Account\Services;

use GuzzleHttp\Client;
use stdClass;
use Zephyrus\Core\Cache\ApcuCache;
use Zephyrus\Core\Configuration;

class TokenService
{
    private string $apiKey;
    private ApcuCache $cache;

    public function __construct()
    {
        $config = Configuration::read('services')['moralis'];
        $this->apiKey = $config['api_key'];
        $this->cache = new ApcuCache();
    }

    public function getEth(): stdClass
    {
        if ($this->cache->has("eth_price")) {
            return $this->cache->get("eth_price");
        }
        $client = new Client();
        $response = $client->request('GET', 'https://deep-index.moralis.io/api/v2.2/erc20/0xC02aaA39b223FE8D0A0e5C4F27eAD9083C756Cc2/price?chain=eth&include=percent_change', [
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $this->apiKey,
            ],
        ]);
        $json = json_decode($response->getBody());
        $this->cache->set("eth_price", $json, 300);
        return $json;
    }

    public function getBooe(): stdClass
    {
        if ($this->cache->has("booe_price")) {
            return $this->cache->get("booe_price");
        }
        $client = new Client();
        $response = $client->request('GET', 'https://deep-index.moralis.io/api/v2.2/erc20/0x289Ff00235D2b98b0145ff5D4435d3e92f9540a6/price?chain=eth&include=percent_change', [
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $this->apiKey,
            ],
        ]);
        $json = json_decode($response->getBody());
        $this->cache->set("booe_price", $json, 300);
        return $json;
    }
}
