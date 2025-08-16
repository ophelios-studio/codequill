<?php namespace Models\Account\Services;

use Elliptic\EC;
use kornrunner\Keccak;
use SWeb3\Accounts;
use SWeb3\SWeb3;
use SWeb3\SWeb3_Contract;
use Tracy\Debugger;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Web3;
use Web3p\EthereumTx\Transaction;
use Zephyrus\Core\Configuration;

class NftService
{
    private string $receivingAddress;

    /**
     * @param string $receivingAddress
     */
    public function __construct(string $receivingAddress)
    {
        $this->receivingAddress = $receivingAddress;
    }

    public function generate(string $repoName, string $ownerName): void
    {
//        $imageId = $this->generateImage();
//        sleep(5);
//        $service = new TuskyService();
//        $fileInfo = $service->getFile($imageId);
//        $blobId = $fileInfo['blobId'] ?? null;
//        $tokenURI = "https://walrus.tusky.io/{$blobId}";
//        if (!$blobId) {
//            die("No blobId found for imageId: $imageId");
//        }
//        $metadataUrl = $this->generateMetadata($tokenURI, $repoName, $ownerName);
//        sleep(5);
        $this->mint();
    }

    private function generateImage(): string
    {
        $walrusService = new TuskyService();
        return $walrusService->upload(ROOT_DIR . '/web3/nft.png', '837ae68d-04e4-4952-a670-3a72f2759fb8');
    }

    private function generateMetadata(string $imageUrl, string $repoName, string $authorName): string
    {
        $walrusService = new TuskyService();
        $metaData = json_encode([
            'name' => "Code Quill Authorship: $repoName",
            'description' => "This NFT certifies that $authorName authored the repository $repoName.",
            'image' => $imageUrl,
        ], JSON_PRETTY_PRINT);
        file_put_contents(ROOT_DIR . '/web3/metadata.json', $metaData);
        return $walrusService->upload(ROOT_DIR . '/web3/metadata.json', '837ae68d-04e4-4952-a670-3a72f2759fb8');
    }

    function addressFromPrivateKey(string $privateKeyHex): string
    {
        $pk = preg_replace('/^0x/i', '', $privateKeyHex);
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($pk);

        // uncompressed public key: 0x04 || X || Y
        $pub = $key->getPublic(false, 'hex');
        $pubNoPrefix = substr($pub, 2); // drop 0x04

        // keccak256 and take last 20 bytes
        $hash = Keccak::hash(hex2bin($pubNoPrefix), 256);
        return '0x' . substr($hash, 24);
    }

    public function mint($metadataId = "Y0KbPrpHz4vbGtheIhTBAoktRtH4lyea5rSXtlivDYQ"): string
    {
        $cfg        = Configuration::read('services');
        $rpcUrl     = $cfg['infura']['polygon_url'];
        $mintCfg    = $cfg['mint'];
        $privateKey = $mintCfg['private_key'];
        $contractAddr = $mintCfg['contract_address'];
        $toAddress  = $this->receivingAddress;
        $abi        = json_decode(file_get_contents(ROOT_DIR . '/web3/contract_abi.json'), true);

        // tokenURI from storage
        $tokenURI = "https://walrus.tusky.io/$metadataId";


        $web3 = new Web3($rpcUrl);
        $contract = new Contract($web3->provider, $abi, $contractAddr);

        $account2 = Accounts::privateKeyToAccount($privateKey);
        Debugger::barDump($account2);

        // Encode function call
        $methodName = 'mintNFT';
        $data = $contract->getData($methodName, $toAddress, $tokenURI);

        // Nonce
        $nonce = null;
        $web3->getEth()->getTransactionCount('0x846a07aa7577440174Fe89B82130D836389b1b81', 'pending', function ($err, $result) use (&$nonce) {
            if ($err) throw new \RuntimeException("Nonce error: ".$err->getMessage());
            $nonce = '0x' . dechex($result->toString());
        });

        // Gas price
        $gasPrice = null;
        $web3->getEth()->gasPrice(function ($err, $result) use (&$gasPrice) {
            if ($err) throw new \RuntimeException("Gas price error: ".$err->getMessage());
            $gasPrice = '0x' . dechex($result->toString());
        });

        // Build tx
        $tx = [
            'nonce'    => $nonce,
            'to'       => $contractAddr,
            'gas'      => '0x493e0', // 300k as buffer; or estimateGas
            'gasPrice' => $gasPrice,
            'value'    => '0x0',
            'data'     => $data,
            'chainId'  => 137
        ];

        $transaction = new Transaction($tx);
        $signed = '0x' . $transaction->sign($privateKey);

        // Send raw tx
        $txHash = null;
        $web3->getEth()->sendRawTransaction($signed, function ($err, $result) use (&$txHash) {
            if ($err) throw new \RuntimeException("Broadcast error: ".$err->getMessage());
            $txHash = $result;
        });
        Debugger::barDump($txHash);

        return $txHash;
    }
}
