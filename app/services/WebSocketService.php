<?php
namespace App\Services;
require_once 'app/config/database.php';
require_once 'vendor/autoload.php'; // Đảm bảo autoload được nạp

use App\Config\Database; // Đường dẫn đến class Database
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketService implements MessageComponentInterface
{
    protected $readyStates; // Thêm biến này

    private $db;
    protected $clients;
    protected $rooms;
    protected $roomStates; // 'waiting', 'playing'
    protected $gameStates;
    protected $gameController; // Thêm biến để lưu GameController
    protected $loop;
    
    public function __construct($loop) {
        
        $this->loop = $loop;
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->readyStates = [];
        $this->roomStates = [];
        $this->gameStates = [];
        $this->db = (new Database())->getConnection(); // Kết nối DB
    
        require_once 'app/controllers/GameController.php'; // 🔥 Thêm dòng này
        $this->gameController = new \App\Controllers\GameController($this->db); // 🔥 Thêm dòng này
    
        echo "WebSocket server started.\n";
        $this->loop->addPeriodicTimer(1, function() {
            echo "[HEARTBEAT] " . microtime(true) . "\n";
        });
    }
    protected function sendScoreboardToRoom($room_id, $session_id) {
        $stmt = $this->db->prepare("
            SELECT a.name AS userName, COALESCE(SUM(s.total_score), 0) AS totalScore
            FROM account a
            JOIN scoreboard s ON a.account_id = s.account_id
            WHERE s.room_id = ? 
            GROUP BY a.account_id
            ORDER BY totalScore DESC
        ");
        $stmt->execute([$room_id]);
        $isLastQuestion = false;
$currentIndex = $this->gameStates[$room_id]['current_question_index'] ?? 0;
$totalQuestions = count($this->gameStates[$room_id]['questions'] ?? []);
if ($currentIndex + 1 >= $totalQuestions) {
    $isLastQuestion = true;
}
        $scoreboard = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $scoreboard[] = [
                'userName' => $row['userName'],
                'totalScore' => (int) $row['totalScore']
            ];
        }
    
        // ✅ Lấy host_id
        require_once 'app/models/RoomModel.php';
        $roomModel = new \App\Models\RoomModel($this->db);
        $hostInfo = $roomModel->getHostInfo($room_id);
        $host_id = $hostInfo['host_id'] ?? null;
    
        // ✅ Gửi cho từng user, mỗi người có `isHost` riêng!
        if (isset($this->rooms[$room_id])) {
            foreach ($this->rooms[$room_id] as $info) {
                $user_id = $info['userId'];
                $isHost = ($user_id == $host_id);
    
                $message = json_encode([
                    'action' => 'updateScoreboard',
                    'scoreboard' => $scoreboard,
                    'isHost' => ($host_id == $user_id), // nếu bạn có host_id
                    'isLastQuestion' => $isLastQuestion
                ]);
    
                $info['conn']->send($message);
            }
        }
    }
    
    protected function nextQuestionSafe($room_id, $session_id) {
        // Tăng index
        echo "[NEXT QUESTION] Room {$room_id} - Moving to next question...\n";
        $this->gameStates[$room_id]['current_question_index']++;
    
        // Kiểm tra end game
        $currentIndex = $this->gameStates[$room_id]['current_question_index'];
        $totalQuestions = count($this->gameStates[$room_id]['questions'] ?? []);
    
        if ($currentIndex >= $totalQuestions) {
            echo "[GAME] Room {$room_id} - Game Over.\n";
    
            $scoreboard = $this->getScoreboard($room_id); // Sẵn có hàm này rồi

            // --- Get host ---
            require_once 'app/models/RoomModel.php';
            $roomModel = new \App\Models\RoomModel($this->db);
            $hostInfo = $roomModel->getHostInfo($room_id);
            $hostName = $hostInfo['name'] ?? '';

            $message = json_encode([
                'action' => 'gameOver',
                'scoreboard' => $scoreboard,
                'hostName' => $hostName
            ]);
    
            if (isset($this->rooms[$room_id])) {
                foreach ($this->rooms[$room_id] as $info) {
                    $info['conn']->send($message);
                }
            }
    
            return;
        }
    
        // Nếu còn câu → gửi tiếp
        $this->startNextQuestion($room_id, $session_id);
    }
    
    protected function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance;
    }

    // Tính điểm
    protected function calculateScore($distance)
    {
        // Ví dụ: điểm càng gần càng cao, max 100
        $score = max(0, 100 - round($distance * 10));
        return $score;
    }

    // Lấy bảng xếp hạng
    protected function getScoreboard($room_id)
    {
        $stmt = $this->db->prepare("
            SELECT a.name, s.total_score
            FROM scoreboard s
            JOIN account a ON s.account_id = a.account_id
            WHERE s.room_id = :room_id
            ORDER BY s.total_score DESC
        ");
        $stmt->execute([':room_id' => $room_id]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    // Khi một kết nối mới được mở
    public function onOpen(ConnectionInterface $conn) {
        echo "New connection: {$conn->resourceId}\n";
        $this->clients->attach($conn);

        // Gửi thông báo connected về client
        $conn->send(json_encode([
            'action' => 'connected',
            'message' => 'Kết nối WebSocket thành công! (ID: ' . $conn->resourceId . ')'
        ]));
    }

    // Khi một kết nối bị đóng
    public function onClose(ConnectionInterface $conn) {
        foreach ($this->rooms as $room_id => $users) {
            foreach ($users as $resourceId => $info) {
                if ($info['conn'] === $conn) {
                    unset($this->rooms[$room_id][$resourceId]);
    
                    echo "User {$resourceId} has disconnected from room {$room_id}.\n";
    
                    // ⚠️ Chỉ xóa room nếu không phải đang playing
                    if (empty($this->rooms[$room_id])) {
                        if (($this->roomStates[$room_id] ?? 'waiting') !== 'playing') {
                            unset($this->rooms[$room_id]);
                            unset($this->roomStates[$room_id]);
                            echo "Room '{$room_id}' has been deleted because it is empty.\n";
                        } else {
                            echo "Room '{$room_id}' is in playing state, not deleting.\n";
                        }
                    } else {
                        $this->sendUserListToRoom($room_id);
                    }
                    break 2;
                }
            }
        }
    }
    protected function startNextQuestion($room_id, $session_id) {
        echo "[START NEXT QUESTION] Room {$room_id} - Starting next question...\n";
        $currentIndex = $this->gameStates[$room_id]['current_question_index'] ?? 0;
        $questions = $this->gameStates[$room_id]['questions'] ?? [];
    
        if ($currentIndex >= count($questions)) {
            echo "[GAME] Room {$room_id} - Game Over.\n";
            // Optional: gửi event gameOver
            return;
        }
    
        $question = $questions[$currentIndex];
    
        $message = json_encode([
            'action' => 'showQuestion',
            'question' => $question,
            'questionIndex' => $currentIndex,
            'timeLimit' => 20
        ]);
    
        // Gửi cho tất cả user
        if (isset($this->rooms[$room_id])) {
            foreach ($this->rooms[$room_id] as $info) {
                $info['conn']->send($message);
            }
        }
    
        // Reset players_answered
        foreach ($this->gameStates[$room_id]['players_answered'] as $uid => &$answered) {
            $answered = false;
        }
    
        // Bắt timer 20s auto-submit
        $this->gameStates[$room_id]['autoSubmitTimer'] = $this->scheduleAutoSubmit($room_id, $session_id, $currentIndex, 20);
    
    
        echo "[START NEXT QUESTION] Room {$room_id} - Sent question index {$currentIndex}\n";
    }
    
    
    
    
    protected function scheduleAutoSubmit($room_id, $session_id, $questionIndex, $timeLimit) {
        $timer = $this->loop->addTimer($timeLimit, function() use ($room_id, $session_id, $questionIndex) {
            echo "[AUTO] Check auto-submit cho room {$room_id}, question {$questionIndex}\n";
    
            foreach ($this->gameStates[$room_id]['players_answered'] as $user_id => $answered) {
                if (!$answered) {
                    $stmt = $this->db->prepare("
                        INSERT INTO answer (account_id, session_id, question_id, selected_lat, selected_lng, time_taken, distance)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, $session_id,
                        $this->gameStates[$room_id]['questions'][$questionIndex]['question_id'],
                        0, 0, 20, 99999.0
                    ]);
    
                    echo "[AUTO] User {$user_id} auto-submitted (score 0)\n";
    
                    $this->gameStates[$room_id]['players_answered'][$user_id] = true;
                }
            }
    
            // Sau auto-submit → gửi BXH + bắt timer 5s để next
            $this->sendScoreboardToRoom($room_id, $session_id);
    
            // Bắt timer 5s để sang câu tiếp
            // --- Bắt timer 5s để sang câu tiếp ---
            $this->loop->addTimer(5, function() use ($room_id, $session_id) {
                echo "[TIMER FIRED] 5s timer fired at " . microtime(true) . " for room {$room_id}\n";
                $this->nextQuestionSafe($room_id, $session_id);
            });
            

    
            echo "[AUTO] Scheduled next question after 5 seconds.\n";
        });
    
        // 👉 RETURN để lưu vào gameStates!
        return $timer;
    }
    
    
    
        
    protected function sendReadyStatusToRoom($room_id) {
        if (!isset($this->rooms[$room_id])) return;
    
        // Lấy host
        require_once 'app/models/RoomModel.php';
        $roomModel = new \App\Models\RoomModel($this->db);
        $hostInfo = $roomModel->getHostInfo($room_id);
        $host_id = $hostInfo['host_id'] ?? 0;
    
        // Đếm số user (KHÔNG TÍNH HOST)
        $totalUsers = 0;
        $readyUsers = 0;
    
        foreach ($this->rooms[$room_id] as $info) {
            $uid = $info['userId'];
            if ($uid == $host_id) continue; // Bỏ host
    
            $totalUsers++;
            if (!empty($this->readyStates[$room_id][$uid])) {
                $readyUsers++;
            }
        }
    
        // Gửi cho cả phòng
        $message = json_encode([
            'action' => 'updateReadyStatus',
            'readyUsers' => $readyUsers,
            'totalUsers' => $totalUsers,
            'hostId' => $host_id
        ]);
    
        foreach ($this->rooms[$room_id] as $info) {
            $info['conn']->send($message);
        }
    
        echo "[READY STATUS] Room {$room_id} → {$readyUsers}/{$totalUsers} user ready\n";
    }
    
    // Khi nhận được tin nhắn từ client
    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Message from {$from->resourceId}: $msg\n";
        $data = json_decode($msg, true);
        if (!$data || !isset($data['action'])) {
            $from->send(json_encode([
                'action' => 'error',
                'message' => 'Invalid message format.'
            ]));
            return;
        }

        switch ($data['action']) {
            case 'joinRoom':
                $room_id = $data['room_id'] ?? null;
                $userId = $data['userId'] ?? null;
                $userName = $data['userName'] ?? null;
                if (!$room_id || !$userId || !$userName) {
                    $from->send(json_encode([
                        'action' => 'error',
                        'message' => 'room_id, userId, userName là bắt buộc.'
                    ]));
                    return;
                }
                if (!isset($this->rooms[$room_id])) {
                    $this->rooms[$room_id] = [];
                }
                $this->rooms[$room_id][$from->resourceId] = [
                    'conn' => $from,
                    'userId' => $userId,
                    'userName' => $userName
                ];
                echo "[JOIN] User '{$userName}' (ID: {$userId}) đã vào phòng '{$room_id}' (resourceId: {$from->resourceId})\n";
                $this->sendUserListToRoom($room_id);
                break;
            
            case 'leaveRoom':
                $room_id = $data['room_id'];
                $userId = $data['userId'];
                $userName = $data['userName'];

                // Kiểm tra xem người dùng có trong phòng không
                if (isset($this->rooms[$room_id][$from->resourceId])) {
                    unset($this->rooms[$room_id][$from->resourceId]);
                    echo "[LEAVE] User '{$userName}' (ID: {$userId}) đã rời phòng '{$room_id}' (resourceId: {$from->resourceId})\n";
                    // Gửi lại danh sách user mới cho các client còn lại
                    if (empty($this->rooms[$room_id])) {
                        unset($this->rooms[$room_id]);
                        echo "Room '{$room_id}' has been deleted because it is empty.\n";
                    } else {
                        $this->sendUserListToRoom($room_id);                      
                    }

                    // Gửi action leaveRoom về cho chính client vừa rời phòng
                    $from->send(json_encode([
                        'action' => 'leaveRoom',
                        'message' => 'Bạn đã rời phòng.'
                    ]));
                }
                break;
            
                case 'startGame':
                    $room_id = $data['room_id'] ?? null;
                    $host_id = $data['userId'] ?? null;
                
                    if (!$room_id || !$host_id) {
                        $from->send(json_encode([
                            'action' => 'error',
                            'message' => 'Thiếu room_id hoặc host_id để bắt đầu game.'
                        ]));
                        return;
                    }
                
                    // ✅ Lấy host từ DB để kiểm tra
                    require_once 'app/models/RoomModel.php';
                    $roomModel = new \App\Models\RoomModel($this->db);
                    $hostInfo = $roomModel->getHostInfo($room_id);
                    $db_host_id = $hostInfo['host_id'] ?? null;
                
                    if ($host_id != $db_host_id) {
                        $from->send(json_encode([
                            'action' => 'error',
                            'message' => 'Bạn không phải host, không được bắt đầu game.'
                        ]));
                        return;
                    }
                
                    // ✅ Kiểm tra ready
                    $totalUsers = 0;
                    $readyUsers = 0;
                
                    foreach ($this->rooms[$room_id] as $info) {
                        $uid = $info['userId'];
                        if ($uid == $host_id) continue; // Không tính host
                        $totalUsers++;
                        if (!empty($this->readyStates[$room_id][$uid])) {
                            $readyUsers++;
                        }
                    }
                
                    if ($totalUsers === 0) {
                        $from->send(json_encode([
                            'action' => 'error',
                            'message' => 'Không có người chơi nào trong phòng.'
                        ]));
                        return;
                    }
                
                    if ($readyUsers < $totalUsers) {
                        $from->send(json_encode([
                            'action' => 'error',
                            'message' => "Chưa đủ người sẵn sàng ({$readyUsers}/{$totalUsers})."
                        ]));
                        return;
                    }
                
                    // ✅ TỚI ĐÂY MỚI CHO START GAME
                    $this->roomStates[$room_id] = 'playing';
                
                    echo "[START GAME] Host {$host_id} đang bắt đầu game trong phòng {$room_id}\n";
                
                    $stmt = $this->db->prepare("DELETE FROM scoreboard WHERE room_id = ?");
                    $stmt->execute([$room_id]);
                    echo "[START GAME] Đã reset scoreboard của room {$room_id}\n";
                
                    // ✅ Reset gameStates cho room
                    unset($this->gameStates[$room_id]);
                
                    $result = $this->gameController->startGame($room_id, $host_id);
                
                    if ($result['success']) {
                        echo "[START GAME] Game đã bắt đầu trong phòng {$room_id} với session ID: {$result['sessionId']}\n";
                
                        // ✅ Khởi tạo players_answered từ userId trong phòng:
                        $players_answered = [];
                        if (isset($this->rooms[$room_id])) {
                            foreach ($this->rooms[$room_id] as $info) {
                                $userIdInRoom = $info['userId'];
                                $players_answered[$userIdInRoom] = false; // Chưa trả lời
                            }
                        }
                
                        // ✅ Lưu cả questions vào gameStates:
                        $this->gameStates[$room_id] = [
                            'session_id' => $result['sessionId'],
                            'current_question_index' => 0,
                            'questions' => $result['questions'],
                            'players_answered' => $players_answered,
                            'totalPlayers' => count($players_answered),
                            'question_start_time' => time(),
                        ];
                
                        // Gửi gameStarted cho client:
                        $gameStartedMessage = json_encode([
                            'action' => 'gameStarted',
                            'message' => $result['message'],
                            'sessionId' => $result['sessionId'],
                            'questions' => $result['questions'] ?? [],
                            'questionOrder' => $result['questionOrder'] ?? []
                        ]);
                
                        if (isset($this->rooms[$room_id])) {
                            foreach ($this->rooms[$room_id] as $info) {
                                $info['conn']->send($gameStartedMessage);
                            }
                        }
                
                        echo "[START GAME] Đã gửi 'gameStarted' cho phòng {$room_id}\n";
                
                        $this->startNextQuestion($room_id, $result['sessionId']);
                        echo "[START GAME] Đã gọi startNextQuestion cho phòng {$room_id}\n";
                
                    } else {
                        echo "[START GAME] Lỗi khi bắt đầu game trong phòng {$room_id}: {$result['message']}\n";
                        $from->send(json_encode([
                            'action' => 'error',
                            'message' => $result['message']
                        ]));
                    }
                
                    break;
                
                    case 'playerReady':
                        $room_id = $data['room_id'] ?? null;
                        $userId = $data['userId'] ?? null;
                    
                        if (!$room_id || !$userId) {
                            $from->send(json_encode([
                                'action' => 'error',
                                'message' => 'Thiếu dữ liệu playerReady.'
                            ]));
                            return;
                        }
                    
                        if (!isset($this->readyStates[$room_id])) {
                            $this->readyStates[$room_id] = [];
                        }
                    
                        $this->readyStates[$room_id][$userId] = true; // Đánh dấu ready
                    
                        echo "[READY] User {$userId} đã sẵn sàng trong phòng {$room_id}\n";
                    
                        // Gửi cập nhật trạng thái ready cho cả room
                        $this->sendReadyStatusToRoom($room_id);
                        $this->sendUserListToRoom($room_id);
                        break;
                    
                    
                    case 'joinGameSession':
                        $room_id = $data['room_id'] ?? null;
                        $session_id = $data['session_id'] ?? null;
                        $userId = $data['userId'] ?? null;
                        $userName = $data['userName'] ?? null;
                    
                        if (!$room_id || !$session_id || !$userId || !$userName) {
                            $from->send(json_encode([
                                'action' => 'error',
                                'message' => 'Thiếu dữ liệu joinGameSession.'
                            ]));
                            return;
                        }
                    
                        // Gắn lại user vào room (nếu cần)
                        if (!isset($this->rooms[$room_id])) {
                            $this->rooms[$room_id] = [];
                        }
                    
                        $this->rooms[$room_id][$from->resourceId] = [
                            'conn' => $from,
                            'userId' => $userId,
                            'userName' => $userName
                        ];
                    
                        echo "[JOIN GAME] User '{$userName}' (ID: {$userId}) đã vào game session '{$session_id}' trong phòng '{$room_id}' (resourceId: {$from->resourceId})\n";
                    
                        // Gửi xác nhận về client:
                        $from->send(json_encode([
                            'action' => 'joinedGameSession',
                            'message' => 'Đã vào game session thành công.'
                        ]));
                    
                        // Gửi lại câu hỏi hiện tại nếu game đang chơi:
                        $state = $this->gameStates[$room_id] ?? null;
                        if ($state && $this->roomStates[$room_id] === 'playing') {
                            $currentIndex = $state['current_question_index'] ?? 0;
                            $questions = $state['questions'] ?? [];
                            $question = $questions[$currentIndex] ?? null;
                    
                            if ($question) {
                                $from->send(json_encode([
                                    'action' => 'showQuestion',
                                    'question' => $question,
                                    'questionIndex' => $currentIndex,
                                    'timeLimit' => 20 // hoặc tính time còn lại
                                ]));
                    
                                echo "[JOIN GAME] Đã gửi lại câu hỏi hiện tại (index {$currentIndex}) cho user '{$userName}' trong room {$room_id}\n";
                            }
                        }
                    
                        // Update user list
                        $this->sendUserListToRoom($room_id);
                    
                        break;
                    case 'nextQuestion':
                            $room_id = $data['room_id'] ?? null;
                            $userId = $data['userId'] ?? null;
                        
                          
                            require_once 'app/models/RoomModel.php';
                            $roomModel = new \App\Models\RoomModel($this->db);
                            $hostInfo = $roomModel->getHostInfo($room_id);
                            $host_id = $hostInfo['host_id'] ?? null;
                        
                            if ($userId != $host_id) {
                                echo "[NEXT QUESTION] User {$userId} không phải host, không cho phép.\n";
                                return;
                            }
                        
                            echo "[NEXT QUESTION] Host {$userId} yêu cầu next question cho room {$room_id}\n";
                        
                            // Tiếp tục câu tiếp
                            $this->nextQuestionSafe($room_id, $this->gameStates[$room_id]['session_id'] ?? '');
                        
                            break;
                        
                    case 'submitAnswer':
                            $room_id = $data['room_id'] ?? null;
                            $session_id = $data['session_id'] ?? null;
                            $question_id = $data['question_id'] ?? null;
                            $user_id = $data['user_id'] ?? null;
                            $selected_lat = $data['selected_lat'] ?? null;
                            $selected_lng = $data['selected_lng'] ?? null;
                        
                            if (!$room_id || !$session_id || !$question_id || !$user_id || $selected_lat === null || $selected_lng === null) {
                                $from->send(json_encode([
                                    'action' => 'error',
                                    'message' => 'Thiếu dữ liệu submitAnswer.'
                                ]));
                                return;
                            }
                        
                            // --- Lấy correct lat/lng ---
                            require_once 'app/models/QuestionModel.php';
                            $questionModel = new \App\Models\QuestionModel($this->db);
                            $correct = $questionModel->getQuestionLatLng($question_id);
                        
                            if (!$correct) {
                                $from->send(json_encode([
                                    'action' => 'error',
                                    'message' => 'Không tìm thấy câu hỏi.'
                                ]));
                                return;
                            }
                        
                            $correct_lat = $correct['correct_lat'];
                            $correct_lng = $correct['correct_lng'];
                        
                            // --- Tính distance ---
                            $distance = $this->calculateDistance($selected_lat, $selected_lng, $correct_lat, $correct_lng);
                        
                            // --- Tính điểm ---
                            $score = intval(1000 / (1 + $distance));
                        
                            // --- Ghi vào bảng answer ---
                            $stmt = $this->db->prepare("
                                INSERT INTO answer (account_id, session_id, question_id, selected_lat, selected_lng, time_taken, distance)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $user_id, $session_id, $question_id, $selected_lat, $selected_lng, 0, $distance
                            ]);
                        
                            // --- Cập nhật scoreboard ---
                            $stmt2 = $this->db->prepare("
                                INSERT INTO scoreboard (room_id, account_id, total_score)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE total_score = total_score + VALUES(total_score)
                            ");
                            $stmt2->execute([$room_id, $user_id, $score]);
                        
                            echo "[ANSWER] User {$user_id} answered Q{$question_id} - Distance: {$distance} km - Score: {$score}\n";
                        
                            // --- Đánh dấu user đã trả lời ---
                            if (!isset($this->gameStates[$room_id]['players_answered'])) {
                                echo "[ERROR] Không tìm thấy players_answered cho room {$room_id}\n";
                                return;
                            }
                        
                            $this->gameStates[$room_id]['players_answered'][$user_id] = true;
                        
                            // --- Kiểm tra nếu tất cả player đã trả lời ---
                            $allAnswered = true;
                            foreach ($this->gameStates[$room_id]['players_answered'] as $uid => $answered) {
                                if (!$answered) {
                                    $allAnswered = false;
                                    break;
                                }
                            }
                        
                            if ($allAnswered) {
                                echo "[SYNC] Tất cả player đã trả lời. Gửi BXH và chuẩn bị sang câu tiếp theo.\n";
                                
                                if (isset($this->gameStates[$room_id]['autoSubmitTimer'])) {
                                    $this->loop->cancelTimer($this->gameStates[$room_id]['autoSubmitTimer']);

                                    unset($this->gameStates[$room_id]['autoSubmitTimer']);
                                    echo "[SYNC] Đã hủy autoSubmitTimer vì tất cả player đã trả lời.\n";
                                }

                                // --- Gửi BXH ---
                                $this->sendScoreboardToRoom($room_id, $session_id);
                        
                                $this->loop->addTimer(5, function() use ($room_id, $session_id) {
                                    echo "[TIMER FIRED] 5s timer fired at " . microtime(true) . " for room {$room_id}\n";
                                    $this->nextQuestionSafe($room_id, $session_id);
                                });
                                

                        
                                echo "[SYNC] Scheduled next question after 5 seconds.\n";
                            } else {
                                echo "[SYNC] Player {$user_id} đã trả lời. Đang chờ các player khác...\n";
                            }
                        
                            break;
                        
                    
                    
                    

            default:
                $from->send(json_encode([
                    'action' => 'error',
                    'message' => 'Unknown action.'
                ]));

        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error occurred with connection {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    // Gửi danh sách người chơi trong phòng cho tất cả người chơi
    protected function sendUserListToRoom($room_id) {
        if (!isset($this->rooms[$room_id])) return;
    
        require_once 'app/models/RoomModel.php';
        $roomModel = new \App\Models\RoomModel($this->db);
        $hostInfo = $roomModel->getHostInfo($room_id);
        $hostName = $hostInfo['name'] ?? '';
    
        $userList = [];
    
        foreach ($this->rooms[$room_id] as $info) {
            $userId = $info['userId'];
            $userList[] = [
                'userId' => $userId,
                'userName' => $info['userName'],
                'ready' => !empty($this->readyStates[$room_id][$userId]) // true/false
            ];
        }
    
        $userListMessage = json_encode([
            'action' => 'updateUserList',
            'userList' => $userList,
            'hostName' => $hostName
        ]);
    
        foreach ($this->rooms[$room_id] as $info) {
            $info['conn']->send($userListMessage);
        }
    
        echo "[USER LIST] Room {$room_id} → " . count($userList) . " users sent\n";
    }
    
}