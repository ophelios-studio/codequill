<?php namespace Controllers\Public;

use Controllers\Controller;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class TermsController extends Controller
{
    #[Get("/terms")]
    public function index(): Response
    {
        return $this->render("public/terms");
    }
}
