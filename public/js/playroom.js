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

      scoreboardList.innerHTML = "";

      data.scoreboard.forEach((player) => {
        const li = document.createElement("li");
        li.textContent = `#${data.scoreboard.indexOf(player) + 1} - ${
          player.userName
        } - ${player.totalScore} pts`;
        scoreboardList.appendChild(li);
      });

      // Hiện overlay
      scoreboardOverlay.style.display = "flex";

      // Kiểm tra có phải câu cuối không:
      if (data.isLastQuestion) {
        console.log(
          "This is the last question - showing End + Play Again buttons."
        );

        // ❌ Ẩn nút tiếp tục
        nextBtn.style.display = "none";
        waitingText.style.display = "none";

        // ✅ Show Kết thúc + Chơi lại
        endBtn.style.display = "inline-block";
        playAgainBtn.style.display = "inline-block";
      } else {
        // Nếu chưa phải câu cuối → bình thường
        if (data.isHost) {
          nextBtn.style.display = "block";
          waitingText.style.display = "none";
        } else {
          nextBtn.style.display = "none";
          waitingText.style.display = "block";
        }

        // Ẩn End + Play Again (để tránh lỗi nếu vừa chơi lại)
        endBtn.style.display = "none";
        playAgainBtn.style.display = "none";
      }

      break;

    case "showQuestion":
      console.log("Show question:", data);
      // Ẩn overlay
      scoreboardOverlay.style.display = "none";
      questions = questions || [];
      currentQuestionIndex = data.questionIndex;
      questions[currentQuestionIndex] = data.question;
      if (typeof loadQuestion === "function") {
        loadQuestion(data.question, data.timeLimit);
      }
      break;
    default:
      console.warn("Unknown action:", data.action);
      break;
  }
};

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
