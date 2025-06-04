// --- Global ---
const userId = USER_ID;
const userName = USER_NAME;
let isReady = false;

const roomId = urlParams.get("room") || urlParams.get("room_id") || "";
const readyBtn = document.getElementById("readyBtn");
const readyStatus = document.getElementById("ready-status");
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
      // Reset trạng thái sẵn sàng
      isReady = false;
      if (readyBtn) {
        readyBtn.textContent = "Sẵn sàng";
        readyBtn.classList.remove("btn-success");
        readyBtn.classList.add("btn-warning");
        readyStatus.textContent = "";
      }

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
    case "updateReadyStatus":
      console.log(
        "Ready status update:",
        data.readyUsers,
        "/",
        data.totalUsers
      );

      if (readyStatus) {
        const startBtn = document.getElementById("startBtn");

        if (IS_HOST) {
          // Nếu là host
          if (data.totalUsers === 0) {
            startBtn.disabled = true;
            readyStatus.textContent = "Chưa có người chơi nào trong phòng.";
          } else if (data.readyUsers === data.totalUsers) {
            startBtn.disabled = false;
            readyStatus.textContent =
              "Tất cả người chơi đã sẵn sàng. Bạn có thể bắt đầu!";
          } else {
            startBtn.disabled = true;
            readyStatus.textContent = `${data.readyUsers}/${data.totalUsers} người chơi đã sẵn sàng...`;
          }
        } else {
          // User thấy trạng thái
          readyStatus.textContent = `${data.readyUsers}/${data.totalUsers} người chơi đã sẵn sàng...`;
        }
      } else {
        console.warn("Không tìm thấy ready-status");
      }

      break;

    default:
      console.warn("Unknown action:", data.action);
      break;
  }
};
if (readyBtn) {
  readyBtn.onclick = function () {
    isReady = true;
    ws.send(
      JSON.stringify({
        action: "playerReady",
        room_id: roomId,
        userId: userId,
        userName: userName,
      })
    );

    // Cập nhật giao diện ngay (UX)
    readyBtn.disabled = true;
    readyBtn.innerText = "Đã sẵn sàng";
  };
} else {
  console.warn("Không tìm thấy nút readyBtn");
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
function updateUserList(userList, hostName) {
  const userListElement = document.getElementById("userListTable");
  userListElement.innerHTML = "";

  // Tiêu đề 2 cột:
  const header = document.createElement("li");
  header.className = "list-group-item d-flex justify-content-between fw-bold";
  header.innerHTML = `
        <span>Tên người chơi</span>
        <span>Trạng thái</span>
    `;
  userListElement.appendChild(header);

  userList.forEach((user) => {
    const tr = document.createElement("tr");

    const isHost = user.userName === hostName;
    const isReady = user.ready || isHost; // ✅ Đúng key server gửi là 'ready'

    tr.innerHTML = `
            <td>${user.userName} ${isHost ? "(Chủ phòng)" : ""}</td>
            <td class="${isReady ? "text-success" : "text-secondary"}">
                ${isReady ? "Sẵn sàng" : "Chưa sẵn sàng"}
            </td>
        `;
    userListElement.appendChild(tr);
  });
  const readyBtn = document.getElementById("readyBtn");
  if (readyBtn) {
    if (USER_NAME === hostName) {
      // Host → ẩn hoặc disable
      // readyBtn.classList.add('d-none'); // nếu muốn ẩn
      readyBtn.disabled = true;
      readyBtn.innerText = "Bạn là chủ phòng";
    } else {
      readyBtn.disabled = false;
      readyBtn.classList.remove("d-none");
      readyBtn.innerText = isReady ? "Đã sẵn sàng" : "Sẵn sàng";
    }
  }
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

// --- Start Game ---
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
