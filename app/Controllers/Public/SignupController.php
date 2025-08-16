<?php namespace Controllers\Public;

use Controllers\Controller;
use Pulsar\Account\Services\UserService;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Utilities\MaskFormat;

class SignupController extends Controller
{
    #[Get("/signup")]
    public function index(): Response
    {
        return $this->render("public/signup");
    }

    #[Post("/signup")]
    public function signup(): Response
    {
        $user = UserService::signup($this->buildForm());
        Flash::success(localize("accounts.success.signup", [
            'fullname' => $user->fullname,
            'email' => MaskFormat::email($user->email)]
        ));
        return $this->redirect("/login");
    }
}
