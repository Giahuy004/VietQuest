<?php
// app/controllers/QuestionController.php
namespace App\Controllers;
require_once 'app/models/QuestionModel.php';

class QuestionController {
    private $questionModel;

    public function __construct($db) {
        $this->questionModel = new \App\Models\QuestionModel($db); // Sửa: thêm namespace đầy đủ
    }

    // Hiển thị tất cả hình ảnh (câu hỏi)
    
}
