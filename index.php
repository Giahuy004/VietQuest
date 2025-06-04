<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once 'app/config/database.php';
use App\Config\Database;

session_start();


$url = $_GET['url'] ?? '';
$url = rtrim($url, '/');
$url = filter_var($url, FILTER_SANITIZE_URL);
$url = explode('/', $url);

if ($url[0] === 'VietQuest') {
    array_shift($url);
}

// Mặc định HomeController và action index
$controllerName = !empty($url[0]) ? ucfirst($url[0]) . 'Controller' : 'AuthController';
$controllerClass = '\\App\\Controllers\\' . $controllerName;
$action = $url[1] ?? 'index'; // Mặc định vào action index
$params = array_slice($url, 2);

$controllerPath = 'app/controllers/' . $controllerName . '.php';

if (!file_exists($controllerPath)) {
    http_response_code(404);
    die('Không tìm thấy controller: ' . htmlspecialchars($controllerName));
}

require_once $controllerPath;

if (!class_exists($controllerClass)) {
    die("Class $controllerClass không tồn tại! Kiểm tra lại namespace và tên class trong file $controllerPath");
}

// --- Khởi tạo kết nối DB ---
$database = new Database();
$db = $database->getConnection();

// Nếu controller cần $db thì truyền vào, nếu không thì không truyền
$reflector = new ReflectionClass($controllerClass);
if ($reflector->getConstructor() && $reflector->getConstructor()->getNumberOfParameters() > 0) {
    $controller = new $controllerClass($db);
} else {
    $controller = new $controllerClass();
}


if (!method_exists($controller, $action)) {
    http_response_code(404);
    die('Không tìm thấy action: ' . htmlspecialchars($action));
}

call_user_func_array([$controller, $action], $params);


