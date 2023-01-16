<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
chdir('/src/api-platform');

require './vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

function run(string $guide) {
    require "/src/api-platform/src/$guide.php";

    $app = function (array $context) use ($guide) {
        return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG'], $guide);
    };

    $runtime = $_SERVER['APP_RUNTIME'] ?? $_ENV['APP_RUNTIME'] ?? 'Symfony\\Component\\Runtime\\SymfonyRuntime';
    $runtime = new $runtime(['disable_dotenv']);
    [$app, $args] = $runtime
        ->getResolver($app)
        ->resolve();

    $app = $app(...$args);

    $app->executeMigrations();
    $app->loadFixtures();
    $app->request();
}
