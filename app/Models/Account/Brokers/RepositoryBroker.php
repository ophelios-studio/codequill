<?php namespace Models\Account\Brokers;

use Models\Core\Broker;

class RepositoryBroker extends Broker
{
    public function claim(int $githubId, string $name, string $address, int $userId): void
    {
        $sql = "INSERT INTO repository_claim (github_id, name, wallet_address, user_id) VALUES (?, ?, ?, ?)";
        $this->query($sql, [
            $githubId,
            $name,
            strtolower($address),
            $userId
        ]);
    }
}