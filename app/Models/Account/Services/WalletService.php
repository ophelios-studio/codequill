<?php namespace Models\Account\Services;

use Models\Account\Brokers\WalletBroker;
use Pulsar\Account\Passport;
use stdClass;

class WalletService
{
    private WalletBroker $walletBroker;

    public function __construct(WalletBroker $walletBroker)
    {
        $this->walletBroker = $walletBroker;
    }

    public function handleConnect(string $address, int $userId): void
    {
        $this->walletBroker->storeWalletConnection($address, $userId);
    }

    public function refreshENS(string $address): void
    {
        $this->walletBroker->refreshENSData($address);
    }

    public function disconnect(string $address): void
    {
        $this->walletBroker->disconnectWallet($address, Passport::getUserId());
    }

    public function getConnectedWallet(int $userId): ?stdClass
    {
        return $this->walletBroker->getWalletByUserId($userId);
    }
}