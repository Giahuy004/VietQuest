<?php
namespace App\Controllers;

require_once 'app/models/RoomModel.php';
require_once 'app/models/SessionModel.php';
require_once 'app/models/QuestionModel.php';
class GameController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function startGame($room_id, $host_id) {
        try {

            // 1️⃣ Kiểm tra phòng có tồn tại không
            $roomModel = new \App\Models\RoomModel($this->db);
            $room = $roomModel->getRoomById($room_id);

            if (!$room) {
                return [
                    'success' => false,
                    'message' => "Phòng {$room_id} không tồn tại."
                ];
            }

            if ($room['host_id'] != $host_id) {
                return [
                    'success' => false,
                    'message' => "Chỉ host mới có quyền bắt đầu game."
                ];
            }

            // 2️⃣ Tạo session mới
            $sessionModel = new \App\Models\SessionModel($this->db);
            $sessionId = $sessionModel->createSession($room_id);

            $questionModel = new \App\Models\QuestionModel($this->db);
            $questions = $questionModel->getRandomQuestions(2); 

            $sessionModel->addQuestionsToSession($sessionId, $questions);

            return [
                'success' => true,
                'message' => "Game đã bắt đầu!",
                'sessionId' => $sessionId,
                'questionOrder' => array_column($questions, 'question_id'),
                'questions' => $questions
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Lỗi khi start game: " . $e->getMessage()
            ];
        }
    }
}
