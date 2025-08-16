<?php namespace Models\Core;

use Zephyrus\Application\Flash;
use Zephyrus\Core\Session;
use Zephyrus\Exceptions\RouteNotFoundException;
use Zephyrus\Exceptions\Security\InvalidCsrfException;
use Zephyrus\Exceptions\Security\UnauthorizedAccessException;
use Zephyrus\Network\Response;
use Zephyrus\Security\AuthorizationRepository;

final class Kernel extends \Zephyrus\Core\Kernel
{
    public function __construct(string $applicationClass = Application::class)
    {
        parent::__construct($applicationClass);
        $this->initializeBaseAuthorizations();
    }

    protected function handleUnauthorizedException(UnauthorizedAccessException $exception): ?Response
    {
        if (in_array('authenticated', $exception->getRequirements())) {
            // Keep the attempted route in the session and once logged, try to redirect there
            Session::set('disconnect_route', $this->request->getRoute());
        }
        Flash::warning(localize("errors.unauthorized"));
        return Response::builder()->redirect("/");
    }

    protected function handleInvalidCsrf(InvalidCsrfException $exception): ?Response
    {
        Flash::error(localize("errors.csrf_invalid"));
        return Response::builder()->redirect(!empty($this->request->getReferer()) ? $this->request->getReferer() : "/");
    }

    protected function handleRouteNotFound(RouteNotFoundException $exception): ?Response
    {
        return Response::builder()->plain("404");
    }

    private function initializeBaseAuthorizations(): void
    {
        AuthorizationRepository::getInstance()->addSessionRule('authenticated', 'user');
    }
}
