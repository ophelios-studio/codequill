<?php namespace Controllers\Application\Repository;

use Pulsar\Account\Passport;
use Pulsar\OAuth\GitHub\GitHubService;
use Tracy\Debugger;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class OrganizationController extends CodeBaseController
{
    #[Get("/")]
    public function index(): Response
    {
        $token = Passport::getUser()->authentication->oauth_access_token;
        $service = new GitHubService($token);
        $organizations = $service->getUserOrganizations();
        foreach ($organizations as &$organization) {
            $organization->repos = $service->getOrganizationRepositories($organization->login);
        }
        Debugger::barDump($organizations);
        return $this->render("application/repository/organization", [
            'organizations' => $organizations,
            'github_user' => $service->getUser(),
            'user_repos' => $service->getUserRepositories()
        ]);
    }
}
