<?php namespace Controllers\Application\Repository;

use Models\Account\Services\NftService;
use Models\Account\Services\WalletService;
use Pulsar\Account\Passport;
use Pulsar\OAuth\GitHub\GitHubService;
use Tracy\Debugger;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;

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

    #[Get("/{org}/repositories/{repository}")]
    public function read(string $organizationLogin, string $repositoryName): Response
    {
        $token = Passport::getUser()->authentication->oauth_access_token;
        $service = new GitHubService($token);
        if ($organizationLogin != "me") {
            $repository = $service->getRepository($organizationLogin . "/" . $repositoryName);
        } else {
            $repository = $service->getRepository($repositoryName);
        }

        if (is_null($repository)) {
            Flash::warning("No repository found for this organization.");
            return $this->redirect($this->getRouteRoot());
        }
        Debugger::barDump($repository);
        return $this->render("application/repository/read", [
            'repository' => $repository,
            'organization' => (object) $repository->getRawData()->organization
        ]);
    }

    #[Post("/{org}/repositories/{repository}/claim")]
    public function claim(string $organizationLogin, string $repositoryName): Response
    {




        $wallet = new WalletService()->getConnectedWallet(Passport::getUserId());
        if (is_null($wallet)) {
            Flash::warning("No wallet connected.");
            return $this->redirect($this->getRouteRoot());
        }

        $token = Passport::getUser()->authentication->oauth_access_token;
        $service = new GitHubService($token);
        $githubUser = $service->getUser();
        if ($organizationLogin != "me") {
            $repository = $service->getRepository($organizationLogin . "/" . $repositoryName);
        } else {
            $repository = $service->getRepository($repositoryName);
        }

        if (is_null($repository)) {
            Flash::warning("No repository found for this organization.");
            return $this->redirect($this->getRouteRoot());
        }

        new NftService("0x28bDeBA50ae38D29D99FeEc528c6169CED4B8560")
            ->generate($repository->full_name, $githubUser->login);
        return $this->html("Claimed repository");
    }
}
