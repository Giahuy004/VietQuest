// --- Global ---
let questions = [];
let currentQuestionIndex = 0;
let map, currentMarker, targetMarker, TARGET_LATLNG;
let submitClicked = false;
let countdownElement, countdownInterval;
let progressBarElement, progressInterval; // Biến cho thanh tiến trình và interval của nó
let currentPolyline = null; // Biến để lưu trữ polyline

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
    clearInterval(progressInterval); // Dừng progressInterval khi khởi tạo game
    if (currentPolyline) { // Xóa polyline cũ nếu có
        map.removeLayer(currentPolyline);
        currentPolyline = null;
    }
    if (targetMarker) { // Xóa targetMarker cũ nếu có
        map.removeLayer(targetMarker);
        targetMarker = null;
    }

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
    // Đảm bảo thời gian được truyền vào đây khớp với thời gian bạn muốn cho mỗi câu hỏi
    loadQuestion(questions[currentQuestionIndex], 20); // Thời gian giới hạn 20 giây
}

// --- Initialize Map ---
function initializeMap() {
    map = L.map('map', {
        zoomDelta: 5.0,
        zoomSnap: 0.1
    }).setView([21.026203, 105.83475], 13);

    L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
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

    // Dừng cả hai interval CŨ trước khi bắt đầu cái MỚI
    clearInterval(countdownInterval);
    clearInterval(progressInterval); // Dừng progressInterval cũ

    countdownElement = document.getElementById("countdown-timer");
    countdownElement.classList.add("visible");
    countdownElement.textContent = timeLimit.toString();

    // Lấy element của thanh tiến trình và reset
    progressBarElement = document.getElementById("question-progress-bar");
    if (progressBarElement) {
        progressBarElement.style.width = '100%'; // Đảm bảo bắt đầu từ 100%
        // Đảm bảo transition được kích hoạt nếu có (đã có trong HTML)
    }


    TARGET_LATLNG = [question.correct_lat, question.correct_lng];

    // Update image
    const imgEl = document.getElementById("question-image");
    if (imgEl) {
        // Loại bỏ dấu chấm đầu nếu có
        let imageUrl = question.image_url;
        if (imageUrl.startsWith(".")) {
            imageUrl = imageUrl.slice(1); // Bỏ ký tự đầu tiên
        }

        imgEl.src = "/VietQuest/public" + imageUrl;
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
    if (currentPolyline) { // Xóa polyline cũ nếu có
        map.removeLayer(currentPolyline);
        currentPolyline = null;
    }
    if (targetMarker) { // Xóa targetMarker cũ nếu có
        map.removeLayer(targetMarker);
        targetMarker = null;
    }

    // Start timer và progress bar
    startCountdown(timeLimit); // Truyền timeLimit vào hàm startCountdown
    startProgressBar(timeLimit); // Bắt đầu thanh tiến trình với timeLimit
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

    // CHỈ DỪNG thanh tiến trình khi submit, giữ số đếm vẫn chạy
    clearInterval(progressInterval); // Dừng progressInterval

    const latlng = currentMarker.getLatLng();

    sendAnswer(latlng);
};

// --- Send Answer Helper ---
function sendAnswer(latlng) {
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
            room_id: answer.room_id,
            session_id: answer.session_id,
            question_id: answer.question_id,
            user_id: answer.user_id,
            selected_lat: answer.selected_lat,
            selected_lng: answer.selected_lng,
        })
    );
}

// --- Start Countdown (chỉ cho số đếm) ---
function startCountdown(timeLimit) {
    countdownElement = document.getElementById("countdown-timer");
    countdownElement.classList.add("visible");
    countdownElement.textContent = timeLimit.toString();

    clearInterval(countdownInterval); // Dừng countdownInterval cũ
    let startTime = Date.now();

    countdownInterval = setInterval(() => {
        const elapsed = (Date.now() - startTime) / 1000;
        const remaining = Math.max(0, timeLimit - elapsed);
        countdownElement.textContent = Math.ceil(remaining);

        if (remaining <= 0) {
            clearInterval(countdownInterval); // Dừng số đếm khi hết giờ
            // Không ẩn số đếm, giữ nguyên số trên màn hình

            if (!submitClicked) {
                console.log("Time out! Auto-submitting.");
                autoSubmit(); // Gọi autoSubmit nếu chưa submit
            }
        }
    }, 500); // Cập nhật số đếm mỗi 0.5 giây
}

// --- Start Progress Bar ---
function startProgressBar(timeLimit) {
    progressBarElement = document.getElementById("question-progress-bar"); // Lấy lại element đảm bảo
    if (!progressBarElement) {
        console.error("Progress bar element not found!");
        return;
    }

    // Đảm bảo thanh tiến trình bắt đầu ở 100%
    progressBarElement.style.width = '100%';

    clearInterval(progressInterval); // Dừng progressInterval cũ
    let startTime = Date.now();

    progressInterval = setInterval(() => {
        if (submitClicked) { // Nếu đã submit, dừng thanh tiến trình và không cập nhật nữa
            clearInterval(progressInterval);
            return;
        }

        const elapsed = (Date.now() - startTime) / 1000;
        const remainingPercentage = Math.max(0, (timeLimit - elapsed) / timeLimit) * 100;

        progressBarElement.style.width = `${remainingPercentage}%`;

        // Nếu hết thời gian, hàm autoSubmit sẽ được gọi bởi countdownInterval
        // nên không cần gọi lại autoSubmit ở đây. Chỉ dừng interval.
        if (remainingPercentage <= 0) {
            clearInterval(progressInterval);
        }
    }, 50); // Cập nhật nhanh hơn để thanh tiến trình mượt mà hơn
}


// --- Auto Submit on Timeout ---
function autoSubmit() {
    if (submitClicked) return;
    submitClicked = true;
    submitButton.disabled = true;

    clearInterval(countdownInterval); // Dừng số đếm
    clearInterval(progressInterval); // Dừng thanh tiến trình

    let latlng = { lat: 0, lng: 0 };
    if (currentMarker) {
        latlng = currentMarker.getLatLng();
    }

    console.log("Auto-submitting answer:", latlng);
    sendAnswer(latlng);
}

// --- Show Scoreboard ---
function showScoreboard(scoreboard) {
    clearInterval(countdownInterval);
    clearInterval(progressInterval); // Dừng progressInterval khi show scoreboard

    countdownElement = document.getElementById("countdown-timer");
    countdownElement.classList.remove("visible");

    // Ẩn thanh tiến trình hoặc đặt về 0% khi show scoreboard
    progressBarElement = document.getElementById("question-progress-bar");
    if (progressBarElement) {
        progressBarElement.style.width = '0%'; // Đặt về 0% hoặc ẩn
    }

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
        li.textContent = `#${index + 1} - ${player.userName} - ${player.totalScore} pts`;
        list.appendChild(li);
    });
}

// --- DOM Ready ---
document.addEventListener("DOMContentLoaded", () => {
    // NOTE: Không cần initializeMap() ở đây!
    // Init Game sẽ được gọi khi showGameView gọi initGame()
});