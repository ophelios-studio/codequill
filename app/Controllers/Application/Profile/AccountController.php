<?php namespace Controllers\Application\Profile;

use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class AccountController extends ProfileController
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("application/profile/settings");
    }
}
