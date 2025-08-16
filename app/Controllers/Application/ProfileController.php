<?php namespace Controllers\Application;

use Pulsar\Account\Passport;
use Pulsar\Account\Services\UserService;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Root;

#[Root("/profile")]
class ProfileController extends AppController
{
    #[Get("/")]
    public function index(): Response
    {
        return $this->render("application/profile/settings");
    }

    #[Get("/password")]
    public function changePasswordForm(): Response
    {
        $user = Passport::getUser();
        if ($user->authentication->oauth_provider) {
            Flash::error(localize("accounts.errors.password_oauth"));
            return $this->redirect("/app/profile");
        }
        return $this->render("application/profile/password");
    }

    #[Post("/password")]
    public function changePassword(): Response
    {
        $user = Passport::getUser();
        if ($user->authentication->oauth_provider) {
            Flash::error(localize("accounts.errors.password_oauth"));
            return $this->redirect("/app/profile");
        }
        UserService::updatePassword(Passport::getUser(), $this->buildForm(), true);
        Flash::success(localize("accounts.success.password_updated"));
        return $this->redirect($this->getRouteRoot());
    }
}
