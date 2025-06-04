<?php

namespace App\Controllers;

// app/controllers/AuthController.php
require_once 'app/models/AccountModel.php';
require_once 'app/config/database.php';
use App\Models\AccountModel;
use App\Config\Database;

class HomeController {
    private $accountModel;
    private $db;

    public function __construct() {
        // Tạo đối tượng Database và AccountModel
        $database = new Database();
        $this->db = $database->getConnection();
        $this->accountModel = new AccountModel($this->db); // Sử dụng $this->db thay vì $db
    }

    // Phương thức hiển thị trang index
    public function index() {
        // Chỉ cần yêu cầu hiển thị view index.php
        require 'app/views/home/index.php';
    }
    
}

?>
