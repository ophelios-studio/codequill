<?php namespace Controllers\Application;

use Models\Account\Brokers\WalletBroker;
use Models\Account\Entities\Wallet;
use Models\Account\Services\WalletService;
use Pulsar\Account\MultiFactor;
use Pulsar\Account\Passport;
use Pulsar\Account\Services\UserService;
use Tracy\Debugger;
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

    #[Get("/wallet")]
    public function walletForm(): Response
    {
        $wallet = new WalletService()->getConnectedWallet(Passport::getUserId());
        return $this->render("application/profile/wallet", [
            'wallet' => $wallet
        ]);
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

    #[Get("/mfa")]
    public function mfaForm(): Response
    {
        $user = Passport::getUser();
        if ($user->authentication->oauth_provider) {
            Flash::error(localize("accounts.errors.mfa_oauth"));
            return $this->redirect("/app/profile");
        }
        $user = Passport::getUser();
        $mfa = new MultiFactor(Passport::getUser());
        $otpImageUrl = null;
        $otpSecret = null;
        $otp = $user->authentication->getMfa('otp');

        if (is_null($otp)) {
            $mfa->initiateAuthenticator();
            $otpImageUrl = $mfa->getAuthenticatorImage();
            $otpSecret = $mfa->getAuthenticatorSecret();
        }
        return $this->render("application/profile/mfa", [
            'otp_image_url' => $otpImageUrl,
            'otp_secret' => $otpSecret,
        ]);
    }

    #[Post("/password")]
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

    #[Post("/mfa-preferred")]
    public function changePreferredMfa(): Response
    {
        $user = Passport::getUser();
        if ($user->authentication->oauth_provider) {
            Flash::error(localize("accounts.errors.mfa_oauth"));
            return $this->redirect("/app/profile");
        }
        $mfa = $this->request->getParameter('mfa');
        if (!is_numeric($mfa)) {
            return $this->redirect($this->getRouteRoot());
        }
        UserService::setPrimaryMfa(Passport::getUser(), $mfa);
        Flash::success(localize("accounts.success.mfa_update"));
        return $this->redirect($this->getRouteRoot());
    }

    #[Post("/mfa/email/{action}")]
    public function toggleEmailMfa(string $action): Response
    {
        $user = Passport::getUser();
        if ($user->authentication->oauth_provider) {
            Flash::error(localize("accounts.errors.mfa_oauth"));
            return $this->redirect("/app/profile");
        }
        if ($action === 'enable') {
            UserService::enableEmailMfa(Passport::getUser());
            Flash::success(localize("accounts.success.mfa_email_enabled"));
        }
        if ($action === 'disable') {
            UserService::disableEmailMfa(Passport::getUser());
            Flash::success(localize("accounts.success.mfa_email_disabled"));
        }
        return $this->modalSuccess();
    }

    #[Post("/mfa/otp/{action}")]
    public function toggleOtpMfa(string $action): Response
    {
        $user = Passport::getUser();
        if ($user->authentication->oauth_provider) {
            Flash::error(localize("accounts.errors.mfa_oauth"));
            return $this->redirect("/app/profile");
        }
        $user = Passport::getUser();
        if ($action === 'enable') {
            $code = $this->request->getParameter('code', '');
            if (is_array($code)) {
                $code = implode('', $code);
            }

            if (is_null($code)) {
                return $this->modalError(["Session has ended. Please try again."]);
            }

            $mfa = new MultiFactor($user);
            if ($mfa->verifyAuthenticatorCode($code)) {
                UserService::enableOtpMfa($user);
                Flash::success(localize("accounts.success.mfa_otp_enabled"));
                return $this->modalSuccess();
            }
            return $this->modalError([localize("accounts.errors.mfa_invalid")]);;
        }
        if ($action === 'disable') {
            UserService::disableOtpMfa($user);
            Flash::success(localize("accounts.success.mfa_otp_disabled"));
        }
        return $this->modalSuccess();
    }
}
