// --- Global ---
let questions = [];
let currentQuestionIndex = 0;
let map, currentMarker, TARGET_LATLNG;
let submitClicked = false;
let countdownElement, countdownInterval;

// --- INIT GAME ---
function initGame() {
  console.log("Initializing game...");

  // --- XÓA MAP CŨ (nếu có) ---
  if (map) {
    map.remove();
    console.log("[MAP] Removed old map instance");
  }

  // --- Reset biến ---
  currentMarker = null;
  TARGET_LATLNG = null;
  submitClicked = false;
  clearInterval(countdownInterval);

  // --- Lấy questions ---
  const questionsData = sessionStorage.getItem("lastQuestions");
  if (!questionsData || questionsData === "undefined") {
    console.warn("No questions found!");
    return;
  }
  questions = JSON.parse(questionsData);
  currentQuestionIndex = 0;

  console.log("Loaded questions:", questions);

  // --- Ẩn scoreboard ---
  const overlay = document.getElementById("scoreboard-overlay");
  overlay.style.display = "none";

  // --- Initialize map ---
  initializeMap();

  // --- Load first question ---
  loadQuestion(questions[currentQuestionIndex], 20);
}

// --- Initialize Map ---
function initializeMap() {
  map = L.map("map", {
    zoomDelta: 5.0,
    zoomSnap: 0.1,
  }).setView([21.026203, 105.83475], 5);

  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap",
  }).addTo(map);

  map.on("click", handleMapClick);
}

// --- Handle Map Click ---
function handleMapClick(e) {
  const latlng = e.latlng;

  if (!submitClicked) {
    if (currentMarker) {
      map.removeLayer(currentMarker);
    }

    currentMarker = L.marker(latlng)
      .addTo(map)
      .bindPopup(
        `Your guess:<br>${latlng.lat.toFixed(4)}, ${latlng.lng.toFixed(4)}`
      )
      .openPopup();
  }
}

// --- Load Question ---
function loadQuestion(question, timeLimit = 10) {
  console.log("Loading question:", question);

  currentQuestionIndex = questions.findIndex(
    (q) => q && q.question_id === question.question_id
  );
  if (currentQuestionIndex === -1) {
    console.warn("Question not found in questions array, adding it.");
    currentQuestionIndex = questions.length;
    questions[currentQuestionIndex] = question;
  }

  clearInterval(countdownInterval);
  countdownElement = document.getElementById("countdown-timer");
  countdownElement.classList.add("visible");
  countdownElement.textContent = timeLimit.toString();

  TARGET_LATLNG = [question.correct_lat, question.correct_lng];

  // Update image
  const imgEl = document.getElementById("question-image");
  if (imgEl) {
    imgEl.src = question.image_url;
  }

  // Reset state
  submitClicked = false;
  const submitButton = document.getElementById("submitBtn");
  submitButton.disabled = false;

  // Reset marker
  if (currentMarker) {
    map.removeLayer(currentMarker);
    currentMarker = null;
  }

  // Start timer
  startCountdown(timeLimit);
}

// --- Submit Button ---
const submitButton = document.getElementById("submitBtn");
submitButton.onclick = function () {
  if (!currentMarker) {
    alert("Vui lòng chọn vị trí trên bản đồ!");
    return;
  }

  if (submitClicked) return;
  submitClicked = true;
  submitButton.disabled = true;
  clearInterval(countdownInterval);
  const latlng = currentMarker.getLatLng();

  const answer = {
    room_id: ROOM_ID,
    session_id: sessionStorage.getItem("lastSessionId"),
    question_id: questions[currentQuestionIndex].question_id,
    user_id: USER_ID,
    selected_lat: latlng.lat,
    selected_lng: latlng.lng,
  };

  console.log("Sending answer:", answer);

  ws.send(
    JSON.stringify({
      action: "submitAnswer",
      room_id: ROOM_ID,
      session_id: answer.session_id,
      question_id: answer.question_id,
      user_id: answer.user_id,
      selected_lat: answer.selected_lat,
      selected_lng: answer.selected_lng,
    })
  );

  // 🚫 Không cần showScoreboard() ở đây → server sẽ gửi updateScoreboard!
};

// --- Start Countdown ---
function startCountdown(timeLimit = 10) {
  countdownElement = document.getElementById("countdown-timer");
  countdownElement.classList.add("visible");
  countdownElement.textContent = timeLimit.toString();

  clearInterval(countdownInterval);
  let startTime = Date.now();

  countdownInterval = setInterval(() => {
    const elapsed = (Date.now() - startTime) / 1000;
    const remaining = Math.max(0, timeLimit - elapsed);
    countdownElement.textContent = Math.ceil(remaining);

    if (remaining <= 0) {
      clearInterval(countdownInterval);
      countdownElement.classList.remove("visible");

      if (!submitClicked) {
        console.log("Time out!");
        autoSubmit();
      }
    }
  }, 500);
}

// --- Auto Submit on Timeout ---
function autoSubmit() {
  if (submitClicked) return;
  submitClicked = true;
  submitButton.disabled = true;

  let latlng = { lat: 0, lng: 0 };
  if (currentMarker) {
    latlng = currentMarker.getLatLng();
  }

  const answer = {
    room_id: ROOM_ID,
    session_id: sessionStorage.getItem("lastSessionId"),
    question_id: questions[currentQuestionIndex].question_id,
    user_id: USER_ID,
    selected_lat: latlng.lat,
    selected_lng: latlng.lng,
  };

  console.log("Auto-submitting answer:", answer);

  ws.send(
    JSON.stringify({
      action: "submitAnswer",
      room_id: ROOM_ID,
      session_id: answer.session_id,
      question_id: answer.question_id,
      user_id: answer.user_id,
      selected_lat: answer.selected_lat,
      selected_lng: answer.selected_lng,
    })
  );

  // 🚫 Không cần showScoreboard() ở đây → server sẽ gửi updateScoreboard!
}

// --- Show Scoreboard ---
function showScoreboard(scoreboard) {
  clearInterval(countdownInterval);
  countdownElement = document.getElementById("countdown-timer");
  countdownElement.classList.remove("visible");
  const overlay = document.getElementById("scoreboard-overlay");
  overlay.style.display = "flex";

  const list = document.getElementById("scoreboard-list");
  list.innerHTML = "";

  if (!scoreboard || scoreboard.length === 0) {
    console.warn("No scoreboard data received!");
    list.innerHTML = "<li>No data</li>";
    return;
  }

  scoreboard.forEach((player, index) => {
    const li = document.createElement("li");
    li.textContent = `#${index + 1} - ${player.userName} - ${
      player.totalScore
    } pts`;
    list.appendChild(li);
  });
}

// --- DOM Ready ---
document.addEventListener("DOMContentLoaded", () => {
  // NOTE: Không cần initializeMap() ở đây!
  // Init Game sẽ được gọi khi showGameView gọi initGame()
});
