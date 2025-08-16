<?php namespace Controllers\Application\Repository;

use Pulsar\Account\Passport;
use Pulsar\OAuth\GitHub\GitHubService;
use Tracy\Debugger;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;

class RepositoryController extends CodeBaseController
{
    #[Get("/{org}/repositories")]
    public function index(string $organizationLogin): Response
    {
        $token = Passport::getUser()->authentication->oauth_access_token;
        $service = new GitHubService($token);

        $repositories = ($organizationLogin == "me")
            ? $service->getUserRepositories()
            : $service->getOrganizationRepositories($organizationLogin);

        if (empty($repositories)) {
            Flash::warning("No repository found for this organization.");
            return $this->redirect($this->getRouteRoot());
        }
        Debugger::barDump($repositories);
        return $this->render("application/repository/repositories", [
            'repositories' => $repositories,
            'organization' => ($organizationLogin != "me") ? $service->getOrganization($organizationLogin) : null,
            'github_user' => ($organizationLogin !== "me") ? $service->getUser() : null
        ]);
    }
}
