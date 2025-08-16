<?php namespace Controllers\Application\Profile;

use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Root;

#[Root("/wallet")]
class WalletController extends ProfileController
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("application/profile/wallet");
    }
}
