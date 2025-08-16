<?php namespace Controllers\Application;

use Controllers\Controller;
use Models\Account\Services\WalletService;
use Pulsar\Account\Passport;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Authorize;

#[Authorize("authenticated")]
abstract class AppController extends Controller
{
    public function render(string $page, array $args = []): Response
    {
        $args = array_merge($args, [
            'user' => Passport::getUser(),
            'wallet' => new WalletService()->getConnectedWallet(Passport::getUserId())
        ]);
        return parent::render($page, $args);
    }
}
