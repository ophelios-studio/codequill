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
        return $this->html("<h3>You have successfully logged in! More to come.</h3>");
    }
}
