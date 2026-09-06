(() => {
    const form = document.getElementById("examForm");
    const config = JSON.parse(document.getElementById("examData").textContent);
    const pageSize = Math.max(1, Number(config.pageSize) || 2);
    const questions = config.questions;
    const totalPages = Math.max(1, Math.ceil(questions.length / pageSize));
    const cards = new Map(Array.from(form.querySelectorAll("[data-question-id]"), (card) => [Number(card.dataset.questionId), card]));
    const answers = new Map();
    const previous = document.getElementById("previousPage");
    const next = document.getElementById("nextPage");
    const lastSubmit = document.getElementById("lastPageSubmit");
    let currentPage = Number(config.currentPage) || 1;
    let posting = false;
    let timerInterval = null;
    const deadline = Date.now() + Number(config.timeRemaining) * 1000;

    function updateAnswers() {
        answers.clear();
        form.querySelectorAll('input[type="radio"]:checked').forEach((input) => {
            answers.set(Number(input.name.substring("question_".length)), Number(input.value));
        });
        document.getElementById("answeredQuestionCount").textContent = answers.size;
        document.getElementById("unansweredQuestionCount").textContent = questions.length - answers.size;
    }

    function showPage(page) {
        currentPage = Math.max(1, Math.min(page, totalPages));
        const offset = (currentPage - 1) * pageSize;
        const visibleIds = new Set(questions.slice(offset, offset + pageSize).map((question) => Number(question.id)));
        cards.forEach((card, id) => {
            card.hidden = !visibleIds.has(id);
        });
        form.elements.current_page.value = currentPage;
        form.elements.total_pages.value = totalPages;
        document.getElementById("pageLabel").textContent = `Page ${currentPage} of ${totalPages}`;
        document.getElementById("questionPageLabel").textContent = `Page ${currentPage} of ${totalPages}`;
        previous.style.visibility = currentPage === 1 ? "hidden" : "visible";
        next.hidden = currentPage === totalPages;
        lastSubmit.hidden = currentPage !== totalPages;
    }

    previous.addEventListener("click", () => {
        showPage(currentPage - 1);
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
    form.addEventListener("change", updateAnswers);
    document.getElementById("submitModal").addEventListener("show.bs.modal", updateAnswers);
    form.addEventListener("submit", (event) => {
        if (posting) {
            event.preventDefault();
            return;
        }
        // Hidden pages remain enabled, so the normal POST includes every selected answer.
        updateAnswers();
        posting = true;
        clearInterval(timerInterval);
    });

    function updateTimer() {
        const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        const hours = Math.floor(remaining / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);
        const seconds = remaining % 60;
        document.getElementById("timerText").textContent = [hours, minutes, seconds].map((value) => String(value).padStart(2, "0")).join(":");
        const progress = Math.max(0, Math.min(100, (remaining / Math.max(1, Number(config.timeLimit))) * 100));
        const progressBar = document.getElementById("timerProgress");
        progressBar.style.width = progress + "%";
        progressBar.className = "progress-timer-bar" + (progress < 20 ? " danger" : progress < 50 ? " warning" : "");
        document.getElementById("timerDisplay").classList.toggle("warning", progress < 20);
        if (remaining === 0 && !posting) {
            posting = true;
            clearInterval(timerInterval);
            updateAnswers();
            form.submit();
        }
    }

    window.addEventListener("pageshow", (event) => {
        if (event.persisted) {
            posting = false;
            clearInterval(timerInterval);
            timerInterval = setInterval(updateTimer, 1000);
            updateTimer();
        }
    });
    updateAnswers();
    showPage(currentPage);
    timerInterval = setInterval(updateTimer, 1000);
    updateTimer();
})();
