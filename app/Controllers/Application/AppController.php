<?php namespace Controllers\Application;

use Controllers\Controller;
use Models\Account\Services\PriceService;
use Models\Account\Services\TokenService;
use Models\Account\Services\WalletService;
use Pulsar\Account\Passport;
use Tracy\Debugger;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Authorize;

#[Authorize("authenticated")]
abstract class AppController extends Controller
{
    public function render(string $page, array $args = []): Response
    {
        $args = array_merge($args, [
            'user' => Passport::getUser(),
            'wallet' => new WalletService()->getConnectedWallet(Passport::getUserId()),
            'prices' => $this->getPrices()
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
