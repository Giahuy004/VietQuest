<?php
namespace App\Models;
class AccountModel {
    private $db;

    public function __construct($db) {
        $this->db = $db; // Kết nối với cơ sở dữ liệu
    }

    // Tạo tài khoản mới
    public function createAccount($name, $email, $password) {
        // Mã hóa mật khẩu trước khi lưu vào cơ sở dữ liệu
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        $query = "INSERT INTO Account (name, email, password_hash) VALUES (:name, :email, :password_hash)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $passwordHash);
        
        return $stmt->execute();
    }

    // Lấy thông tin tài khoản từ email
    public function getAccountByEmail($email) {
        $query = "SELECT * FROM Account WHERE email = :email";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // Kiểm tra mật khẩu khi đăng nhập
    public function checkPassword($inputPassword, $storedPasswordHash) {
        return password_verify($inputPassword, $storedPasswordHash);
    }

    // Cập nhật thông tin tài khoản (nếu cần)
    public function updateAccount($id, $name, $email, $password) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $query = "UPDATE Account SET name = :name, email = :email, password_hash = :password_hash WHERE id = :account_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':account_id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $passwordHash);
        
        return $stmt->execute();
    }
    
}
?>
