<?php namespace Controllers\Public;

use Controllers\Controller;
use Pulsar\Account\Authenticator;
use Pulsar\Account\Exceptions\AuthenticationException;
use Pulsar\Account\Passport;
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

    #[Get("/logout")]
    public function logout(): Response
    {
        if (!Passport::isAuthenticated()) {
            return $this->redirect("/login");
        }
        new Authenticator()->logout();
        return $this->redirect("/login");
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
        return $this->redirect("/app/profile");
    }
}
