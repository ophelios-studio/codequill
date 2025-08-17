<?php namespace Models\Account\Services;

use kornrunner\Keccak;
use Models\Account\Entities\Wallet;
use Pulsar\OAuth\GitHub\Entities\GitHubRepository;
use SWeb3\Accounts;
use Web3\Contract;
use Web3\Web3;
use Web3p\EthereumTx\Transaction;
use Zephyrus\Core\Configuration;

class RegistryService
{
    // If null = no owner
    public function getOwner(GitHubRepository $repository): ?string
    {
        $config = Configuration::read('services')['infura'];
        $rpc   = $config['polygon_url'];
        $abi   = file_get_contents(ROOT_DIR . '/web3/registry_abi.json');
        $contractAddress  = '0xa7239b6D1d714Eb6032076a073903F9b119f368C';

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
        $rpc   = $config['polygon_url'];
        $abi   = file_get_contents(ROOT_DIR . '/web3/registry_abi.json');
        $contractAddress  = '0xa7239b6D1d714Eb6032076a073903F9b119f368C';

        $web3      = new Web3($rpc);
        $contract  = new Contract($web3->getProvider(), $abi);

        $githubId    = $repository->id;
        $repoId      = $this->repoIdFromGithubId($githubId);
        $repoMeta    = $repository->html_url;
        $userWallet  = $wallet->address;

        $relayerAddr = '0x846a07aa7577440174Fe89B82130D836389b1b81';
        $privateKey  = Configuration::read('services')['mint']['private_key'];

        $account2 = Accounts::privateKeyToAccount($privateKey);

        // 1) Encode calldata
        $methodName = 'claimRepoFor';
        $data = $contract->getData($methodName, $repoId, $repoMeta, $userWallet);

        // Nonce
        $nonce = null;
        $web3->getEth()->getTransactionCount($account2->address, 'pending', function ($err, $result) use (&$nonce) {
            if ($err) throw new \RuntimeException("Nonce error: ".$err->getMessage());
            $nonce = '0x' . dechex($result->toString());
        });

        // Gas price
        $gasPrice = null;
        $web3->getEth()->gasPrice(function ($err, $result) use (&$gasPrice) {
            if ($err) throw new \RuntimeException("Gas price error: ".$err->getMessage());
            $gasPrice = '0x' . dechex($result->toString());
        });

        // 5) Build & sign legacy tx (simple and widely supported)
        $data = preg_replace('/^0x/i', '', $data);
        $transaction = new Transaction([
            'nonce'    => $nonce,
            'to'       => $contractAddress,
            'gas'      => '0x493e0',
            'gasPrice' => $gasPrice,
            'value'    => '0x0',
            'data'     => '0x' . $data,
            'chainId'  => 137,
        ]);

        $signed = '0x' . $transaction->sign($account2->privateKey);

        // 6) Send raw tx
        $txHash = null;
        $web3->getEth()->sendRawTransaction($signed, function ($err, $result) use (&$txHash) {
            if ($err) throw new \RuntimeException("Broadcast error: ".$err->getMessage());
            $txHash = $result;
        });
        return $txHash;
    }

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
}
