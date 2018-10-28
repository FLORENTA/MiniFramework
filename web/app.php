<?php

/* Front Controller */
use Classes\Process\InitApplication;

define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', dirname(dirname(__FILE__)));

require ROOT_DIR . '/lib/classes/DependencyInjection/Autoload.php';

(new InitApplication)->start();