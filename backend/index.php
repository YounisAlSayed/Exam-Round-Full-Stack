<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
define('BASE_PATH', dirname($_SERVER['SCRIPT_NAME']));

use Dotenv\Dotenv;
use App\Utils\Cors;
use App\Routes\Router;
$_SESSION['user'] = ['id' => 1, 'role' => 'teacher'];
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

(new Cors())->handle();
require_once 'routes/api.php';
Router::dispatch();