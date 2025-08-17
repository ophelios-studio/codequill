<?php namespace Controllers\Application\Profile;

use Pulsar\Account\Passport;
use Pulsar\OAuth\GitHub\GitHubService;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Root;

#[Root("/github")]
class GithubController extends ProfileController
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("application/profile/github", [
            'github_user' => null
        ]);
    }
}
