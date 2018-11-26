<?php

/* Front Controller */
use Lib\Process\InitApplication;

define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', dirname(dirname(__FILE__)));

require ROOT_DIR . '/lib/DependencyInjection/Autoload.php';

/** @var \Lib\Http\Response|\Lib\Model\JsonResponse $response */
$response = (new InitApplication)->boot();
$response->send();