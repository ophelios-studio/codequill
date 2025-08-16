<?php

use Models\Core\Kernel;

define('ROOT_DIR', realpath(__DIR__ . '/..'));
require ROOT_DIR . '/vendor/autoload.php';

$kernel = new Kernel();
$response = $kernel->run();
$response->send();
