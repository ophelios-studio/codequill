<?php namespace Models\Account\Entities;

use Zephyrus\Core\Entity\Entity;

class Wallet extends Entity
{
    public string $address;
    public int $user_id;
}
