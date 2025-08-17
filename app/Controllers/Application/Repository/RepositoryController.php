<?php namespace Controllers\Application\Repository;

use Models\Account\Services\NftService;
use Models\Account\Services\RegistryService;
use Models\Account\Services\SnapshotService;
use Models\Account\Services\WalletService;
use Pulsar\Account\Passport;
use Pulsar\OAuth\GitHub\GitHubService;
use Tracy\Debugger;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Security\Cryptography;
use Zephyrus\Utilities\FileSystem\Directory;

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
        $user = $service->getUser();
        return $this->render("application/repository/repositories", [
            'repositories' => $repositories,
            'organization' => ($organizationLogin != "me") ? $service->getOrganization($organizationLogin) : $user,
            'github_user' => ($organizationLogin !== "me") ? $user : null,
            'claimed_repo_ids' => $claimedRepoIds,
        ]);
    }

    #[Get("/{org}/repositories/{repository}")]
    public function read(string $organizationLogin, string $repositoryName): Response
    {
        $token = Passport::getUser()->authentication->oauth_access_token;
        $service = new GitHubService($token);
        try {
            if ($organizationLogin != "me") {
                $repository = $service->getRepository($organizationLogin . "/" . $repositoryName);
            } else {
                $repository = $service->getRepository($repositoryName);
            }
        } catch (\Exception $e) {
            Flash::warning("You are not the owner of the selected repository.");
            return $this->redirect("/app/codebase/$organizationLogin/repositories");
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

        $snapshots = [];
        $wallet = new WalletService()->getConnectedWallet(Passport::getUserId());
        if ($ownerAddress == $wallet->address) {
            $snapshotService = new SnapshotService();
            $snapshots = $snapshotService->getSnapshots($repository->id);
            Debugger::barDump($snapshots);
        }
        $user = $service->getUser();
        return $this->render("application/repository/read", [
            'repository' => $repository,
            'organization' => ($organizationLogin != "me") ? (object) $repository->getRawData()->organization : null,
            'github_user' => ($organizationLogin !== "me") ? $user : null,
            'confetti' => $confetti,
            'owner_address' => $ownerAddress,
            'snapshots' => $snapshots
        ]);
    }

    #[Post("/{org}/repositories/{repository}/snapshot")]
    public function snapshot(string $organizationLogin, string $repositoryName): Response
    {
        $wallet = new WalletService()->getConnectedWallet(Passport::getUserId());
        if (is_null($wallet)) {
            Flash::warning("No wallet connected.");
            return $this->redirect($this->getRouteRoot());
        }

        $token = Passport::getUser()->authentication->oauth_access_token;
        $service = new GitHubService($token);
        $githubUser = $service->getUser();
        $owner = ($organizationLogin != "me") ? $organizationLogin : $githubUser->login;

        if ($organizationLogin != "me") {
            $repository = $service->getRepository($organizationLogin . "/" . $repositoryName);
        } else {
            $repository = $service->getRepository($repositoryName);
        }

        if (is_null($repository)) {
            Flash::warning("No repository found for this organization.");
            return $this->redirect($this->getRouteRoot());
        }

        $snapshotService = new SnapshotService();

        $destination = ROOT_DIR . "/temp/github/" . Cryptography::randomString(32);
        $path = $service->downloadRepositoryZip($owner, $repositoryName, "main", $destination);
        $hash = hash_file('sha256', $path);
        new Directory($destination)->remove();
        $transactionHash = $snapshotService->snapshot($repository->id, $wallet->address, $hash);

        Flash::success("The snapshot was successfully created for this repository 🎉. You can consult the <a href='https://polygonscan.com/tx/$transactionHash' target='_blank'>transaction</a>. Please allow a couple of minutes for the snapshot to be indexed.");
        return $this->redirect("/app/codebase/$organizationLogin/repositories/$repositoryName");
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
        Flash::success("Your NFT was successfully minted 🎉! You can consult the <a href='https://polygonscan.com/tx/$hash' target='_blank'>transaction hash</a> and find it on <a href='https://opensea.io/assets/ethereum/$wallet->address/'>OpenSea</a>.");
        Session::set("confetti", true);
        return $this->redirect("/app/codebase/$organizationLogin/repositories/$repositoryName");
    }

    #[Post("/{org}/repositories/{repository}/verify")]
    public function verify(string $organizationLogin, string $repositoryName): Response
    {
        $wallet = new WalletService()->getConnectedWallet(Passport::getUserId());
        if (is_null($wallet)) {
            Flash::warning("No wallet connected.");
            return $this->redirect("/app/codebase/$organizationLogin/repositories/$repositoryName");
        }

        $token = Passport::getUser()->authentication->oauth_access_token;
        $service = new GitHubService($token);

        if ($organizationLogin != "me") {
            $repository = $service->getRepository($organizationLogin . "/" . $repositoryName);
        } else {
            $repository = $service->getRepository($repositoryName);
        }

        $hash = $this->request->getParameter("hash");
        if (empty($hash)) {
            Flash::error("The <b>hash</b> must not be empty.");
            return $this->redirect("/app/codebase/$organizationLogin/repositories/$repositoryName");
        }

        $service = new SnapshotService();
        if ($service->hasSnapshotByHash($repository->id, $hash)) {
            $meta = $service->getSnapshotMetaByHash($repository->id, $hash);  // timestamp, author, index, commitHash
            Debugger::barDump($meta);
            Flash::success("A snapshot with the specified hash was found authored by <b>" . $meta['author'] . "</b> at " . format('datetime', date('Y-m-d H:i:s', $meta['timestamp'])) . ".");
        } else {
            Flash::success("No snapshot with the specified hash was found.");
        }
        return $this->redirect("/app/codebase/$organizationLogin/repositories/$repositoryName");
    }
}
