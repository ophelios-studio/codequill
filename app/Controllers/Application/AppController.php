<?php namespace Controllers\Application;

use Controllers\Controller;
use Models\Account\Services\TokenService;
use Models\Account\Services\WalletService;
use Pulsar\Account\Passport;
use Pulsar\OAuth\GitHub\GitHubService;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Authorize;

#[Authorize("authenticated")]
abstract class AppController extends Controller
{
    public function render(string $page, array $args = []): Response
    {
        $user = null;
        if (Passport::getUser()->authentication->oauth_provider) {
            $user = new GitHubService(Passport::getUser()->authentication->oauth_access_token)->getUser();
        }
        $args = array_merge($args, [
            'user' => Passport::getUser(),
            'wallet' => new WalletService()->getConnectedWallet(Passport::getUserId()),
            'prices' => $this->getPrices(),
            'github_user' => $user
        ]);
        return parent::render($page, $args);
    }

    private function getPrices(): array
    {
        $priceService = new TokenService();
        $eth = $priceService->getEth();
        $booe = $priceService->getBooe();
        return [
            'eth' => $eth->usdPrice,
            'booe' => $booe->usdPrice,
        ];
    }
}
