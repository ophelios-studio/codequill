<?php namespace Models\Account\Services;

use SWeb3\Accounts;
use Web3\Contract;
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

    public function generate(string $repoName, string $ownerName): string
    {
        $imageId = $this->generateImage();
        sleep(5);
        $service = new TuskyService();
        $fileInfo = $service->getFile($imageId);
        $blobId = $fileInfo['blobId'] ?? null;
        $tokenURI = "https://walrus.tusky.io/$blobId";
        $metadataId = $this->generateMetadata($tokenURI, $repoName, $ownerName);
        sleep(5);
        return $this->mint($metadataId);
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

    public function mint(string $metadataId): string
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

        // Encode function call
        $methodName = 'mintNFT';
        $data = $contract->getData($methodName, $toAddress, $tokenURI);

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
        // Build tx
        $tx = [
            'nonce'    => $nonce,
            'to'       => $contractAddr,
            'gas'      => '0x493e0', // 300k as buffer; or estimateGas
            'gasPrice' => $gasPrice,
            'value'    => '0x0',
            'data'     => '0x' . $data,
            'chainId'  => 137
        ];

        $transaction = new Transaction($tx);
        $signed = '0x' . $transaction->sign($account2->privateKey);

        // Send raw tx
        $txHash = null;
        $web3->getEth()->sendRawTransaction($signed, function ($err, $result) use (&$txHash) {
            if ($err) throw new \RuntimeException("Broadcast error: ".$err->getMessage());
            $txHash = $result;
        });
        return $txHash;
    }
}
