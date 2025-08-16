<?php namespace Controllers\Public;

use Controllers\Controller;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class HomeController extends Controller
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("welcome");
    }

    #[Get("/health")]
    public function health(): Response
    {
        return $this->json(['status' => 'ok']);
    }
}
