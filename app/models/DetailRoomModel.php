<?php
// app/models/DetailRoomModel.php

class DetailRoomModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Thêm người chơi vào phòng
    public function addPlayerToRoom($roomId, $userId) {
        $query = "INSERT INTO roomdetail (room_id, account_id) VALUES (:room_id, :account_id)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId);
        $stmt->bindParam(':account_id', $userId);
        return $stmt->execute();
    }

    // Lấy danh sách người chơi trong phòng (theo room_id)
    public function getPlayersInRoom($roomId) {
        $query = "SELECT a.name 
                FROM roomdetail rd
                JOIN account a ON rd.account_id = a.account_id
                WHERE rd.room_id = :room_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, \PDO::PARAM_INT);
        $stmt->execute();

        // Trả về mảng chỉ gồm tên người chơi
        $players = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $players;
    }   

    // Lấy 1 người chơi khác bất kỳ (không phải $excludeUserId)
    public function getRandomPlayerInRoomExcept($roomId, $excludeUserId) {
        $query = "SELECT account_id FROM roomdetail WHERE room_id = :room_id AND account_id != :exclude_id ORDER BY RAND() LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId);
        $stmt->bindParam(':exclude_id', $excludeUserId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Xóa người chơi khỏi phòng
    public function removePlayerFromRoom($roomId, $userId) {
        $query = "DELETE FROM roomdetail WHERE room_id = :room_id AND account_id = :account_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId);
        $stmt->bindParam(':account_id', $userId);
        return $stmt->execute();
    }
    
    // Lấy số người chơi trong phòng
    public function countPlayersInRoom($roomId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM roomdetail WHERE room_id = :room_id");
        $stmt->bindParam(':room_id', $roomId, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn(); // Trả về số lượng người chơi
    }
}
