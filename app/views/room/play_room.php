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

    <!-- Image + Submit overlay -->
    <div class="image-container" style="
        position: absolute;
        top: 20px;
        left: 20px;
        z-index: 1000;
        background: rgba(255,255,255,0.9);
        border-radius: 8px;
        padding: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        max-width: 400px;
    ">
        <img id="question-image" src="" alt="Question" style="width: 100%; border-radius: 5px;">
        <button id="submitBtn" class="btn btn-primary mt-2" style="width: 100%;">Submit</button>
    </div>

    <!-- Countdown -->
    <div id="countdown-timer" style="
        position: absolute;
        top: 20px;
        right: 20px;
        z-index: 1000;
        font-size: 2rem;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 10px 20px;
        border-radius: 10px;
    "></div>

<div id="scoreboard-overlay" style="
    display: none;
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 2000;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
">
    <h2>Bảng xếp hạng</h2>
    <ul id="scoreboard-list" style="list-style: none; padding: 0;"></ul>

    <!-- Nút tiếp theo cho host -->
    <button id="next-question-btn" style="margin-top: 20px; display: none;" class="btn btn-success">
        Tiếp tục
    </button>

    <!-- Text cho người chơi khác -->
    <p id="waiting-host-text" style="margin-top: 20px; display: none;">Chờ chủ phòng tiếp tục...</p>

    <!-- Nút Kết thúc -->
<button id="end-btn" class="btn btn-danger mt-3" style="display: none;">Kết thúc</button>

<!-- Nút Chơi lại -->
<button id="play-again-btn" class="btn btn-success mt-3" style="display: none;">Chơi lại</button>
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