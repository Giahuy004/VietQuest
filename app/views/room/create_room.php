<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'app/views/layouts/header.php'; ?>
<div class="container mt-5">
    <div class="card shadow-sm mx-auto" style="max-width: 400px;">
        <div class="card-body">
            <h2 class="card-title text-center mb-4">Tạo phòng mới</h2>
            <form id="createRoomForm">
                <div class="mb-3">
                    <label for="playerCount" class="form-label">
                        Số người chơi tối đa: <span id="playerCountValue" class="fw-bold"></span>
                    </label>
                    <input type="range" class="form-range" name="playerCount" id="playerCount" value="1" min="1" max="20" step="1">
                </div>
                <button type="submit" class="btn btn-primary w-100">Tạo phòng</button>
            </form>
        </div>
    </div>
</div>
<script>
const playerCountInput = document.getElementById('playerCount');
const playerCountValue = document.getElementById('playerCountValue');
playerCountInput.oninput = function() {
    playerCountValue.textContent = playerCountInput.value;
};
playerCountValue.textContent = playerCountInput.value;

const form = document.getElementById('createRoomForm');
form.onsubmit = function(e) {
    e.preventDefault();
    const playerCount = playerCountInput.value;
    fetch('/VietQuest/Room/createRoom', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'playerCount=' + encodeURIComponent(playerCount)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = `/VietQuest/Room/showplayRoom?room=${data.roomId}`;
        } else {
            alert(data.message);
        }
    });
};
</script>
<?php require 'app/views/layouts/footer.php'; ?>