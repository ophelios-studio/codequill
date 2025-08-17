<?php namespace Models\Account\Services;

use kornrunner\Keccak;
use SWeb3\Accounts;
use Web3\Contract;
use Web3\Web3;
use Web3p\EthereumTx\Transaction;
use Zephyrus\Core\Configuration;

class SnapshotService
{
    public const string CONTRACT_ADDRESS = '0x4Ba01aCD794E250be190fa3783A2475ABd4962f6';

    public function getSnapshots(string $gitHubRepositoryId): array
    {
        $repoIdHex = $this->repoIdFromGithubId($gitHubRepositoryId);
        $config = Configuration::read('services')['infura'];
        $rpc   = $config['polygon_url'];
        $abi   = file_get_contents(ROOT_DIR . '/web3/snapshot_abi.json');
        $web3      = new Web3($rpc);
        $contract  = new Contract($web3->getProvider(), $abi);

        $count = 0;
        $contract->at(self::CONTRACT_ADDRESS)->call('getSnapshotsCount', $repoIdHex, function($err,$res) use (&$count) {
            if ($err) throw $err;
            // web3p returns hex for uint sometimes; handle both
            $v = $res[0];
            $count = is_string($v) ? hexdec($v) : (int)$v;
        });

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $snap = null;
            $contract->at(self::CONTRACT_ADDRESS)->call('getSnapshot', $repoIdHex, $i, function($err,$res) use (&$snap) {
                if ($err) throw $err;
                // tuple: [commitHash, totalHash, ipfsCid, timestamp, author, index]
                $snap = [
                    'commitHash' => (string)$res[0],
                    'totalHash'  => (string)$res[1],
                    'ipfsCid'    => (string)$res[2],
                    'timestamp'  => is_string($res[3]) ? hexdec($res[3]) : (int)$res[3],
                    'author'     => (string)$res[4],
                    'index'      => is_string($res[5]) ? hexdec($res[5]) : (int)$res[5],
                ];
            });
            $out[] = $snap;
        }
        return $out;
    }

    public function snapshot(string $gitHubRepositoryId,
            string $authorWallet,       // wallet that owns the repo in Registry
            string $totalHashHex,       // 0x + 64 hex (required)
            ?string $commitHashHex = null,      // 0x + 64 hex (or ''/0 if N/A)
            string $ipfsCid = ""
        ): string {

        $config = Configuration::read('services')['infura'];
        $rpc   = $config['polygon_url'];
        $abi   = file_get_contents(ROOT_DIR . '/web3/snapshot_abi.json');

        $web3      = new Web3($rpc);
        $contract  = new Contract($web3->getProvider(), $abi);
        $privateKey  = Configuration::read('services')['mint']['private_key'];

        $account2 = Accounts::privateKeyToAccount($privateKey);
        $from = $account2->address;

        // inputs
        $repoId     = $this->repoIdFromGithubId($gitHubRepositoryId);
        $commitHash = $commitHashHex ? $this->toBytes32($commitHashHex) : $this->toBytes32('0x0');
        $totalHash  = $this->toBytes32($totalHashHex);
        $author     = $authorWallet;

        // 1) calldata
        $methodName = 'snapRepoFor';
        /**
         * bytes32 repoId,
         * bytes32 commitHash,
         * bytes32 totalHash,
         * string calldata ipfsCid,
         * address author
         */
        $data = $contract->at(self::CONTRACT_ADDRESS)->getData($methodName, $repoId, $commitHash, $totalHash, $ipfsCid, $author);

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


        $data = preg_replace('/^0x/i', '', $data);
        $transaction = new Transaction([
            'nonce'    => $nonce,
            'to'       => self::CONTRACT_ADDRESS,
            'gas'      => '0x493e0',
            'gasPrice' => $gasPrice,
            'value'    => '0x0',
            'data'     => '0x' . $data,
            'chainId'  => 137,
        ]);
        $signed = '0x' . $transaction->sign($account2->privateKey);

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
