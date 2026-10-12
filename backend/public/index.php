<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../routes/api.php';

use CampusSOS\Routes\Router;
use function CampusSOS\Routes\registerRoutes;

$router = new Router();
registerRoutes($router);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);

