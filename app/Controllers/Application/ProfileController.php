<?php namespace Controllers\Application;

use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Root;

#[Root("/profile")]
class ProfileController extends AppController
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("application/profile/settings");
    }

    #[Get("/password")]
    public function changePasswordForm(): Response
    {
        return $this->render("application/profile/password");
    }
}
