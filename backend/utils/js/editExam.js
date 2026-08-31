let questions = [];
const removedDraftQuestionIds = new Set();

if (Array.isArray(examQuestions)) {
    questions = examQuestions.map((q) => ({
        id: q.id,
        question_text: q.question,
        question_mark: q.question_mark || 10,
        choices: examQuestionsChoices[q.id] || [],
        type: q.type,
        is_generated: false,
        is_draft: true,
    }));
}
// TAB NAVIGATION
function activateTab(id) {
    const trigger = document.getElementById(id);
    document.getElementById("confirmTitle").innerHTML = document.getElementById("examTitle").value ?? "-";
    document.getElementById("confirmStart").innerHTML = document.getElementById("startDate").value ?? "-";
    document.getElementById("confirmEnd").innerHTML = document.getElementById("endDate").value ?? "-";

    bootstrap.Tab.getOrCreateInstance(trigger).show();
}

document.getElementById("toQuestionsBtn").addEventListener("click", function () {
    const title = document.getElementById("examTitle").value.trim();
    const start = document.getElementById("startDate").value;
    const end = document.getElementById("endDate").value;
    const marks = document.getElementById("totalMarks").value;

    if (!title || !start || !end || !marks || parseInt(marks) <= 0) {
        alert("Please fill in all required exam details first.");
        return;
    }

    activateTab("questions-tab");
});

document.getElementById("finalSubmitBtn").addEventListener("click", function (event) {
    const title = document.getElementById("examTitle").value.trim();
    const start = document.getElementById("startDate").value;
    const end = document.getElementById("endDate").value;
    const marks = document.getElementById("totalMarks").value;

    if (!title || !start || !end || !marks || parseInt(marks) <= 0 || parseInt(marks) > 100 || questions.length <= 0) {
        event.preventDefault();
        alert("Please fill in all required exam details first.");
        return;
    }
});

document.getElementById("backToDetailsBtn").addEventListener("click", () => activateTab("details-tab"));
document.getElementById("backToQuestionsBtn").addEventListener("click", () => activateTab("questions-tab"));

document.getElementById("toConfirmBtn").addEventListener("click", function () {
    if (questions.length === 0) {
        alert("Please generate or add at least one question.");
        return;
    }
    updateConfirmSummary();
    renderConfirmQuestions();
    activateTab("confirm-tab");
});

// RENDER QUESTIONS (Questions tab)
function renderQuestions() {
    const container = document.getElementById("questionsContainer");

    if (questions.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5 empty-state" id="emptyQuestions">
                <i class="fas fa-question-circle" style="font-size: 3rem;"></i>
                <p class="mt-3 mb-0">No questions yet.</p>
                <p class="small">Click "Generate Random Questions" above to get started.</p>
            </div>
        `;
        document.getElementById("questionCounter").textContent = "0 questions";
        return;
    }

    let html = "";
    questions.forEach((q, index) => {
        const isGenerated = q.is_generated || false;
        const isDraft = q.is_draft || false;
        html += `
        <input type="hidden" name="question_ids[]" value="${q.id}">
            <div class="border rounded-3 p-3 mb-3 question-card-item ${isGenerated ? "is-generated" : ""}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-secondary rounded-pill">Q${index + 1}</span>
                            <span class="badge bg-primary rounded-pill">${q.question_mark} marks</span>
                            ${isDraft ? '<span class="badge bg-warning text-dark rounded-pill"><i class="fas fa-save me-1"></i>Draft</span>' : ""}
                            ${isGenerated ? '<span class="badge bg-info text-white rounded-pill"><i class="fas fa-magic me-1"></i>Auto</span>' : ""}
                        </div>
                        <p class="mb-1">Q. ${q.question_text}</p>
                        <div class="d-flex flex-column flex-wrap gap-2 mt-1">
                            ${(q.choices || [])
                                .map(
                                    (c) => `
                                <span class="choice-pill ${c.is_correct ? "correct" : ""}">
                                    ${c.choice_text}${c.is_correct ? " ✓" : ""}
                                </span>
                            `,
                                )
                                .join("")}
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" onclick="updateQuestion(${q.id}, '${q.question_text}', ${q.question_mark}, '${q.type}')">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                                
                        <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0" onclick="removeQuestion(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    document.getElementById("questionCounter").textContent = questions.length + " questions";
}

function removeQuestion(index) {
    const question = questions[index];

    if (!question) {
        return;
    }

    if (confirm("Remove this question from the exam?")) {
        // Remember that the user explicitly removed this draft.
        if (question.is_draft) {
            removedDraftQuestionIds.add(String(question.id));
        }

        questions.splice(index, 1);

        renderQuestions();
    }
}

// CONFIRM TAB
function updateConfirmSummary() {
    document.getElementById("confirmTitle").textContent = document.getElementById("examTitle").value || "-";
    document.getElementById("confirmStart").textContent = document.getElementById("startDate").value
        ? new Date(document.getElementById("startDate").value).toLocaleString()
        : "-";
    document.getElementById("confirmEnd").textContent = document.getElementById("endDate").value
        ? new Date(document.getElementById("endDate").value).toLocaleString()
        : "-";
    document.getElementById("confirmDuration").textContent = new Date(
        new Date(document.getElementById("endDate")).getTime() - new Date(document.getElementById("startDate")).getTime(),
    );
}

function renderConfirmQuestions() {
    const container = document.getElementById("confirmQuestionsContainer");
    if (questions.length === 0) {
        container.innerHTML = `<p class="text-muted">No questions added.</p>`;
        return;
    }

    let html = "";
    questions.forEach((q, i) => {
        html += `
            <div class="border rounded-3 p-3 mb-2 bg-light">
                <div class="d-flex gap-2 align-items-start">
                    <span class="badge bg-secondary rounded-pill mt-1">${i + 1}</span>
                    <div>
                        <span class="badge bg-primary rounded-pill mb-1">${q.question_mark} marks</span>
                        <p class="mb-1">${q.question_text}</p>
                        <div class="d-flex flex-wrap gap-2">
                            ${(q.choices || [])
                                .map(
                                    (c) => `
                                <span class="choice-pill ${c.is_correct ? "correct" : ""}">
                                    ${c.choice_text}${c.is_correct ? " ✓" : ""}
                                </span>
                            `,
                                )
                                .join("")}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

// SECOND CONFIRMATION LAYER
document.getElementById("openFinalConfirmBtn").addEventListener("click", function () {
    const totalMarks = questions.reduce((sum, q) => sum + (parseInt(q.question_mark) || 0), 0);
    document.getElementById("finalConfirmTitle").textContent = document.getElementById("examTitle").value || "this exam";
    document.getElementById("finalConfirmCount").textContent = questions.length;
    document.getElementById("finalConfirmMarks").textContent = totalMarks;

    // Pack the assembled questions (with real nested choices) into the
    // hidden field so the real POST submission carries them to the server.
    document.getElementById("questionsPayload").value = JSON.stringify(questions);

    new bootstrap.Modal(document.getElementById("finalConfirmModal")).show();
});

function createQuestion() {
    const modal = questionPreview();
}

function updateQuestion(questionID, questionText, questionMark, questionType) {
    const modal = questionPreview("Update", questionID, questionText, questionMark, questionType);
}
document.addEventListener("DOMContentLoaded", function () {
    const now = new Date();
    const start = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
    const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);

    document.getElementById("startDate").value = start.toISOString().slice(0, 16);
    document.getElementById("endDate").value = end.toISOString().slice(0, 16);
});
