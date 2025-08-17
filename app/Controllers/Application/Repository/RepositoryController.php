<?php namespace Controllers\Application\Repository;

use Models\Account\Services\NftService;
use Models\Account\Services\RegistryService;
use Models\Account\Services\WalletService;
use Pulsar\Account\Passport;
use Pulsar\OAuth\GitHub\GitHubService;
use Tracy\Debugger;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
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

        $claimedRepoIds = [];
        $wallet = new WalletService()->getConnectedWallet(Passport::getUserId());
        if ($wallet) {
            $registryService = new RegistryService();
            $claimedRepos = $registryService->getClaimsByOwner($wallet->address);
            Debugger::barDump($claimedRepos);

            foreach ($repositories as $repository) {
                $id = $registryService->repoIdFromGithubId($repository->id);
                if (in_array($id, $claimedRepos)) {
                    $claimedRepoIds[] = $repository->id;
                }
            }
        }
        Debugger::barDump($claimedRepoIds);

        Debugger::barDump($repositories);
        return $this->render("application/repository/repositories", [
            'repositories' => $repositories,
            'organization' => ($organizationLogin != "me") ? $service->getOrganization($organizationLogin) : null,
            'github_user' => ($organizationLogin !== "me") ? $service->getUser() : null,
            'claimed_repo_ids' => $claimedRepoIds,
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

        $registryService = new RegistryService();
        $ownerAddress = $registryService->getOwner($repository);

        if (is_null($repository)) {
            Flash::warning("No repository found for this organization.");
            return $this->redirect($this->getRouteRoot());
        }
        Debugger::barDump($repository);
        $confetti = Session::get("confetti", false);
        if ($confetti) {
            Session::remove("confetti");
        }
        return $this->render("application/repository/read", [
            'repository' => $repository,
            'organization' => (object) $repository->getRawData()->organization,
            'confetti' => $confetti,
            'owner_address' => $ownerAddress,
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

        if ($organizationLogin != "me") {
            $repository = $service->getRepository($organizationLogin . "/" . $repositoryName);
        } else {
            $repository = $service->getRepository($repositoryName);
        }

        if (is_null($repository)) {
            Flash::warning("No repository found for this organization.");
            return $this->redirect($this->getRouteRoot());
        }

        $registryService = new RegistryService();
        $ownerAddress = $registryService->getOwner($repository);
        if ($ownerAddress) {
            Flash::warning("This repository is already claimed.");
            return $this->redirect("/app/codebase/$organizationLogin/repositories/$repositoryName");
        }

        $hash = $registryService->claim($repository, $wallet);
        Debugger::barDump("CLAIM HASH: " . $hash);
        $hash = new NftService($wallet)->generate($repository);
        Debugger::barDump("NFT HASH: " . $hash);
        Flash::success("Your NFT was successfully minted 🎉! You can consult the <a href='https://polygonscan.com/tx/$hash' target='_blank'>transaction</a> ($hash) and find it on <a href='https://opensea.io/assets/ethereum/$wallet->address/'>OpenSea</a>.");
        Session::set("confetti", true);
        return $this->redirect("/app/codebase/$organizationLogin/repositories/$repositoryName");
    }
}
