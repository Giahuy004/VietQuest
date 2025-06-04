<?php
// app/models/RoomModel.php
namespace App\Models;

class RoomModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }
    public function getRoomById($room_id) {
        $stmt = $this->db->prepare("SELECT * FROM room WHERE room_id = ?");
        $stmt->execute([$room_id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    // Tạo phòng mới
    public function createRoom($playerCount, $hostId) {
        // Kiểm tra xem host_id có tồn tại trong bảng `account` không
        $query = "SELECT COUNT(*) FROM account WHERE account_id = :host_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':host_id', $hostId, \PDO::PARAM_INT);
        $stmt->execute();

        // Sinh số ngẫu nhiên có 6 chữ số (từ 100000 đến 999999)
        $roomId = rand(100000, 999999);

        // Thêm phòng vào cơ sở dữ liệu
        $query = "INSERT INTO room (room_id, max_players, host_id) VALUES (:room_id, :max_players, :host_id)";
        $stmt = $this->db->prepare($query);

        // Gán giá trị cho các tham số
        $stmt->bindParam(':room_id', $roomId, \PDO::PARAM_INT);
        $stmt->bindParam(':max_players', $playerCount, \PDO::PARAM_INT);
        $stmt->bindParam(':host_id', $hostId, \PDO::PARAM_INT);

        // Thực thi câu lệnh
        if (!$stmt->execute()) {
            throw new \Exception("Lỗi khi tạo phòng. Vui lòng thử lại.");
        }

        // Trả về ID của phòng mới tạo
        return $roomId;
    }

    // Lấy chủ phòng theo room_Id
    public function getHostInfo($roomId) {
        // Lấy host_id từ bảng room
        $query = "SELECT host_id FROM room WHERE room_id = :room_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, \PDO::PARAM_INT);
        $stmt->execute();
        $room = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$room) {
            throw new \Exception("Không tìm thấy phòng với ID: $roomId.");
        }

        $hostId = $room['host_id'];

        // Lấy tên chủ phòng từ bảng account
        $query = "SELECT name FROM account WHERE account_id = :host_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':host_id', $hostId, \PDO::PARAM_INT);
        $stmt->execute();
        $hostInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$hostInfo) {
            throw new \Exception("Không tìm thấy thông tin chủ phòng với ID: $hostId.");
        }

        // Trả về cả host_id và name
        return [
            'host_id' => $hostId,
            'name' => $hostInfo['name']
        ];
    }

    // Lấy số người chơi tối đa của phòng
    public function getMaxPlayers($roomId) {
        $query = "SELECT max_players FROM room WHERE room_id = :room_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \Exception("Không tìm thấy phòng với ID: $roomId.");
        }

        return $row['max_players'];
    }   

    // Lấy tên người chơi theo ID (của AccountModel mới đúng)
    public function getUserNameById($userId) {
        $query = "SELECT name FROM account WHERE account_id = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \Exception("Không tìm thấy người dùng với ID: $userId.");
        }

        return $row['name'];
    }

    // Cập nhật chủ phòng
    public function updateHost($roomId, $newHostId) {
        $query = "UPDATE room SET host_id = :new_host WHERE room_id = :room_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':new_host', $newHostId, \PDO::PARAM_INT);
        $stmt->bindParam(':room_id', $roomId, \PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new \Exception("Không thể cập nhật chủ phòng.");
        }

        return true;
    }

    // Xóa phòng
    public function deleteRoom($roomId) {
        $query = "DELETE FROM room WHERE room_id = :room_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, \PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new \Exception("Không thể xóa phòng.");
        }
        return true;
    }


}
?>
