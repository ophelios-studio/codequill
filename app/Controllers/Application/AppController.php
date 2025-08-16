<?php namespace Controllers\Application;

use Controllers\Controller;
use Zephyrus\Network\Router\Authorize;
use Zephyrus\Network\Router\Root;

#[Root("/app")]
#[Authorize("authenticated")]
abstract class AppController extends Controller
{

}
