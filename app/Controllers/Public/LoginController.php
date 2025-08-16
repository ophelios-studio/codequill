<?php namespace Controllers\Public;

use Controllers\Controller;
use Pulsar\Account\Authenticator;
use Pulsar\Account\Exceptions\AuthenticationException;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;

class LoginController extends Controller
{
    #[Get("/login")]
    public function index(): Response
    {
        return $this->render("public/login");
    }

    #[Post("/")]
    public function login(): Response
    {
        try {
            new Authenticator()->login();
        } catch (AuthenticationException $e) {
            Flash::error($e->getUserMessage());
            return $this->redirect("/login");
        }
        return $this->redirect("/app");
    }
}
