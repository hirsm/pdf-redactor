<?php
use Slim\Factory\AppFactory;
use App\Middleware\AuthMiddleware;
use App\Controller\AuthController;
use App\Controller\AppController;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$app = AppFactory::create();
$basePath = $_ENV['APP_BASE_PATH'] ?? '';
$app->setBasePath($basePath);
$app->addBodyParsingMiddleware();

$app->get('/login', [AuthController::class, 'loginPage']);
$app->get('/auth/start', [AuthController::class, 'start']);
$app->any('/auth/callback', [AuthController::class, 'callback']);
$app->get('/logout', [AuthController::class, 'logout']);

$app->group('', function ($group) {
    $group->get('/', [AppController::class, 'index']);
    $group->post('/upload', [AppController::class, 'upload']);
})->add(new AuthMiddleware());

$app->run();
