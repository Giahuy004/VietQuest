<?php
namespace App\Controllers;
// app/controllers/AuthController.php
require_once 'app/models/AccountModel.php';
require_once 'app/config/database.php';

use App\Models\AccountModel;  // <-- Import class AccountModel đúng namespace
use App\Config\Database;  // <-- Import class Database đúng namespace
class AuthController {
    private $accountModel;
    private $db;

    public function __construct() {
        // Tạo đối tượng Database và AccountModel
        $database = new Database();
        $this->db = $database->getConnection();
        $this->accountModel = new AccountModel($this->db); // Sử dụng $this->db thay vì $db
    }

    // Hiển thị trang đăng nhập
    public function index() {
        require 'app/views/auth/login.php';
    }

    // Xử lý đăng nhập
    public function login() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $account = $this->accountModel->getAccountByEmail($email);

        if ($account && password_verify($password, $account['password_hash'])) {
            $_SESSION['account_id'] = $account['account_id'];
            $_SESSION['user_name'] = $account['name'];
            header("Location: /VietQuest/Home");
            exit();
        } else {
            $error = "Thông tin đăng nhập không chính xác!";
            require 'app/views/auth/login.php';
        }
    }

    // Hiển thị form đăng ký
    public function showRegister() {
        require 'app/views/auth/register.php';
    }

    // Xử lý đăng ký tài khoản
    public function register() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['confirm_password'] ?? '';

        if ($password !== $passwordConfirm) {
            $error = "Mật khẩu xác nhận không trùng khớp!";
            require 'app/views/auth/register.php';
            return;
        }

        if ($this->accountModel->getAccountByEmail($email)) {
            $error = "Email này đã được đăng ký!";
            require 'app/views/auth/register.php';
            return;
        }

        $this->accountModel->createAccount($name, $email, $password);

        $account = $this->accountModel->getAccountByEmail($email);
        $_SESSION['account_id'] = $account['account_id'];
        $_SESSION['user_name'] = $account['name'];
        header("Location: /VietQuest/Home");
        exit();
    }

    // Phương thức đăng xuất
    public function logout() {
        
        $userId = $_SESSION['account_id'] ?? null;
        $roomId = $_SESSION['current_room_id'] ?? null;

       
        
        // Hủy session và đăng xuất người dùng
        session_unset();   // Xóa tất cả dữ liệu session
        session_destroy(); // Hủy session


        // Chuyển hướng về trang chủ hoặc trang đăng nhập
        header("Location: /VietQuest/auth/showlogin");
        exit();
    }
}


?>
