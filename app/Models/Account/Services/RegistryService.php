<?php namespace Models\Account\Services;

use kornrunner\Keccak;
use Models\Account\Entities\Wallet;
use Pulsar\OAuth\GitHub\Entities\GitHubRepository;
use Tracy\Debugger;
use Web3\Contract;
use Web3\Web3;
use Zephyrus\Core\Configuration;

class RegistryService
{
    // bytes32 hex helper: 0x + 64 hex chars
    public function toBytes32(string $hexNo0x): string
    {
        $hex = strtolower($hexNo0x);
        $hex = preg_replace('/^0x/i', '', $hex);
        return '0x' . str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    // Build a stable repoId
    public function repoIdFromGithubId(string $githubNumericId): string
    {
        $hash = Keccak::hash($githubNumericId, 256); // 64 hex chars
        return $this->toBytes32($hash);
    }

    // If null = no owner
    public function getOwner(GitHubRepository $repository): ?string
    {
        $config = Configuration::read('services')['infura'];
        $rpc   = $config['eth_url'];
        $abi   = file_get_contents(ROOT_DIR . '/web3/registry_abi.json');
        $contractAddress  = '0xbC6C15A2A878300Ac49d51Fc4AA460a4AaF7dc90';

        $web3      = new Web3($rpc);
        $contract  = new Contract($web3->getProvider(), $abi);

        $repoId = $this->repoIdFromGithubId($repository->id);
        $claimed = false;
        $contract->at($contractAddress)->call('isClaimed', $repoId, function($err, $res) use (&$claimed) {
            if ($err !== null) {
                throw $err; // or handle error
            }
            $claimed = $res[0];
        });

        $owner = null;
        if ($claimed) {
            $contract->at($contractAddress)->call('repoOwner', $repoId, function ($err, $res) use (&$owner) {
                if ($err !== null) { throw $err; }
                $owner = $res[0];
            });
        }

        return $owner;
    }

    public function claim(GitHubRepository $repository, Wallet $wallet): ?string
    {
        $config = Configuration::read('services')['infura'];
        $rpc   = $config['eth_url'];
        $abi   = file_get_contents(ROOT_DIR . '/web3/registry_abi.json');
        $contractAddress  = '0xbC6C15A2A878300Ac49d51Fc4AA460a4AaF7dc90';

        $web3      = new Web3($rpc);
        $contract  = new Contract($web3->getProvider(), $abi);

        $githubId    = $repository->id;
        $repoId      = $this->repoIdFromGithubId($githubId);
        $repoMeta    = $repository->html_url;
        $userWallet  = $wallet->address;

        $relayerAddr = '0x846a07aa7577440174Fe89B82130D836389b1b81';
        $privateKey  = Configuration::read('services')['mint']['private_key'];
        $options = [
            'from'       => $relayerAddr,
            // If you prefer explicit gas limits:
            'gas'        => '0x249F0',      // ~150,000 (more than enough here; tune as needed)
            'gasPrice'   => '0x3b9aca00',   // 1 gwei (legacy); OK on many RPCs
            'value'      => '0x0',
            'privateKey' => $privateKey,
        ];

        $txHash = null;
        $contract->at($contractAddress)->send(
            'claimRepoFor',
            $repoId,           // bytes32
            $repoMeta,         // string
            $userWallet,       // address (the actual owner!)
            $options,
            function ($err, $tx) use (&$txHash) {
                if ($err !== null) {
                    error_log('claimRepoFor error: ' . (is_object($err) ? $err->getMessage() : $err));
                    return;
                }
                $txHash = $tx;
            }
        );
        return $txHash;
    }

}
