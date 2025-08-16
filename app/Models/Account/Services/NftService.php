<?php namespace Models\Account\Services;

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
        $imageId = $this->generateImage();
        sleep(5);
        $service = new TuskyService();
        $fileInfo = $service->getFile($imageId);
        Debugger::barDump($fileInfo);
        $blobId = $fileInfo['blobId'] ?? null;
        $tokenURI = "https://walrus.tusky.io/{$blobId}";
        if (!$blobId) {
            die("No blobId found for imageId: $imageId");
        }
        $metadataUrl = $this->generateMetadata($tokenURI, $repoName, $ownerName);
        sleep(5);
        $this->mint($metadataUrl);
    }

    public function generateImage(): string
    {
        $walrusService = new TuskyService();
        return $walrusService->upload(ROOT_DIR . '/web3/nft.png', '837ae68d-04e4-4952-a670-3a72f2759fb8');
    }

    public function generateMetadata(string $imageUrl, string $repoName, string $authorName): string
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

    public function mint(string $metadataId): void
    {
        Debugger::barDump($metadataId);
        $config = Configuration::read('services')['infura'];
        $rpcUrl = $config['polygon_url'];
        $web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 60)));

        $config = Configuration::read('services')['mint'];
        $privateKey = $config['private_key'];
        $fromAddress = '0x846a07aa7577440174Fe89B82130D836389b1b81';
        $toAddress = $this->receivingAddress;

        $web3->eth->getBalance($fromAddress, function ($err, $balance) {
            if ($err !== null) {
                Debugger::barDump("Error fetching balance: " . $err->getMessage());
            } else {
                Debugger::barDump("Balance in wei: " . $balance->toString());
            }
        });

        $service = new TuskyService();
        $fileInfo = $service->getFile($metadataId);
        $blobId = $fileInfo['blobId'] ?? null;
        Debugger::barDump($fileInfo);

        if ($blobId) {
            $tokenURI = "https://walrus.tusky.io/{$blobId}";
        } else {
            die("No blobId found for metadataId: $metadataId");
        }

        Debugger::barDump($tokenURI);

        $contractAddress = $config['contract_address'];
        $abi = json_decode(file_get_contents(ROOT_DIR . '/web3/contract_abi.json'), true);
        $contract = new Contract($web3->provider, $abi);

        $data = $contract->at($contractAddress)->getData('mintNFT', $toAddress, $tokenURI);

        // Get nonce synchronously
        $txNonce = null;
        $web3->eth->getTransactionCount($fromAddress, 'pending', function ($err, $nonce) use (&$txNonce) {
            if ($err !== null) {
                throw new \RuntimeException("Nonce error: " . $err->getMessage());
            }
            $txNonce = (int) $nonce->toString();
        });

        // Ensure nonce was set
        if ($txNonce === null) {
            throw new \RuntimeException("Failed to fetch nonce");
        }

        $gasPrice = 30 * (10 ** 9); // 30 gwei
        $gasLimit = 200000;

        // Build tx
        $tx = [
            'nonce'    => '0x' . dechex($txNonce),
            'from'     => $fromAddress,
            'to'       => $contractAddress,
            'gas'      => '0x' . dechex($gasLimit),
            'gasPrice' => '0x' . dechex($gasPrice),
            'value'    => '0x0',
            'data'     => $data,
            'chainId'  => 137 // or 80001 testnet
        ];

        $transaction = new Transaction($tx);
        $signedTx = '0x' . $transaction->sign($privateKey);

        $web3->eth->sendRawTransaction($signedTx, function ($err, $txHash) {
            if ($err !== null) {
                echo 'Error: ' . $err->getMessage();
            } else {
                echo "NFT Minted! TX Hash: $txHash\n";
            }
        });
    }
}
