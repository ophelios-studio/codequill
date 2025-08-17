<?php namespace Controllers\Application\Verification;

use Models\Account\Services\SnapshotService;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;

class HistoryController extends VerificationController
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("application/verification/upload");
    }

    #[Post("/")]
    public function uploadForm(): Response
    {
        $hash = $this->request->getParameter("hash");
        if (empty($hash)) {
            Flash::error("The <b>hash</b> must not be empty.");
            return $this->redirect($this->getRouteRoot());
        }

        $service = new SnapshotService();
        if ($service->hasSnapshotByHash($repoId, $totalHashHex)) {
            $meta = $service->getSnapshotMetaByHash($repoId, $totalHashHex);  // timestamp, author, index, commitHash
            $cid  = $service->getSnapshotCidByHash($repoId, $totalHashHex);  // optional
            // do something
        } else {
            // not found
        }
    }
}
