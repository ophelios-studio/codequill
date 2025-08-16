<?php namespace Models\Account\Services;

use Models\Account\Brokers\WalletBroker;
use Models\Account\Entities\Wallet;

class WalletService
{
    private WalletBroker $walletBroker;

    public function __construct()
    {
        $this->walletBroker = new WalletBroker();
    }

    public function read(int $walletId): ?Wallet
    {
        return Wallet::build($this->walletBroker->findWalletById($walletId));
    }

    public function readByAddress(string $address): ?Wallet
    {
        return Wallet::build($this->walletBroker->findWalletByAddress($address));
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
        $this->walletBroker->disconnectWallet($this->readByAddress($address));
    }

    public function getConnectedWallet(int $userId): ?Wallet
    {
        return Wallet::build($this->walletBroker->findWalletByUserId($userId));
    }
}