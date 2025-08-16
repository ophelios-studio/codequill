<?php namespace Controllers\Public;

use Controllers\Controller;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class SignupController extends Controller
{
    #[Get("/signup")]
    public function index(): Response
    {
        return $this->render("public/signup");
    }
}
