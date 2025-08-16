<?php namespace Controllers\Application;

use Controllers\Controller;
use Pulsar\Account\Passport;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Authorize;
use Zephyrus\Network\Router\Root;

#[Root("/app")]
#[Authorize("authenticated")]
abstract class AppController extends Controller
{
    public function render(string $page, array $args = []): Response
    {
        $args = array_merge($args, [
            'user' => Passport::getUser()
        ]);
        return parent::render($page, $args);
    }

}
