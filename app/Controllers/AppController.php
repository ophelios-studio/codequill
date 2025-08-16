<?php namespace Controllers;

use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class AppController extends Controller
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("welcome");
    }
}
