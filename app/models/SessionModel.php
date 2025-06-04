<?php
namespace App\Models;

class SessionModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }
    public function getQuestionsBySession($session_id) {
        $sql = "
            SELECT q.question_id, q.image_url, q.correct_lat, q.correct_lng, q.description
            FROM sessiondetail sd
            JOIN question q ON sd.question_id = q.question_id
            WHERE sd.session_id = ?
            ORDER BY sd.question_order ASC
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$session_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function createSession($room_id) {
        $session_id = $this->generateUuid();
        $stmt = $this->db->prepare("INSERT INTO session (session_id, room_id, status) VALUES (?, ?, 'active')");
        $stmt->execute([$session_id, $room_id]);
        return $session_id;
    }

    public function addQuestionsToSession($session_id, $questions) {
        $order = 1;
        foreach ($questions as $q) {
            $stmt = $this->db->prepare("INSERT INTO sessiondetail (session_id, question_id, question_order) VALUES (?, ?, ?)");
            $stmt->execute([$session_id, $q['question_id'], $order]);
            $order++;
        }
    }

    private function generateUuid() {
        // Tạo UUID v4 đơn giản
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
