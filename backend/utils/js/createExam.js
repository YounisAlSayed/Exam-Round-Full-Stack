// ============================================================
// STATE
// ============================================================
let questions = [];
let questionCounter = 0;
let currentStep = 1;

// ============================================================
// STEP NAVIGATION
// ============================================================
function goToStep1() {
    currentStep = 1;
    document.getElementById("step1").classList.remove("d-none");
    document.getElementById("step2").classList.add("d-none");
    document.getElementById("step3").classList.add("d-none");
    updateStepIndicator(1);
    updateConfirmSummary();
}

function goToStep2() {
    // Validate Step 1
    const title = document.getElementById("examTitle").value.trim();
    const start = document.getElementById("startDate").value;
    const end = document.getElementById("endDate").value;
    const marks = document.getElementById("totalMarks").value;
    const duration = document.getElementById("duration").value;

    if (!title) {
        alert("Please enter an exam title.");
        return;
    }
    if (!start) {
        alert("Please select a start date.");
        return;
    }
    if (!end) {
        alert("Please select an end date.");
        return;
    }
    if (!marks || parseInt(marks) <= 0) {
        alert("Please enter a valid total marks.");
        return;
    }
    if (!duration || parseInt(duration) <= 0) {
        alert("Please enter a valid duration.");
        return;
    }

    // FIX (#3 + #4): re-evaluate auto-generate every time Step 1 is confirmed,
    // not just the first time — so changing "Number of Questions" and
    // returning here actually regenerates, and unchecking the toggle clears
    // out any previously auto-generated questions instead of leaving them.
    const autoGen = document.getElementById("autoGenerate").checked;

    if (autoGen) {
        generateQuestions();
    } else {
        // Toggle is off — drop any questions that were auto-generated,
        // keep any the teacher wrote manually.
        questions = questions.filter((q) => !q.is_generated);
    }

    currentStep = 2;
    document.getElementById("step1").classList.add("d-none");
    document.getElementById("step2").classList.remove("d-none");
    document.getElementById("step3").classList.add("d-none");
    updateStepIndicator(2);
    renderQuestions();
}

function goToStep3() {
    if (questions.length === 0) {
        alert("Please add at least one question.");
        return;
    }
    currentStep = 3;
    document.getElementById("step1").classList.add("d-none");
    document.getElementById("step2").classList.add("d-none");
    document.getElementById("step3").classList.remove("d-none");
    updateStepIndicator(3);
    updateConfirmSummary();
    renderConfirmQuestions();
}

function updateStepIndicator(step) {
    const dots = [document.getElementById("step1Dot"), document.getElementById("step2Dot"), document.getElementById("step3Dot")];
    const labels = [document.getElementById("step1Label"), document.getElementById("step2Label"), document.getElementById("step3Label")];
    const lines = [document.getElementById("stepLine1"), document.getElementById("stepLine2")];

    dots.forEach((dot, i) => {
        const num = i + 1;
        dot.classList.remove("active", "completed", "inactive");
        if (num === step) dot.classList.add("active");
        else if (num < step) dot.classList.add("completed");
        else dot.classList.add("inactive");
    });

    labels.forEach((label, i) => {
        const num = i + 1;
        label.classList.remove("active");
        if (num === step) label.classList.add("active");
    });

    lines.forEach((line, i) => {
        if (i + 1 < step) line.classList.add("completed");
        else line.classList.remove("completed");
    });
}

// ============================================================
// AUTO-GENERATE TOGGLE
// ============================================================
document.getElementById("autoGenerate").addEventListener("change", function () {
    const settings = document.getElementById("autoGenerateSettings");
    if (this.checked) {
        settings.classList.remove("d-none");
    } else {
        settings.classList.add("d-none");
    }
});

// ============================================================
// GENERATE QUESTIONS
// ============================================================
function generateQuestions() {
    const numQuestions = parseInt(document.getElementById("numQuestions").value) || 5;

    // FIX (#5): copy the array before sorting — .sort() mutates in place,
    // and `courseQuestions` must stay untouched so every generation starts
    // from the real, full course question bank, not an already-shuffled
    // (and progressively more shuffled) leftover from the last call.
    const shuffled = [...courseQuestions].sort(() => 0.5 - Math.random());
    const selected = shuffled.slice(0, Math.min(numQuestions, shuffled.length));

    if (selected.length === 0) {
        alert("No questions available in this course. Please add questions first.");
        return;
    }

    // FIX (#1/#2, defensive): accept either `question` (your actual DB column)
    // or `question_text`, and warn loudly in the console if choices are
    // missing rather than silently producing an unanswerable question.
    // This is a stopgap — the real fix is making sure the PHP controller
    // sends each course question with its choices already nested.
    const generated = selected.map((q) => {
        const choices = q.choices || [];
        if (choices.length === 0) {
            console.warn(
                `Question id=${q.id} has no choices attached from the server — ` +
                    `this question will be unanswerable unless the backend nests ` +
                    `each question's choices into courseQuestions.`,
            );
        }
        return {
            ...q,
            question_text: q.question_text || q.question || "",
            is_generated: true,
            question_type: q.question_type || "mc",
            choices: choices,
        };
    });

    // Only replace previously auto-generated questions — keep any the
    // teacher already wrote manually in this session.
    const manualQuestions = questions.filter((q) => !q.is_generated);
    questions = [...manualQuestions, ...generated];

    document.getElementById("questionCounter").textContent = questions.length + " questions";
    renderQuestions();
}

// ============================================================
// RENDER QUESTIONS
// ============================================================
function renderQuestions() {
    const container = document.getElementById("questionsContainer");
    const empty = document.getElementById("emptyQuestions");

    if (questions.length === 0) {
        container.innerHTML = `
                    <div class="text-center text-muted py-5 empty-state" id="emptyQuestions">
                        <i class="fas fa-question-circle" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">No questions added yet.</p>
                        <p class="small">Click "Add Question" to get started.</p>
                    </div>
                `;
        document.getElementById("questionCounter").textContent = "0 questions";
        return;
    }

    let html = "";
    questions.forEach((q, index) => {
        const isGenerated = q.is_generated || false;
        const typeLabel = q.question_type === "tf" ? "True/False" : "Multiple Choice";
        html += `
                    <div class="border rounded-3 p-3 mb-3 ${isGenerated ? "generated-question" : ""}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-secondary rounded-pill">Q${index + 1}</span>
                                    <span class="badge bg-light text-dark rounded-pill">${typeLabel}</span>
                                    <span class="badge bg-primary rounded-pill">${q.question_mark || 10} marks</span>
                                    ${isGenerated ? '<span class="badge bg-info text-white rounded-pill"><i class="fas fa-magic me-1"></i>Auto</span>' : ""}
                                </div>
                                <p class="mb-1">${q.question_text || "Question text"}</p>
                                ${
                                    q.question_type === "mc" && q.choices
                                        ? `
                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                        ${q.choices
                                            .map(
                                                (c) => `
                                            <span class="choice-pill ${c.is_correct ? "correct" : ""}">
                                                ${c.choice_text || c.text || "Choice"}
                                                ${c.is_correct ? " ✓" : ""}
                                            </span>
                                        `,
                                            )
                                            .join("")}
                                    </div>
                                `
                                        : ""
                                }
                                ${
                                    q.question_type === "tf"
                                        ? `
                                    <div class="mt-1">
                                        <span class="choice-pill ${q.correct_answer === "true" ? "correct" : ""}">
                                            Correct: ${q.correct_answer === "true" ? "True" : "False"}
                                        </span>
                                    </div>
                                `
                                        : ""
                                }
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editQuestion(${index})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuestion(${index})">
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

// ============================================================
// ADD / EDIT QUESTION (Modal)
// ============================================================
function addQuestion() {
    document.getElementById("modalQuestionTitle").textContent = "Add Question";
    document.getElementById("editQuestionIndex").value = -1;
    document.getElementById("questionForm").reset();
    document.getElementById("questionText").value = "";
    document.getElementById("questionMark").value = 10;
    document.getElementById("typeMC").checked = true;
    toggleQuestionType();
    document.getElementById("choicesList").innerHTML = "";
    addChoiceField();
    addChoiceField();
    new bootstrap.Modal(document.getElementById("questionModal")).show();
}

function editQuestion(index) {
    const q = questions[index];
    document.getElementById("modalQuestionTitle").textContent = "Edit Question";
    document.getElementById("editQuestionIndex").value = index;
    document.getElementById("questionText").value = q.question_text || "";
    document.getElementById("questionMark").value = q.question_mark || 10;

    if (q.question_type === "tf") {
        document.getElementById("typeTF").checked = true;
        document.querySelector(`input[name="tf_correct"][value="${q.correct_answer}"]`).checked = true;
    } else {
        document.getElementById("typeMC").checked = true;
    }

    toggleQuestionType();

    // Populate choices
    const choicesList = document.getElementById("choicesList");
    choicesList.innerHTML = "";
    if (q.choices && q.choices.length > 0) {
        q.choices.forEach((c, i) => {
            addChoiceField(c.choice_text || c.text || "", c.is_correct || false);
        });
    } else {
        addChoiceField();
        addChoiceField();
    }

    new bootstrap.Modal(document.getElementById("questionModal")).show();
}

function saveQuestion() {
    const form = document.getElementById("questionForm");
    const index = parseInt(document.getElementById("editQuestionIndex").value);
    const questionText = document.getElementById("questionText").value.trim();
    const questionMark = parseInt(document.getElementById("questionMark").value) || 10;
    const type = document.querySelector('input[name="question_type"]:checked').value;

    if (!questionText) {
        alert("Please enter the question text.");
        return;
    }

    let questionData = {
        question_text: questionText,
        question_mark: questionMark,
        question_type: type,
        is_generated: false,
    };

    if (type === "mc") {
        const choiceInputs = document.querySelectorAll(".choice-input");
        const choiceCorrects = document.querySelectorAll(".choice-correct");
        const choices = [];
        let hasCorrect = false;

        choiceInputs.forEach((input, i) => {
            const text = input.value.trim();
            if (text) {
                const isCorrect = choiceCorrects[i].checked;
                if (isCorrect) hasCorrect = true;
                choices.push({
                    choice_text: text,
                    is_correct: isCorrect,
                });
            }
        });

        if (choices.length < 2) {
            alert("Please add at least 2 choices.");
            return;
        }
        if (!hasCorrect) {
            alert("Please mark at least one choice as correct.");
            return;
        }

        questionData.choices = choices;
        questionData.correct_answer = null;
    } else {
        // True/False
        const correct = document.querySelector('input[name="tf_correct"]:checked');
        if (!correct) {
            alert("Please select the correct answer (True or False).");
            return;
        }
        questionData.correct_answer = correct.value;
        questionData.choices = [
            {
                choice_text: "True",
                is_correct: correct.value === "true",
            },
            {
                choice_text: "False",
                is_correct: correct.value === "false",
            },
        ];
    }

    if (index === -1) {
        questions.push(questionData);
    } else {
        questions[index] = questionData;
    }

    bootstrap.Modal.getInstance(document.getElementById("questionModal")).hide();
    renderQuestions();
}

function removeQuestion(index) {
    if (confirm("Are you sure you want to remove this question?")) {
        questions.splice(index, 1);
        renderQuestions();
    }
}

// ============================================================
// CHOICE MANAGEMENT (in Modal)
// ============================================================
function addChoiceField(text = "", isCorrect = false) {
    const container = document.getElementById("choicesList");
    const index = container.children.length;
    const div = document.createElement("div");
    div.className = "d-flex align-items-center gap-2 mb-2";
    div.innerHTML = `
                <input type="text" class="form-control choice-input" placeholder="Choice ${String.fromCharCode(65 + index)}" value="${text}" required>
                <div class="form-check">
                    <input class="form-check-input choice-correct" type="radio" name="choice_correct" value="${index}" ${isCorrect ? "checked" : ""}>
                    <label class="form-check-label">Correct</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
    container.appendChild(div);
}

function toggleQuestionType() {
    const isMC = document.getElementById("typeMC").checked;
    document.getElementById("choicesContainer").classList.toggle("d-none", !isMC);
    document.getElementById("tfContainer").classList.toggle("d-none", isMC);
}

document.querySelectorAll('input[name="question_type"]').forEach((el) => {
    el.addEventListener("change", toggleQuestionType);
});

// ============================================================
// CONFIRM STEP
// ============================================================
function updateConfirmSummary() {
    document.getElementById("confirmTitle").textContent = document.getElementById("examTitle").value || "-";
    document.getElementById("confirmStart").textContent = document.getElementById("startDate").value
        ? new Date(document.getElementById("startDate").value).toLocaleString()
        : "-";
    document.getElementById("confirmEnd").textContent = document.getElementById("endDate").value
        ? new Date(document.getElementById("endDate").value).toLocaleString()
        : "-";
    document.getElementById("confirmDuration").textContent = document.getElementById("duration").value
        ? document.getElementById("duration").value + " min"
        : "-";
}

function renderConfirmQuestions() {
    const container = document.getElementById("confirmQuestionsContainer");
    if (questions.length === 0) {
        container.innerHTML = `<p class="text-muted">No questions added.</p>`;
        return;
    }

    let html = "";
    questions.forEach((q, i) => {
        const typeLabel = q.question_type === "tf" ? "True/False" : "Multiple Choice";
        html += `
                    <div class="border rounded-3 p-3 mb-2 bg-light">
                        <div class="d-flex gap-2 align-items-start">
                            <span class="badge bg-secondary rounded-pill mt-1">${i + 1}</span>
                            <div>
                                <div class="d-flex gap-2 mb-1">
                                    <span class="badge bg-light text-dark rounded-pill">${typeLabel}</span>
                                    <span class="badge bg-primary rounded-pill">${q.question_mark || 10} marks</span>
                                </div>
                                <p class="mb-1">${q.question_text || "Question text"}</p>
                                ${
                                    q.question_type === "mc" && q.choices
                                        ? `
                                    <div class="d-flex flex-wrap gap-2">
                                        ${q.choices
                                            .map(
                                                (c) => `
                                            <span class="choice-pill ${c.is_correct ? "correct" : ""}">
                                                ${c.choice_text || c.text || "Choice"}
                                                ${c.is_correct ? " ✓" : ""}
                                            </span>
                                        `,
                                            )
                                            .join("")}
                                    </div>
                                `
                                        : ""
                                }
                                ${
                                    q.question_type === "tf"
                                        ? `
                                    <span class="choice-pill correct">
                                        Correct: ${q.correct_answer === "true" ? "True" : "False"}
                                    </span>
                                `
                                        : ""
                                }
                            </div>
                        </div>
                    </div>
                `;
    });
    container.innerHTML = html;
}

// ============================================================
// INIT
// ============================================================
document.addEventListener("DOMContentLoaded", function () {
    // Set default date to now + 7 days
    const now = new Date();
    const start = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
    const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);

    document.getElementById("startDate").value = start.toISOString().slice(0, 16);
    document.getElementById("endDate").value = end.toISOString().slice(0, 16);

    // Initialize with 2 empty choices
    addChoiceField();
    addChoiceField();
});
