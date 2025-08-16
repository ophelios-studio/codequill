<?php namespace Models\Account\Entities;

use stdClass;
use Zephyrus\Core\Entity\Entity;

class Wallet extends Entity
{
    public int $id;
    public string $address;
    public ?string $ens_name;
    public ?string $ens_avatar;
    public ?stdClass $ens_data;
    public int $user_id;
    public string $created_at;

    public function getName(): string
    {
        return $this->ens_name ?? format('eth_address', $this->address);
    }

    public function getAvatar(): ?string
    {
        if (is_null($this->ens_avatar)) {
            return null;
        }
        return '/assets/images/avatars/' . $this->ens_avatar;
    }
}
