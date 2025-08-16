<?php namespace Models\Account\Brokers;

use Models\Account\Entities\Wallet;
use Models\Core\Broker;
use stdClass;
use Models\Account\Services\ENSResolver;
use Zephyrus\Application\Configuration;
use Zephyrus\Security\Cryptography;

class WalletBroker extends Broker
{
    public function storeWalletConnection(string $address, int $userId): void
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

        $filename = null;
        if ($ensData['avatar']) {
            $filename = Cryptography::randomString(32) . '.png';
            $this->downloadEnsAvatar($ensData['avatar'], $filename);
        }

        $this->query($sql, [
            'address' => strtolower($address),
            'user_id' => $userId,
            'ens_name' => $ensData['name'],
            'ens_avatar' => $filename,
            'ens_data' => json_encode([
                'avatar' => $ensData['avatar'],
                'twitter' => $ensData['twitter'],
                'github' => $ensData['github'],
                'url' => $ensData['url']
            ])
        ]);
    }

    public function findWalletByAddress(string $address): ?stdClass
    {
        $sql = "SELECT * FROM wallet WHERE address = ?";
        return $this->selectSingle($sql, [strtolower($address)]);
    }

    public function findWalletById(int $walletId): ?stdClass
    {
        $sql = "SELECT * FROM wallet WHERE id = ?";
        return $this->selectSingle($sql, [$walletId]);
    }

    public function findWalletByUserId(int $user_id): ?stdClass
    {
        $sql = "SELECT * FROM wallet WHERE user_id = ?";
        return $this->selectSingle($sql, [$user_id]);
    }

    public function refreshENSData(Wallet $wallet): void
    {
        $ensResolver = $this->buildResolver();
        $ensData = $ensResolver->getENSData($wallet->address);
        $sql = "UPDATE wallet 
                SET ens_name = :ens_name,
                    ens_avatar = :ens_avatar,
                    ens_data = :ens_data
                WHERE address = :address";

        $filename = $wallet->ens_avatar;
        if ($ensData['avatar']) {
            if ($wallet->ens_data->ens_avatar !== $ensData['avatar']) {
                unlink(ROOT_DIR . '/public/assets/images/avatars/' . $wallet->ens_avatar);
                $filename = Cryptography::randomString(32) . '.png';
                $this->downloadEnsAvatar($ensData['avatar'], $filename);
            }
        }

        $this->query($sql, [
            'address' => strtolower($wallet->address),
            'ens_name' => $ensData['name'],
            'ens_avatar' => $filename,
            'ens_data' => json_encode([
                'ens_avatar' => $ensData['avatar'],
                'twitter' => $ensData['twitter'],
                'github' => $ensData['github'],
                'url' => $ensData['url']
            ])
        ]);
    }

    public function disconnectWallet(Wallet $wallet): void
    {
        if ($wallet->ens_avatar) {
            unlink(ROOT_DIR . '/public/assets/images/avatars/' . $wallet->ens_avatar);
        }
        $sql = "DELETE FROM wallet WHERE address = ? AND user_id = ?";
        $this->query($sql, [strtolower($wallet->address), $wallet->user_id]);
    }

    private function buildResolver(): ENSResolver
    {
        $config = Configuration::read('services')['infura'];
        return new ENSResolver($config['url']);
    }

    private function downloadEnsAvatar(string $avatarUrl, string $filename): bool
    {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: MyApp/1.0\r\n",
                'timeout' => 10 // Timeout after 10 seconds if the download fails
            ]
        ]);
        $avatarContent = file_get_contents($avatarUrl, false, $context);
        if ($avatarContent === false) {
            return false;
        }
        $saved = file_put_contents(ROOT_DIR . '/public/assets/images/avatars/' . $filename, $avatarContent);
        return $saved !== false;
    }
}