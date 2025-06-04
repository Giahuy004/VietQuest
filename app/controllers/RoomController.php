<?php

namespace App\Controllers;  // Đảm bảo khai báo đúng namespace
require_once 'app/models/RoomModel.php';  // Đường dẫn đến model RoomModel
require_once 'app/config/database.php';
require_once 'app/models/DetailRoomModel.php'; // Đường dẫn đến model roomdetailModel

use App\Models\RoomModel;  // Sử dụng model RoomModel
use App\Config\Database;   // Đường dẫn đến class Database
use App\Models\roomdetailModel; // Đường dẫn đến class roomdetailModel
use DetailRoomModel;

class RoomController
{
    private $db;
    private $roomModel;
    private $detailRoomModel;

    public function __construct($db)
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->roomModel = new RoomModel($db);
        $this->detailRoomModel = new DetailRoomModel($db);

    }

    // Tạo phòng (AJAX POST)
    public function createRoom()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $hostId = $_SESSION['account_id'] ?? null;
        $hostName = $_SESSION['user_name'] ?? null;
        $playerCount = $_POST['playerCount'] ?? 1;

        if (!$hostId || !$hostName) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập!']);
            return;
        }

        try {
            $roomId = $this->roomModel->createRoom($playerCount, $hostId);

            // Thêm host vào bảng roomdetail
            $roomdetailModel = new \DetailRoomModel($this->db);
            $roomdetailModel->addPlayerToRoom($roomId, $hostId);
            
            // Lưu thông tin phòng vào session (hoặc DB nếu cần)
            $_SESSION['rooms'][$roomId] = [$hostId];
            echo json_encode([
                'success' => true,
                'roomId' => $roomId,
                'hostId' => $hostId,
                'hostName' => $hostName
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Tham gia phòng (AJAX POST)
    public function joinRoom()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $roomId = $_POST['roomId'] ?? null;
        $userId = $_SESSION['account_id'] ?? null;
        $userName = $_SESSION['user_name'] ?? null;

        if (!$roomId || !$userId || !$userName) {
            echo json_encode(['success' => false, 'message' => 'Thiếu thông tin tham gia phòng!']);
            return;
        }

        try {
            // Thêm user vào bảng roomdetail
            $this->detailRoomModel->addPlayerToRoom($roomId, $userId);

            // Lưu vào session (nếu cần)
            if (!isset($_SESSION['rooms'][$roomId])) {
                $_SESSION['rooms'][$roomId] = [];
            }
            if (!in_array($userId, $_SESSION['rooms'][$roomId])) {
                $_SESSION['rooms'][$roomId][] = $userId;
            }

            echo json_encode([
                'success' => true,
                'roomId' => $roomId,
                'userId' => $userId,
                'userName' => $userName
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Lấy danh sách người chơi trong phòng (AJAX GET)  
    public function getPlayers()
    {
        $roomId = $_GET['roomId'] ?? null;
        if (!$roomId) {
            echo json_encode(['success' => false, 'message' => 'Thiếu roomId']);
            return;
        }
        session_start();
        $userIds = $_SESSION['rooms'][$roomId] ?? [];
        $players = [];
        foreach ($userIds as $userId) {
            $players[] = [
                'userId' => $userId,
                'userName' => $this->roomModel->getUserNameById($userId)
            ];
        }
        echo json_encode(['success' => true, 'players' => $players]);
    }

    // Hiển thị giao diện tạo phòng
    public function showCreateRoom()
    {
        require_once 'app/views/room/create_room.php'; // Giao diện tạo phòng
    }

    // Hiển thị giao diện tham gia phòng
    public function showjoinRoom()
    {
        require_once 'app/views/room/join_room.php';  // Giao diện tham gia phòng
    }

    // Hiển thị giao diện phòng chơi
    public function showplayRoom()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $roomId = $_GET['room'] ?? null;

        if (!$roomId) {
            die("Không tìm thấy phòng");
        }

        // Lấy danh sách người chơi
        $players = $this->detailRoomModel->getPlayersInRoom($roomId);

        // Lấy tên chủ phòng (có kiểm tra)
        try {
            $hostInfo = $this->roomModel->getHostInfo($roomId);
            $hostName = $hostInfo['name'] ?? '';
        } catch (\Exception $e) {
            $hostName = '';
        }

        // Lấy số người chơi tối đa
        $maxPlayers = $this->roomModel->getMaxPlayers($roomId);

        // Truyền dữ liệu vào view
        require_once 'app/views/room/play_room.php';
    }

    // Xóa người chơi khỏi phòng (AJAX POST)
    public function leaveRoom() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $roomId = $_POST['roomId'] ?? null;
        $userId = $_POST['userId'] ?? null;
        if (!$roomId || !$userId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Thiếu roomId hoặc userId']);
            return;
        }
        require_once 'app/models/DetailRoomModel.php';
        $detailRoomModel = new \DetailRoomModel($this->db);

        // Lấy host hiện tại
        $hostInfo = $this->roomModel->getHostInfo($roomId);
        $hostId = $hostInfo['host_id'] ?? $hostInfo['account_id'] ?? null;

        // Kiểm tra xem người dùng có phải là chủ phòng hay không
        if ($userId == $hostId) {

            // Nếu là chủ phòng, cần tìm người chơi khác để làm chủ phòng mới
            $newHost = $detailRoomModel->getRandomPlayerInRoomExcept($roomId, $userId);
            if ($newHost && isset($newHost['account_id'])) {
                
                // Cập nhật chủ phòng mới
                $this->roomModel->updateHost($roomId, $newHost['account_id']);
                
                // Cập nhật lại session rooms (nếu bạn dùng session để lưu danh sách user/phòng)
                if (isset($_SESSION['rooms'][$roomId])) {
                    // Xóa userId cũ khỏi session
                    if (($key = array_search($userId, $_SESSION['rooms'][$roomId])) !== false) {
                        unset($_SESSION['rooms'][$roomId][$key]);
                    }
                    // Thêm chủ phòng mới vào session nếu chưa có
                    if (!in_array($newHost['account_id'], $_SESSION['rooms'][$roomId])) {
                        $_SESSION['rooms'][$roomId][] = $newHost['account_id'];
                    }
                }

                // Lấy host hiện tại với try-catch
                try {
                    $hostInfo = $this->roomModel->getHostInfo($roomId);
                    $hostId = $hostInfo['host_id'] ?? $hostInfo['account_id'] ?? null;
                } catch (\Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Lỗi lấy thông tin chủ phòng: ' . $e->getMessage()]);
                    return;
                }
            }
            // Sau đó mới xóa chủ phòng khỏi phòng
            $detailRoomModel->removePlayerFromRoom($roomId, $userId);
        } else {
            // Nếu không phải chủ phòng, chỉ cần xóa
            $detailRoomModel->removePlayerFromRoom($roomId, $userId);
        }

        // Kiểm tra xem còn người chơi trong phòng hay không
        $remainingPlayers = $this->detailRoomModel->countPlayersInRoom($roomId);

        if ($remainingPlayers === 0) {
            // Nếu không còn ai trong phòng, gọi hàm xóa phòng
            $this->roomModel->deleteRoom($roomId);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    // Lấy danh sách người chơi trong phòng (theo room_id)
    public function getPlayersInRoom($roomId) {
        $query = "
            SELECT a.account_id, a.name
            FROM roomdetail rd
            JOIN account a ON rd.account_id = a.account_id
            WHERE rd.room_id = :room_id
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
