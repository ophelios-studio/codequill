<?php namespace Controllers\Application;

use Models\Account\Services\WalletService;
use Pulsar\Account\Passport;
use Tracy\Debugger;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Root;

#[Root("/app/api")]
class ApiController extends AppController
{
    #[Post('/wallet/connect')]
    public function connect(): Response
    {
        $address = $_POST['address'] ?? null;
        if (!$address) {
            Debugger::log('No address provided in POST data');
            return $this->json(['error' => 'No address provided']);
        }

        try {
            $walletService = new WalletService();

            // Check if user already has a connected wallet
            $existingWallet = $walletService->getConnectedWallet(Passport::getUserId());
            if ($existingWallet) {
                Debugger::log('User already has wallet connected: ' . $existingWallet->address);
                if ($existingWallet->address !== strtolower($address)) {
                    // If trying to connect a different wallet, return error
                    return $this->json([
                        'error' => 'Another wallet is already connected',
                        'message' => 'Please disconnect your current wallet first'
                    ]);
                }
                // If same wallet, just return its data
                return $this->json([
                    'success' => true,
                    'ens_name' => $existingWallet->ens_name ?? null,
                    'ens_avatar' => $existingWallet->ens_avatar ?? null,
                    'ens_data' => $existingWallet->ens_data ?? null
                ]);
            }

            // Connect new wallet
            $walletService->handleConnect($address, Passport::getUserId());

            // Get the wallet data to return
            $wallet = $walletService->getConnectedWallet(Passport::getUserId());
            Flash::success('Wallet connected successfully with address: ' . $wallet->address . ' 🎉!');
            return $this->json([
                'success' => true,
                'ens_name' => $wallet->ens_name ?? null,
                'ens_avatar' => $wallet->ens_avatar ?? null,
                'ens_data' => $wallet->ens_data ?? null
            ]);
        } catch (\Exception $e) {
            Debugger::log('Failed to connect wallet: ' . $e->getMessage());
            return $this->json([
                'error' => 'Failed to connect wallet',
                'message' => $e->getMessage()
            ]);
        }
    }

    #[Post('/wallet/refresh-ens')]
    public function refreshENS(): Response
    {
        try {
            $walletService = new WalletService();
            $wallet = $walletService->getConnectedWallet(Passport::getUserId());

            if (!$wallet) {
                return $this->json(['error' => 'No wallet connected']);
            }

            $walletService->refreshENS($wallet);

            // Get updated wallet data
            $updatedWallet = $walletService->getConnectedWallet(Passport::getUserId());
            Flash::success('Wallet refreshed successfully 🎉!');
            return $this->json([
                'success' => true,
                'ens_name' => $updatedWallet->ens_name,
                'ens_avatar' => $updatedWallet->ens_avatar,
                'ens_data' => $updatedWallet->ens_data
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to refresh ENS data']);
        }
    }

    #[Post('/wallet/disconnect')]
    public function disconnect(): Response
    {
        try {
            $walletService = new WalletService();
            $wallet = $walletService->getConnectedWallet(Passport::getUserId());
            if (!$wallet) {
                return $this->json(['error' => 'No wallet connected']);
            }
            $walletService->disconnect($wallet->address);
            Flash::success("Wallet disconnected successfully 🎉!");
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            return $this->json(['error' => 'Failed to disconnect wallet']);
        }
    }
}