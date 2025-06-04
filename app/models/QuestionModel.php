<?php

namespace App\Models;

class QuestionModel
{
    /**
     * @var \PDO
     */
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ✅ Lấy lat/lng của 1 câu hỏi
    public function getQuestionLatLng($question_id)
    {
        $sql = "SELECT correct_lat, correct_lng 
                FROM question 
                WHERE question_id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$question_id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // ✅ Lấy N câu hỏi ngẫu nhiên (chuẩn, ép kiểu LIMIT)
    public function getRandomQuestions($num)
    {
        $sql = "SELECT question_id, image_url, correct_lat, correct_lng, description 
                FROM question 
                ORDER BY RAND() 
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, (int) $num, \PDO::PARAM_INT);  // 🚀 ép kiểu int
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
