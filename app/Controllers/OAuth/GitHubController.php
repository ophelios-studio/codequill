<?php namespace Controllers\OAuth;

use Exception;
use Pulsar\Account\Passport;
use Pulsar\Account\Services\AuthenticationService;
use Pulsar\Account\Services\UserService;
use Pulsar\OAuth\GitHub\GitHubOauth;
use Pulsar\OAuth\GitHub\GitHubOauthConfiguration;
use Pulsar\OAuth\GitHub\GitHubService;
use Zephyrus\Application\Configuration;
use Zephyrus\Application\Controller;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Root;

#[Root("/oauth/github")]
class GitHubController extends Controller
{
    #[Get("/callback")]
    public function callback(): Response
    {
        $config = new GitHubOauthConfiguration(Configuration::read('services')['github']);
        $githubOAuth = new GitHubOauth($config);

        $code = $this->request->getParameter('code');
        if (is_null($code)) {
            Flash::error(localize("errors.github.no_code"));
            return $this->redirect('/login');
        }

        try {
            $token = $githubOAuth->getAccessToken($code);
            $service = new GitHubService($token);
        } catch (Exception $e) {
            Flash::error($e->getMessage());
            return $this->redirect('/login');
        }

        $githubUser = $service->getUser();
        $user = AuthenticationService::authenticateByOauth('github', $githubUser->id);

        if ($user) {
            UserService::updateLastConnection($user->id);
            Passport::registerUser($user);
            return $this->redirect("/app/profile");
        } else {
            Session::set('oauth_user', $githubUser->getRawData());
            Session::set('oauth_provider', "github");
            Session::set('oauth_access_token', $token);
            return $this->redirect('/signup-github');
        }
    }
}
