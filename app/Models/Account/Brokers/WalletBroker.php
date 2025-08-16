<?php namespace Models\Account\Brokers;

use Models\Core\Broker;
use stdClass;
use Models\Account\Services\ENSResolver;
use Zephyrus\Application\Configuration;

class WalletBroker extends Broker
{
    public function storeWalletConnection(string $address, int $user_id): void
    {
        $ensResolver = $this->buildResolver();
        $ensData = $ensResolver->getENSData($address);

        $sql = "INSERT INTO wallet (address, user_id, ens_name, ens_avatar, ens_data, created_at) 
                VALUES (:address, :user_id, :ens_name, :ens_avatar, :ens_data, CURRENT_TIMESTAMP)
                ON CONFLICT (address) 
                DO UPDATE SET 
                    user_id = :user_id,
                    ens_name = :ens_name,
                    ens_avatar = :ens_avatar,
                    ens_data = :ens_data";

        $this->query($sql, [
            'address' => strtolower($address),
            'user_id' => $user_id,
            'ens_name' => $ensData['name'],
            'ens_avatar' => $ensData['avatar'],
            'ens_data' => json_encode([
                'twitter' => $ensData['twitter'],
                'github' => $ensData['github'],
                'url' => $ensData['url']
            ])
        ]);
    }

    public function getWallet(string $address): ?stdClass
    {
        $sql = "SELECT * FROM wallet WHERE address = ?";
        return $this->selectSingle($sql, [strtolower($address)]);
    }

    public function getWalletByUserId(int $user_id): ?stdClass
    {
        $sql = "SELECT * FROM wallet WHERE user_id = ?";
        return $this->selectSingle($sql, [$user_id]);
    }

    public function refreshENSData(string $address): void
    {
        $ensResolver = $this->buildResolver();
        $ensData = $ensResolver->getENSData($address);

        $sql = "UPDATE wallet 
                SET ens_name = :ens_name,
                    ens_avatar = :ens_avatar,
                    ens_data = :ens_data
                WHERE address = :address";

        $this->query($sql, [
            'address' => strtolower($address),
            'ens_name' => $ensData['name'],
            'ens_avatar' => $ensData['avatar'],
            'ens_data' => json_encode([
                'twitter' => $ensData['twitter'],
                'github' => $ensData['github'],
                'url' => $ensData['url']
            ])
        ]);
    }

    public function disconnectWallet(string $address, int $userId): void
    {
        $sql = "DELETE FROM wallet WHERE address = ? AND user_id = ?";
        $this->query($sql, [strtolower($address), $userId]);
    }

    private function buildResolver(): ENSResolver
    {
        $config = Configuration::read('services')['infura'];
        return new ENSResolver($config['url']);
    }
}