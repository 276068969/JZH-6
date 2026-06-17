<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Core/MigrationRunner.php';
require_once __DIR__ . '/../src/Core/Auth.php';
require_once __DIR__ . '/../src/Core/View.php';
require_once __DIR__ . '/../templates/helpers.php';
require_once __DIR__ . '/../src/Models/DashboardRepository.php';
require_once __DIR__ . '/../src/Controllers/AppController.php';

use App\Controllers\AppController;

session_start();

$controller = new AppController();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$method = $method === 'HEAD' ? 'GET' : $method;

try {
    if ($path === '/' && $method === 'GET') {
        $controller->home();
    } elseif ($path === '/login' && $method === 'GET') {
        $controller->loginForm();
    } elseif ($path === '/login' && $method === 'POST') {
        $controller->login();
    } elseif ($path === '/logout') {
        $controller->logout();
    } elseif ($path === '/admin' && $method === 'GET') {
        $controller->admin();
    } elseif ($path === '/admin/cases' && $method === 'POST') {
        $controller->createCase();
    } elseif ($path === '/admin/ambulances' && $method === 'POST') {
        $controller->updateAmbulance();
    } elseif ($path === '/admin/alerts/handle' && $method === 'POST') {
        $controller->handleAlert();
    } elseif ($path === '/api/overview' && $method === 'GET') {
        $controller->overviewApi();
    } elseif ($path === '/api/dispatch-check' && $method === 'GET') {
        $controller->dispatchCheckApi();
    } else {
        http_response_code(404);
        echo '页面不存在';
    }
} catch (Throwable $exception) {
    http_response_code(500);
    echo '系统暂时不可用：' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
}
