<?php namespace Models\Account\Services;

use kornrunner\Keccak;
use phpseclib\Math\BigInteger;
use SWeb3\Accounts;
use Tracy\Debugger;
use Web3\Contract;
use Web3\Web3;
use Web3p\EthereumTx\Transaction;
use Zephyrus\Core\Configuration;

class SnapshotService
{
    public const string CONTRACT_ADDRESS = '0xEAa4F3B10c8ee2C15F0184A181fd298aD5B735df';

    private function pick(array $res, string $name, int $fallbackIndex) {
        return $res[$name] ?? ($res[$fallbackIndex] ?? null);
    }

    public function getSnapshots(string $gitHubRepositoryId): array
    {
        $repoIdHex = $this->repoIdFromGithubId($gitHubRepositoryId);

        $rpc  = Configuration::read('services')['infura']['polygon_url']; // or your chain RPC
        $abi  = file_get_contents(ROOT_DIR . '/web3/snapshot_abi.json');

        $web3 = new Web3($rpc);
        $c    = new Contract($web3->getProvider(), $abi);
        $c->at(self::CONTRACT_ADDRESS);

        // 1) count
        $count = 0;
        $c->call('getSnapshotsCount', $repoIdHex, function($err, $res) use (&$count) {
            if ($err) throw $err;
            $v = $res[0];
            if ($v instanceof \phpseclib\Math\BigInteger) {
                $count = (int)$v->toString();
            } elseif (is_string($v) && str_starts_with(strtolower($v),'0x')) {
                $count = hexdec($v);
            } else {
                $count = (int)$v;
            }
        });
        Debugger::barDump($count);

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $snap = null;

            // IMPORTANT: pass uint256 index as BigInteger (or '0x...' string), not a PHP int
            $indexArg = new BigInteger((string)$i);

            // 2a) fixed-size fields
            $fixed = null;
            $c->call('getSnapshotFixed', $repoIdHex, $indexArg, function($err, $res) use (&$fixed) {
                if ($err) throw $err;

                // web3p may return an associative array with named keys
                $commitHash = (string) ($this->pick($res, 'commitHash', 0) ?? '');
                $totalHash  = (string) ($this->pick($res, 'totalHash',  1) ?? '');
                $tsRaw      =           ($this->pick($res, 'timestamp',  2) ?? 0);
                $author     = (string) ($this->pick($res, 'author',     3) ?? '');
                $idxRaw     =           ($this->pick($res, 'idx',        4) ?? 0);

                $timestamp  = $this->normalizeBigInt($tsRaw);
                $index      = $this->normalizeBigInt($idxRaw);

                $fixed = (object) [
                    'commitHash' => $commitHash,     // e.g. "0x..." or "0" if you stored 0x0
                    'totalHash'  => $totalHash,      // "0x..."
                    'timestamp'  => $timestamp,      // int
                    'author'     => $author,         // "0x..."
                    'index'      => $index,          // int
                ];
            });

            // 2b) string (CID)
//            $cid = '';
//            $c->call('getSnapshotCid', $repoIdHex, $indexArg, function($err, $res) use (&$cid) {
//                if ($err) throw $err;
//                Debugger::barDump($res);
//                $cid = (string)$res[0];
//            });

            //$snap = $fixed + ['ipfsCid' => $cid];
            $out[] = $fixed;
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

    private function normalizeBigInt(mixed $v): string
    {
        if ($v instanceof BigInteger) return $v->toString(); // decimal string
        if (is_string($v) && str_starts_with(strtolower($v), '0x')) return (string)hexdec($v);
        return (string)$v;
    }
}
