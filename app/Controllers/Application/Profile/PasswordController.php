<?php namespace Controllers\Application\Profile;

use Models\Account\Services\WalletService;
use Pulsar\Account\Passport;
use Pulsar\Account\Services\UserService;
use Zephyrus\Application\Flash;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Root;

#[Root("/password")]
class PasswordController extends ProfileController
{
    #[Get("/")]
    public function changePasswordForm(): Response
    {
        $user = Passport::getUser();
        if ($user->authentication->oauth_provider) {
            Flash::error(localize("accounts.errors.password_oauth"));
            return $this->redirect("/app/profile");
        }
        return $this->render("application/profile/password");
    }

    #[Post("/")]
    public function changePassword(): Response
    {
        $user = Passport::getUser();
        if ($user->authentication->oauth_provider) {
            Flash::error(localize("accounts.errors.mfa_oauth"));
            return $this->redirect("/app/profile");
        }
        UserService::updatePassword(Passport::getUser(), $this->buildForm(), true);
        Passport::reloadUser();
        Flash::success(localize("accounts.success.password_updated"));
        return $this->redirect($this->getRouteRoot());
    }
}
