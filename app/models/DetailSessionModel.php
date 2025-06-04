<?php
// app/models/DetailSessionModel.php
namespace App\Models;

class DetailSessionModel {
    private $db;

    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    /**
     * Ghi lại câu trả lời của người chơi cho một câu hỏi cụ thể trong session.
     *
     * @param string $sessionId ID của session.
     * @param int $questionId ID của câu hỏi.
     * @param int $accountId ID của người chơi.
     * @param float $answerLat Vĩ độ người chơi trả lời.
     * @param float $answerLng Kinh độ người chơi trả lời.
     * @param int|null $score Điểm số người chơi đạt được (tùy chọn).
     * @param int|null $timeTaken Thời gian người chơi trả lời (tùy chọn).
     * @return bool True nếu thành công, false nếu thất bại.
     */
    public function recordPlayerAnswer(string $sessionId, int $questionId, int $accountId, float $answerLat, float $answerLng, ?int $score = null, ?int $timeTaken = null): bool {
        $query = "INSERT INTO player_session_answers (session_id, question_id, account_id, answer_lat, answer_lng, score, time_taken, answered_at)
                  VALUES (:session_id, :question_id, :account_id, :answer_lat, :answer_lng, :score, :time_taken, NOW())";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':session_id', $sessionId, \PDO::PARAM_STR);
        $stmt->bindParam(':question_id', $questionId, \PDO::PARAM_INT);
        $stmt->bindParam(':account_id', $accountId, \PDO::PARAM_INT);
        $stmt->bindParam(':answer_lat', $answerLat, \PDO::PARAM_STR);
        $stmt->bindParam(':answer_lng', $answerLng, \PDO::PARAM_STR);
        $stmt->bindParam(':score', $score, \PDO::PARAM_INT);
        $stmt->bindParam(':time_taken', $timeTaken, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Lấy tất cả câu trả lời của một người chơi trong một session.
     */
    public function getPlayerAnswersInSession(string $sessionId, int $accountId): array {
        $query = "SELECT * FROM player_session_answers
                  WHERE session_id = :session_id AND account_id = :account_id
                  ORDER BY answered_at ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':session_id', $sessionId, \PDO::PARAM_STR);
        $stmt->bindParam(':account_id', $accountId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Bạn có thể thêm các phương thức khác ở đây như:
    // - getSessionLeaderboard(string $sessionId): Lấy bảng xếp hạng session.
    // - getQuestionResults(string $sessionId, int $questionId): Lấy kết quả cho một câu hỏi cụ thể.
}