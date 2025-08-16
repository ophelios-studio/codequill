<?php namespace Controllers\Public;

use Controllers\Controller;
use Pulsar\Account\Authenticator;
use Pulsar\Account\Exceptions\AuthenticationException;
use Pulsar\Account\Exceptions\AuthenticationPasswordCompromisedException;
use Pulsar\Account\Exceptions\AuthenticationPasswordResetException;
use Pulsar\Account\Passport;
use Pulsar\Account\Services\UserService;
use Pulsar\OAuth\GitHub\GitHubOauth;
use Pulsar\OAuth\GitHub\GitHubOauthConfiguration;
use Zephyrus\Application\Configuration;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;

class LoginController extends Controller
{
    #[Get("/login")]
    public function index(): Response
    {
        if (Passport::isAuthenticated()) {
            return $this->redirect("/app");
        }

        $view = $this->request->getParameter('view');
        if ($view === 'reset-password') {
            $state = $this->request->getParameter('state');
            if (is_null($state) || Session::get('reset_password_state') !== $state) {
                Session::destroy();
                return $this->redirect("/login");
            }
            return $this->render("public/password-reset");
        }
        if ($view === 'breach-password') {
            $state = $this->request->getParameter('state');
            if (is_null($state) || Session::get('reset_password_state') !== $state) {
                Session::destroy();
                return $this->redirect("/login");
            }
            return $this->render("public/password-breached");
        }

        $config = new GitHubOauthConfiguration(Configuration::read('services')['github']);
        $githubOAuth = new GitHubOauth($config);
        $gitHubAuthorizeUrl = $githubOAuth->getAuthorizationUrl(['user', 'repo', 'read:org']);
        return $this->render("public/login", [
            "github_url" => $gitHubAuthorizeUrl
        ]);
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
        } catch (AuthenticationPasswordResetException $e) {
            return $this->redirect("/login?view=reset-password&state=" . $e->getState());
        } catch (AuthenticationPasswordCompromisedException $e) {
            return $this->redirect("/login?view=breach-password&state=" . $e->getState());
        } catch (AuthenticationException $e) {
            Flash::error($e->getUserMessage());
            return $this->redirect("/login");
        }
        return $this->redirect("/app/profile");
    }

    #[Post("/password-reset")]
    public function passwordReset(): Response
    {
        $username = Session::get('reset_password_username');
        $user = UserService::readByUsername($username);
        if (is_null($user)) {
            return $this->redirect("/login");
        }
        $user = UserService::updatePassword($user, $this->buildForm());
        Flash::success(localize("accounts.success.password_updated"));
        UserService::updateLastConnection($user->id);
        Passport::registerUser($user);
        return $this->redirect("/app/profile");
    }
}
