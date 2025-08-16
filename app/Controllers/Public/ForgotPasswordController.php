<?php namespace Controllers\Public;

use Controllers\Controller;
use Pulsar\Account\Services\UserService;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;

class ForgotPasswordController extends Controller
{
    #[Get("/forgot-password")]
    public function index(): Response
    {
        return $this->render("public/forgot-password");
    }

    #[Post("/forgot-password")]
    public function forgotPassword(): Response
    {
        UserService::resetPassword($this->buildForm());
        Flash::success(localize("accounts.success.reset_password"));
        return $this->redirect("/");
    }
}
