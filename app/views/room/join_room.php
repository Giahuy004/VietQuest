<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'app/views/layouts/header.php'; ?>
<div class="container mt-5">
    <div class="card shadow-sm mx-auto" style="max-width: 400px;">
        <div class="card-body">
            <h2 class="card-title text-center mb-4">Tham gia phòng</h2>
            <form id="joinRoomForm">
                <div class="mb-3">
                    <label for="joinRoomId" class="form-label">Nhập mã phòng:</label>
                    <input type="text" name="joinRoomId" id="joinRoomId" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Tham gia</button>
            </form>
        </div>
    </div>
</div>
<script>
const form = document.getElementById('joinRoomForm');
form.onsubmit = function(e) {
    e.preventDefault();
    const roomId = document.getElementById('joinRoomId').value;
    fetch('/VietQuest/Room/joinRoom', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'roomId=' + encodeURIComponent(roomId)
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