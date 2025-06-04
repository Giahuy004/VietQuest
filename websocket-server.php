<?php
// /websocket-server.php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once 'vendor/autoload.php';
require_once 'app/services/WebSocketService.php';
$loop = React\EventLoop\Factory::create();
use Ratchet\App;
use app\services\WebSocketService;

$host = 'localhost';
$port = 8080;
$webSocketService = new \App\Services\WebSocketService($loop);
$app = new App($host, $port);
$app->route('/socket', $webSocketService, ['*']);
echo "Starting WebSocket server on ws://localhost:8080/socket\n";

$app->run();