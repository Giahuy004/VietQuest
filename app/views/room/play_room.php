<?php
require 'app/views/layouts/header.php'; ?>
<!-- Room View -->
<!-- Room View -->
<div id="roomView">
    <div class="container mt-5">
        <div class="card shadow-sm mx-auto" style="max-width: 600px;">
            <div class="card-body">
                <h2 class="card-title text-center mb-3 text-primary">
                    <i class="bi bi-door-open"></i> Phòng: <span id="roomId"></span>
                </h2>
                <p><strong>Chủ phòng:</strong> <span class="text-success"><?= htmlspecialchars($hostName) ?></span></p>
                <p><strong>Số người tối đa:</strong> <span class="badge bg-info"><?= htmlspecialchars($maxPlayers) ?></span></p>
                <h4 class="mt-4">Danh sách người chơi:</h4>
                <ul id="userList" class="list-group mb-3"></ul>

                <div class="d-flex justify-content-between mt-4">
                    <?php if ($_SESSION['user_name'] === $hostName): ?>
                        <button id="startBtn" class="btn btn-success px-4">
                            <i class="bi bi-play-fill"></i> Bắt đầu
                        </button>
                    <?php endif; ?>
                    <button id="leaveBtn" class="btn btn-outline-danger px-4">
                        <i class="bi bi-box-arrow-left"></i> Rời phòng
                    </button>
                </div>

            </div>
        </div>

        
    </div>
</div>

<!-- Game View (Full screen) -->
<div id="gameView" style="display: none; position: relative; height: 100vh; width: 100vw;">

    <!-- Map full screen -->
    <div id="map" style="position: absolute; top: 0; left: 0; height: 100%; width: 100%; z-index: 1;"></div>

    <div class="image-container" style="
    position: absolute;
    top: 100px;
    left: 100px;
    z-index: 1000;
    background: rgba(255,255,255,0.9);
    border-radius: 8px;
    padding: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    max-width: 450px;     
">
    <img id="question-image" src="" alt="Question" style="width: 100%; height: 350px; border-radius: 5px;">
                <!-- Progress bar -->
        <div class="progress-bar-bg" style="
            width: 100%;
            height: 6px;
            background-color: #ddd;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        ">
            <div id="question-progress-bar" style="
                height: 100%;
                width: 100%;
                background: linear-gradient(to right, #00cfff, #00e6a8, #ffe066, #ff9a8b, #c17fff);
                transition: width linear;
            "></div>
        </div>

        <button id="submitBtn" class="btn btn-primary mt-2" style="width: 100%;">Submit</button>
    </div>

    <!-- Countdown -->
    <div id="countdown-timer" style="
        position: absolute;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1000;
        font-size: 3rem;
        font-weight: bold;
        color: #fff;
        background: rgba(0,0,0,0.7);
        padding: 10px 20px;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        display: none;
    ">0</div>

<!-- Overlay dùng chung -->
<div id="overlay-layer" style="
    display: none;
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.85);
    z-index: 2000;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 20px;
">

    
    <style>
        /* Overlay mô tả */
        #description-overlay {
        display: none;
        position: fixed;
        top: 40%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1001;
        text-align: center;
        font-size: 2.5rem;
        color: #ffffff;
        text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.6);
        padding: 24px 36px;
        border-radius: 12px;
        background-color: rgba(0, 0, 0, 0.75);
        width: 80%;
        max-width: 600px;
        animation: fadeIn 0.4s ease-out;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }

        #description-overlay h3 {
        margin-bottom: 0.5rem;
        margin-top: 0.7rem;
        font-size: 2.5rem;
        font-weight: 600;
        color: #fbea02;
        }

        #desc-text {
        display: inline-block;
        text-align: left;
        white-space: pre-wrap;
        overflow: hidden;
        }

        @keyframes fadeIn {
        from { opacity: 0; transform: translate(-50%, -55%); }
        to { opacity: 1; transform: translate(-50%, -50%); }
        }
    </style>

    <!-- Mô tả địa điểm -->
    <div id="description-overlay" style="display: none; max-width: 700px;">
        <h3>Mô tả địa điểm</h3>
        <div id="desc-text"></div>
    </div>


    <style>
        /* Bảng xếp hạng */
        #scoreboard-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none; /* Luôn giữ là 'none', JS sẽ điều khiển display: flex */
            justify-content: center;
            align-items: center;
            z-index: 1100;
            backdrop-filter: blur(5px); /* Hiệu ứng làm mờ nền */
        }

        .scoreboard-content {
            background: linear-gradient(135deg, #f0f4f8, #e6e9ed);
            padding: 40px 50px 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            text-align: center;
            width: 95%; /* Chiều rộng tương đối */
            max-width: 700px; /* Chiều rộng tối đa */
            max-height: 90vh; /* Chiều cao tối đa, để có thể cuộn trên màn hình nhỏ */
            overflow-y: auto; /* Bật cuộn dọc nếu nội dung tràn */
            animation: fadeInScoreboard 0.6s ease forwards; /* Hoạt ảnh xuất hiện */
            -webkit-overflow-scrolling: touch; /* Cuộn mượt trên iOS */
            margin-left: 25vw;
            margin-top: 100px;

            /* Tùy chỉnh thanh cuộn cho .scoreboard-content */
            scrollbar-width: thin; /* Firefox */
            scrollbar-color: rgba(0, 0, 0, 0.3) rgba(0, 0, 0, 0.1); /* Firefox */
        }

        /* Webkit browsers (Chrome, Safari) scrollbar styling */
        .scoreboard-content::-webkit-scrollbar {
            width: 8px;
        }
        .scoreboard-content::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .scoreboard-content::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
        }
        .scoreboard-content::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.5);
        }


        @keyframes fadeInScoreboard {
            0%    { opacity: 0; transform: translateY(-50px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .scoreboard-content h2 {
            margin-bottom: 30px;
            /* Kích thước chữ responsive cho tiêu đề */
            font-size: clamp(2em, 5vw, 2.8em); /* Tối thiểu 2em, lý tưởng 5vw, tối đa 2.8em */
            color: #222;
            letter-spacing: 1px;
        }

        #scoreboard-list {
            list-style: none;
            padding: 0;
            margin: 0;
            color: #444;
        }

        #scoreboard-list li {
            margin: 12px 0;
            padding: 12px 18px;
            background: #fff;
            border-radius: 10px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            border-left: 6px solid #a8dadc;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            gap: 10px;
            /* Kích thước chữ responsive cho mỗi hàng */
            font-size: clamp(1.1em, 2.2vw, 1.3em); /* Tối thiểu 1.1em, lý tưởng 2.2vw, tối đa 1.3em */
            transition: all 0.3s ease; /* Thêm transition cho các hiệu ứng hover/active */
        }

        #scoreboard-list li:nth-child(even) {
            background: #f9fcfd;
            border-left-color: #f7b2bd;
        }

        /* Kiểu dáng đặc biệt cho người đứng đầu (hàng thứ nhất) */
        #scoreboard-list li:nth-child(1) {
            background: linear-gradient(45deg, #ffe0b2, #ffd580); /* Nền vàng cam */
            color: #8b4513; /* Màu chữ nâu sẫm */
            font-weight: 700;
            border-left-color: #ffa000; /* Viền vàng cam đậm */
            box-shadow: 0 4px 15px rgba(255, 165, 0, 0.4); /* Bóng nổi bật hơn */
            transform: scale(1.03); /* Phóng to nhẹ để nhấn mạnh */
            z-index: 1; /* Đảm bảo nó luôn ở trên cùng về mặt trực quan */
        }

        /* Kiểu dáng cho hàng của người chơi hiện tại (cần thêm class này bằng JS) */
        #scoreboard-list li.current-player {
            border: 3px solid #007bff; /* Viền xanh nổi bật */
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.5); /* Bóng phát sáng */
            background: #e0f2ff; /* Nền xanh nhạt hơn */
            font-weight: bold;
            transform: scale(1.01); /* Phóng to nhẹ */
            transition: all 0.3s ease-in-out; /* Chuyển động mượt mà khi được highlight */
        }


        .rank-label {
            font-weight: bold;
            width: 35px; /* Độ rộng cố định cho nhãn thứ hạng */
            text-align: center;
            flex-shrink: 0; /* Ngăn không cho co lại */
        }

        @keyframes rankUpAnimation {
            0%    { background-color: #d4edda; }
            50%   { background-color: #a8e6a1; }
            100% { background-color: transparent; }
        }

        @keyframes rankDownAnimation {
            0%    { background-color: #f8d7da; }
            50%   { background-color: #f4a3a7; }
            100% { background-color: transparent; }
        }

        .rank-up {
            animation: rankUpAnimation 1.5s ease;
        }

        .rank-down {
            animation: rankDownAnimation 1.5s ease;
        }

        .player-name {
            flex: 1; /* Chiếm hết không gian còn lại */
            text-align: left;
            white-space: nowrap; /* Ngăn tên bị xuống dòng */
            overflow: hidden; /* Ẩn phần tên tràn */
            text-overflow: ellipsis; /* Thêm dấu ba chấm nếu tên quá dài */
        }

        .round-score {
            font-weight: bold;
            font-size: 1.1em;
            padding: 4px 10px;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            min-width: 60px; /* Độ rộng tối thiểu cho điểm */
            text-align: center;
            flex-shrink: 0; /* Ngăn không cho co lại */
        }

        .round-score.positive {
            color: #28a745; /* Xanh lá cây */
            background: #d4edda; /* Nền xanh lá cây nhạt */
        }

        .round-score.zero {
            color: #999; /* Xám */
            background: #f1f1f1; /* Nền xám nhạt */
        }

        /* Kiểu dáng cho các nút bên trong scoreboard-content */
        .scoreboard-content .btn {
            margin-top: 15px;
            padding: 10px 20px;
            font-size: 1em;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .scoreboard-content .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }

        .scoreboard-content .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
        }

        .scoreboard-content .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }

        .scoreboard-content .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
        }

        /* Điều chỉnh cho màn hình nhỏ hơn (responsive) */
        @media (max-width: 768px) {
            .scoreboard-content {
                padding: 25px 25px 20px;
            }
            .scoreboard-content h2 {
                font-size: clamp(1.8em, 7vw, 2.2em); /* Điều chỉnh kích thước chữ tiêu đề */
                margin-bottom: 20px;
            }
            #scoreboard-list li {
                font-size: clamp(0.9em, 2.8vw, 1.1em); /* Điều chỉnh kích thước chữ hàng */
                padding: 10px 15px;
                gap: 8px;
            }
            .rank-label {
                width: 30px;
            }
            .round-score {
                min-width: 50px;
                font-size: 1em;
            }
            .player-name {
                font-size: clamp(0.9em, 2.5vw, 1.1em);
            }
        }

    </style>

    
    <div id="scoreboard-overlay" style="display: none;"> <div class="scoreboard-content"> <h2>Bảng xếp hạng</h2>
        <ul id="scoreboard-list"></ul>

        <div class="scoreboard-actions mt-4"> 
            <button id="next-question-btn" class="btn btn-success" style="display: none;">Tiếp tục</button>

            <p id="waiting-host-text" style="display: none;">Chờ chủ phòng tiếp tục...</p>

            <button id="end-btn" class="btn btn-danger">Kết thúc</button>

            <button id="play-again-btn" class="btn btn-success">Chơi lại</button>
        </div>
    </div>
</div>

</div>



</div>


<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>



<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.js"></script>
<script>
    const USER_ID = <?php echo json_encode($_SESSION['account_id'] ?? ''); ?>;
    const USER_NAME = <?php echo json_encode($_SESSION['user_name'] ?? ''); ?>;
    const urlParams = new URLSearchParams(window.location.search);
    const ROOM_ID = urlParams.get('room');
    const IS_HOST = <?php echo ($_SESSION['user_name'] === $hostName) ? 'true' : 'false'; ?>;
</script>

<script src="/VietQuest/public/js/playRoom.js"></script>
<script src="/VietQuest/public/js/game.js"></script>

<?php require 'app/views/layouts/footer.php'; ?>