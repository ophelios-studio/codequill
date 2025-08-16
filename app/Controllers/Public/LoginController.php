<?php namespace Controllers\Public;

use Controllers\Controller;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class LoginController extends Controller
{
    #[Get("/login")]
    public function index(): Response
    {
        return $this->render("public/login");
    }
}
