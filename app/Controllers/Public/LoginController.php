<?php namespace Controllers\Public;

use Controllers\Controller;
use Pulsar\Account\Authenticator;
use Pulsar\Account\Exceptions\AuthenticationException;
use Pulsar\Account\Exceptions\AuthenticationMfaException;
use Pulsar\Account\Exceptions\AuthenticationPasswordCompromisedException;
use Pulsar\Account\Exceptions\AuthenticationPasswordResetException;
use Pulsar\Account\MultiFactor;
use Pulsar\Account\Passport;
use Pulsar\Account\Services\RememberTokenService;
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
            return $this->redirect("/app/profile");
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

        if ($view === 'mfa') {
            $state = $this->request->getParameter('state');
            if (is_null($state) || Session::get('mfa_state') !== $state) {
                Session::destroy();
                return $this->redirect("/login");
            }
            return $this->render("public/mfa");
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
        } catch (AuthenticationMfaException $e) {
            return $this->redirect("/login?view=mfa&state=" . $e->getState());
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

    #[Post("/mfa")]
    public function mfa(): Response
    {
        $username = Session::get('mfa_username');
        $remember = Session::get('mfa_remember');
        $type = Session::get('mfa_type');
        $user = UserService::readByUsername($username);
        $graceEnabled = !is_null($this->request->getParameter('grace'));
        $code = $this->request->getParameter('code', '');
        if (is_array($code)) {
            $code = implode('', $code);
        }

        if (is_null($user)) {
            return $this->redirect("/");
        }

        $mfa = new MultiFactor($user);

        if ($mfa->hasExpired()) {
            Flash::error(localize("accounts.errors.mfa_expired"));
            return $this->redirect("/login");
        }

        $success = match ($type) {
            'email' => $mfa->verifyEmailCode($code),
            'otp' => $mfa->verifyAuthenticatorCode($code)
        };

        if (!$success) {
            Flash::error(localize("accounts.errors.mfa_invalid"));
            return $this->redirect("/login?view=mfa&state=" . Session::get('mfa_state'));
        }

        if ($graceEnabled) {
            $mfa->activateGraceTime();
        }

        UserService::updateLastConnection($user->id);
        Passport::registerUser($user);
        if ($remember) {
            RememberTokenService::remember($user);
        }
        return $this->redirect("/app/profile");
    }
}
