// --- Global ---
const userId = USER_ID;
const userName = USER_NAME;
const roomId = urlParams.get("room") || urlParams.get("room_id") || "";
const ws = new WebSocket("ws://localhost:8080/socket");
const scoreboardOverlay = document.getElementById("scoreboard-overlay");
const scoreboardList = document.getElementById("scoreboard-list");
const nextBtn = document.getElementById("next-question-btn");
const waitingText = document.getElementById("waiting-host-text");
const endBtn = document.getElementById("end-btn");
const playAgainBtn = document.getElementById("play-again-btn");
let isGameOver = false;

// --- WebSocket handlers ---
ws.onopen = function () {
  ws.send(
    JSON.stringify({
      action: "joinRoom",
      room_id: roomId,
      userId: userId,
      userName: userName,
    })
  );
};

ws.onmessage = function (event) {
  console.log("Received message from server:", event.data);
  const data = JSON.parse(event.data);

  switch (data.action) {
    case "connected":
      alert(data.message);
      break;

    case "joinRoom":
      alert(data.message);
      break;

    case "updateUserList":
      updateUserList(data.userList, data.hostName);
      break;

    case "leaveRoom":
      ws.close();
      window.location.href = "/VietQuest/home";
      break;

    case "gameStarted":
      console.log("Game started!");
      

      nextBtn.style.display = "none";
      endBtn.style.display = "none";
      playAgainBtn.style.display = "none";

      // Xóa BXH cũ
      scoreboardList.innerHTML = "";

      // Xóa countdown nếu có (optional)
      const countdownElement = document.getElementById(
        "next-question-countdown"
      );
      if (countdownElement) countdownElement.textContent = "";

      // Xóa session storage nếu muốn "fresh"
      sessionStorage.removeItem("lastQuestions");
      // Save data
      sessionStorage.setItem("lastRoomId", roomId);
      sessionStorage.setItem("lastSessionId", data.sessionId);
      sessionStorage.setItem("lastQuestions", JSON.stringify(data.questions));

      // Push URL
      history.pushState(
        {},
        "",
        "/VietQuest/game?room_id=" +
          encodeURIComponent(roomId) +
          "&session_id=" +
          encodeURIComponent(data.sessionId)
      );

      // Show game view
      showGameView();

      // Init game if available
      if (typeof initGame === "function") {
        initGame();
      }
      break;

    case "joinedGameSession":
      console.log("Joined game session successfully.");
      break;

      case "updateScoreboard":
    console.log("Scoreboard update:", data.scoreboard);

    // Cập nhật danh sách BXH (chưa show ngay)
    scoreboardList.innerHTML = "";
    data.scoreboard.forEach((player) => {
        const li = document.createElement("li");
        li.textContent = `#${data.scoreboard.indexOf(player) + 1} - ${player.userName} - ${player.totalScore} pts`;
        scoreboardList.appendChild(li);
    });

    // Lấy description từ câu hỏi hiện tại
    const descriptionText = (questions && questions[currentQuestionIndex] && questions[currentQuestionIndex].description) || "Đây là mô tả địa điểm!";

    // --- Flow: showResult → showDescription → showScoreboard ---
    showResult(() => {
        showDescription(descriptionText, () => {
            // --- Giữ nguyên code show BXH ---
            const overlayLayer = document.getElementById('overlay-layer');
            const descOverlay = document.getElementById('description-overlay');
            const scoreboardOverlay = document.getElementById('scoreboard-overlay');

            overlayLayer.style.display = 'flex';
            descOverlay.style.display = 'none';
            scoreboardOverlay.style.display = 'block';

            // Kiểm tra có phải câu cuối không:
            if (data.isLastQuestion) {
                console.log("This is the last question - showing End + Play Again buttons.");

                nextBtn.style.display = "none";
                waitingText.style.display = "none";

                endBtn.style.display = "inline-block";
                playAgainBtn.style.display = "inline-block";
            } else {
                if (data.isHost) {
                    nextBtn.style.display = "block";
                    waitingText.style.display = "none";
                } else {
                    nextBtn.style.display = "none";
                    waitingText.style.display = "block";
                }

                endBtn.style.display = "none";
                playAgainBtn.style.display = "none";
            }
        });
    });

    break;

    

    case "showQuestion":
      console.log("Show question:", data);
      // Ẩn overlay
      scoreboardOverlay.style.display = "none";
      const overlayLayer = document.getElementById('overlay-layer');
      if (overlayLayer) overlayLayer.style.display = 'none';
      questions = questions || [];
      currentQuestionIndex = data.questionIndex;
      questions[currentQuestionIndex] = data.question;
      if (typeof loadQuestion === "function") {
        loadQuestion(data.question, data.timeLimit);
        startProgressBar(data.timeLimit);
    }
      break;
    default:
      console.warn("Unknown action:", data.action);
      break;
  }
};
function calculateRealDistance(latlng1, latlng2) {
  const R = 6371;
  const toRad = (value) => value * Math.PI / 180;

  const lat1 = toRad(latlng1.lat);
  const lon1 = toRad(latlng1.lng);
  const lat2 = toRad(latlng2.lat);
  const lon2 = toRad(latlng2.lng);

  const dLat = lat2 - lat1;
  const dLon = lon2 - lon1;

  const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  const distance = R * c;
  return distance.toFixed(1);
}
function showResult(onFinishCallback) {
  // Lấy dữ liệu câu hỏi hiện tại
  const question = questions[currentQuestionIndex];
  if (!question) {
      console.warn("No question found for currentQuestionIndex:", currentQuestionIndex);
      if (typeof onFinishCallback === 'function') {
          onFinishCallback();
      }
      return;
  }

  // Tọa độ đích
  const targetLatLng = [question.correct_lat, question.correct_lng];
  const destinationIcon = L.icon({
    iconUrl: '/VietQuest/public/images/marker/target.png',
    iconSize: [40, 40],      // tùy chỉnh kích thước icon (VD: 40x40 px)
    iconAnchor: [20, 40],    // điểm neo (trung tâm đáy)
    popupAnchor: [0, -40]    // vị trí popup hiển thị
  });

  // Thêm marker đích
  const targetMarker = L.marker(targetLatLng, { icon: destinationIcon }).addTo(map);

  // Tọa độ người chơi chọn
  const userLatLng = currentMarker?.getLatLng?.() ?? targetLatLng;

  // Tính khoảng cách
  const realDistance = calculateRealDistance(userLatLng, targetMarker.getLatLng());

  // Xóa polyline cũ nếu có
  if (typeof polyline !== 'undefined' && polyline) {
      map.removeLayer(polyline);
  }

  // Vẽ line từ user → target
  polyline = L.polyline([userLatLng, targetMarker.getLatLng()], {
      color: 'black',
      weight: 4,
      opacity: 1,
      dashArray: '10,15'
  }).addTo(map);
  polyline.bindPopup(realDistance + " km").openPopup();

  // Zoom đến đích
  map.flyTo(targetLatLng, 17, { duration: 2 });

  // Khi zoom xong → gọi callback
  map.once('moveend', function () {
      if (typeof onFinishCallback === 'function') {
          onFinishCallback();
      }
  });
}

function showDescription(descriptionText, onFinishCallback) {
  const overlayLayer = document.getElementById('overlay-layer');
  const descOverlay = document.getElementById('description-overlay');
  const scoreboardOverlay = document.getElementById('scoreboard-overlay');

  // Reset state
  overlayLayer.style.display = 'flex';
  descOverlay.style.display = 'block';
  scoreboardOverlay.style.display = 'none';

  document.getElementById('desc-text').textContent = descriptionText;

  // Sau vài giây tự động chuyển sang BXH (VD: 4 giây)
  setTimeout(() => {
      if (typeof onFinishCallback === 'function') {
          onFinishCallback();
      }
  }, 4000);
}


function showScoreboard() {
    const overlayLayer = document.getElementById('overlay-layer');
    const descOverlay = document.getElementById('description-overlay');
    const scoreboardOverlay = document.getElementById('scoreboard-overlay');

    overlayLayer.style.display = 'flex';
    descOverlay.style.display = 'none';
    scoreboardOverlay.style.display = 'block';
}

nextBtn.onclick = function () {
  console.log("Host clicked NEXT → sending nextQuestion...");
  ws.send(
    JSON.stringify({
      action: "nextQuestion",
      room_id: roomId,
      userId: userId,
      userName: userName,
    })
  );

  // Ẩn overlay ngay (hoặc có thể đợi server gửi showQuestion)
  scoreboardOverlay.style.display = "none";
};
// --- UI helpers ---

function startProgressBar(durationSeconds) {
  const progressBar = document.getElementById('question-progress-bar');
  const countdownElement = document.getElementById('countdown-timer');

  // Reset progress bar
  progressBar.style.transition = 'none';
  progressBar.style.width = '100%';

  // Reset countdown
  clearInterval(countdownInterval);
  countdownElement.style.display = 'block';
  countdownElement.textContent = durationSeconds;

  // Bắt đầu animate progress
  setTimeout(() => {
      progressBar.style.transition = `width ${durationSeconds}s linear`;
      progressBar.style.width = '0%';
  }, 50);

  // Bắt đầu countdown số giây
  let timeLeft = durationSeconds;
  countdownInterval = setInterval(() => {   // <-- KHÔNG có "let" ở đây !!!
      timeLeft--;
      if (timeLeft >= 0) {
          countdownElement.textContent = timeLeft;
      } else {
          clearInterval(countdownInterval);
          countdownElement.style.display = 'none';
      }
  }, 1000);
}


function updateUserList(userList, hostName) {
  const userListElement = document.getElementById('userList');
  userListElement.innerHTML = '';

  // Tiêu đề 2 cột:
  const header = document.createElement('li');
  header.className = 'list-group-item d-flex justify-content-between fw-bold';
  header.innerHTML = `
      <span>Tên người chơi</span>
      <span>Vai trò</span>
  `;
  userListElement.appendChild(header);

  userList.forEach(user => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between';

      const isHost = (user.userName === hostName);

      li.innerHTML = `
          <span>${user.userName}</span>
          <span>${isHost ? 'Chủ phòng' : 'Người chơi'}</span>
      `;
      userListElement.appendChild(li);
  });

}


function showRoomView() {
  document.getElementById("roomView").style.display = "block";
  document.getElementById("gameView").style.display = "none";

  history.pushState(
    {},
    "",
    "/VietQuest/room?room=" + encodeURIComponent(roomId)
  );
}

function showGameView() {
  document.getElementById("roomView").style.display = "none";
  document.getElementById("gameView").style.display = "block";

  const lastSessionId = sessionStorage.getItem("lastSessionId");
  if (ws && ws.readyState === WebSocket.OPEN) {
    ws.send(
      JSON.stringify({
        action: "joinGameSession",
        room_id: roomId,
        session_id: lastSessionId,
        userId: userId,
        userName: userName,
      })
    );
  }
}
// --- End Game ---
endBtn.onclick = function () {
  window.location.href = "/VietQuest/home";
};

// --- Play Again ---
playAgainBtn.onclick = function () {
  // Ẩn game view, show room view
  showRoomView();

  // Reset overlay
  scoreboardOverlay.style.display = "none";
};
// --- Events ---
document.getElementById("leaveBtn").onclick = function () {
  if (confirm("Bạn có chắc chắn muốn rời phòng?")) {
    fetch("/VietQuest/Room/leaveRoom", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body:
        "roomId=" +
        encodeURIComponent(roomId) +
        "&userId=" +
        encodeURIComponent(userId),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success && ws.readyState === WebSocket.OPEN) {
          ws.send(
            JSON.stringify({
              action: "leaveRoom",
              room_id: roomId,
              userId: userId,
              userName: userName,
            })
          );
        }
      });
  }
};

if (typeof IS_HOST !== "undefined" && IS_HOST) {
  document.getElementById("startBtn").onclick = function () {
    ws.send(
      JSON.stringify({
        action: "startGame",
        room_id: roomId,
        userId: userId,
        userName: userName,
      })
    );
  };
}

// --- Init ---
document.addEventListener("DOMContentLoaded", () => {
  // Auto show correct view
  if (window.location.pathname.includes("/game")) {
    showGameView();
    if (typeof initGame === "function") {
      initGame();
    }
  } else {
    showRoomView();
  }

  // Set roomId text
  const roomIdElement = document.getElementById("roomId");
  if (roomIdElement) {
    roomIdElement.textContent = roomId;
  }
});
