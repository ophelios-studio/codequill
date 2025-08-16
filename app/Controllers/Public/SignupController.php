<?php namespace Controllers\Public;

use Controllers\Controller;
use Pulsar\Account\Passport;
use Pulsar\Account\Services\AuthenticationService;
use Pulsar\Account\Services\UserService;
use Pulsar\OAuth\GitHub\Entities\GitHubUser;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
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

    #[Get("/signup-activation/{code}")]
    public function signupActivation(string $code): Response
    {
        $user = AuthenticationService::authenticateByActivationCode($code);
        if (is_null($user)) {
            Flash::error(localize("accounts.errors.activation_invalid"));
            return $this->redirect("/login");
        }
        AuthenticationService::activate($user);
        Flash::success(localize("accounts.success.activation", ['email' => $user->email]));
        return $this->redirect("/login");
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

    #[Get("/signup-github")]
    public function signupGitHubForm(): Response
    {
        $provider = Session::get('oauth_provider');
        if ($provider != 'github') {
            Flash::error(localize("accounts.errors.invalid_provider"));
            return $this->redirect("/login");
        }
        $user = GitHubUser::build(Session::get('oauth_user'));
        return $this->render("public/signup-github", [
            'user' => $user
        ]);
    }

    #[Post("/signup-github")]
    public function signupGitHub(): Response
    {
        $provider = Session::get('oauth_provider');
        if ($provider != 'github') {
            Flash::error(localize("accounts.errors.invalid_provider"));
            return $this->redirect("/login");
        }
        $gitHubUser = GitHubUser::build(Session::get('oauth_user'));
        $token = Session::get('oauth_access_token');
        $user = UserService::signupByGitHub($this->buildForm(), $gitHubUser->getRawData(), $token);
        Flash::success(localize("accounts.success.signup_github", ['fullname' => $user->fullname]));
        UserService::updateLastConnection($user->id);
        Passport::registerUser($user);
        Session::remove('oauth_provider');
        Session::remove('oauth_user');
        Session::remove('oauth_access_token');
        return $this->redirect("/app/profile");
    }
}
