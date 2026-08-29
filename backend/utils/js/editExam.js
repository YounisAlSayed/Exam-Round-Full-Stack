let questions = [];
const removedDraftQuestionIds = new Set();

if (Array.isArray(draftQuestions)) {
    questions = draftQuestions.map((q) => ({
        id: q.id,
        question_text: q.question,
        question_mark: q.question_mark || 10,
        choices: draftQuestionsChoices[q.id] || [],
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

// RANDOM GENERATE
document.getElementById("generateBtn").addEventListener("click", function () {
    const numQuestions = parseInt(document.getElementById("numQuestions").value) || 5;

    if (!courseQuestions || courseQuestions.length === 0) {
        alert("No questions available in this course yet.");
        return;
    }

    // ALWAYS preserve drafts.
    const draftQuestions = questions.filter((q) => q.is_draft);

    // Questions that should not be randomly generated again.
    const protectedIds = new Set(draftQuestions.map((q) => String(q.id)));

    const availableQuestions = courseQuestions.filter((q) => !protectedIds.has(String(q.id)));

    if (availableQuestions.length === 0) {
        alert("No additional questions are available.");
        return;
    }

    const shuffled = [...availableQuestions].sort(() => 0.5 - Math.random());

    const selected = shuffled.slice(0, Math.min(numQuestions, shuffled.length));

    const generatedQuestions = selected.map((q) => ({
        id: q.id,
        question_text: q.question,
        question_mark: q.question_mark || 10,
        choices: questionsChoices[q.id] || [],
        type: q.type,
        is_generated: true,
        is_draft: false,
    }));

    // Drafts FIRST, generated questions SECOND.
    questions = [...draftQuestions, ...generatedQuestions];

    renderQuestions();
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

document.getElementById("examForm").addEventListener("submit", function (event) {
    if (!this.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();

        const firstInvalid = this.querySelector(":invalid");
        if (firstInvalid) {
            const pane = firstInvalid.closest(".tab-pane");
            if (pane) {
                const tabButton = document.querySelector(`[data-bs-target="#${pane.id}"]`);
                if (tabButton) {
                    bootstrap.Tab.getOrCreateInstance(tabButton).show();
                }
            }

            setTimeout(() => {
                firstInvalid.focus();
                firstInvalid.reportValidity();
            }, 150);
        }

        return;
    }

    // valid — submission proceeds normally
});

function createQuestion() {
    const modal = questionPreview();
}

function updateQuestion(questionID, questionText, questionMark, questionType) {
    const modal = questionPreview("Update", questionID, questionText, questionMark, questionType);
}
function questionPreview(buttonText = "Create", questionId = null) {
    const MIN_CHOICES = 2;
    const MAX_CHOICES = 4;

    let questionText = "";
    let type = "mc";
    let mark = 10;
    let choices = [];

    // `questions` is an array indexed by POSITION, not by each question's real
    // id — so it has to be searched, not indexed directly with questions[questionId].
    if (questionId !== null) {
        const existing = questions.find((q) => q.id === questionId);
        if (existing) {
            questionText = existing.question_text || "";
            type = existing.type || "mc";
            mark = existing.question_mark || 10;
            choices = existing.choices || [];
        }
    }

    const modal = createModal({ id: "questionPreviewModal", title: buttonText + " Question", size: "lg" });

    modal.setBody(`
        <form id="addQuestionForm" method="POST" action="${basePath}/api/questions/create/courses/${courseID}">
            <input type="hidden" name="exam_title" id="saveExamTitle">
            <input type="hidden" name="exam_start_date" id="saveExamStartDate">
            <input type="hidden" name="exam_end_date" id="saveExamEndDate">
            <input type="hidden" name="exam_total_marks" id="saveExamTotalMarks">
            
            <input type="hidden" id="qp_questionId" value="${questionId ?? ""}">
            <div class="row g-3">
                <div class="col-12">
                    <label for="qp_questionText" class="form-label fw-semibold">Question</label>
                    <input type="text" id="qp_questionText" name="question" class="form-control"
                        placeholder="e.g., What does CRUD stand for?" value="${questionText}">
                </div>
 
                <div class="col-md-4">
                    <label for="qp_questionMark" class="form-label fw-semibold">Marks</label>
                    <input type="number" id="qp_questionMark" name="question_mark" class="form-control" min="1" value="${mark}">
                </div>
 
                <div class="col-12">
                    <label class="form-label fw-semibold d-block">Question Type</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="question_type" id="qp_typeMC"
                                value="mc" ${type === "mc" ? "checked" : ""}>
                            <label class="form-check-label" for="qp_typeMC">Multiple Choice</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" id="qp_typeTF"
                                value="t/f" ${type === "t/f" ? "checked" : ""}>
                            <label class="form-check-label" for="qp_typeTF">True / False</label>
                        </div>
                    </div>
                </div>
 
                <!-- Multiple Choice: dynamic choice list, min 2 / max 4 -->
                <div class="col-12" id="qp_mcContainer">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label fw-semibold mb-0">Choices</label>
                        <small class="text-muted" id="qp_choiceHint">2–4 choices</small>
                    </div>
                    <div id="qp_choicesList" class="mt-2"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="qp_addChoiceBtn">
                        <i class="fas fa-plus me-1"></i> Add Choice
                    </button>
                </div>
 
                <!-- True/False: fixed two options -->
                <div class="col-12 d-none" id="qp_tfContainer">
                    <label class="form-label fw-semibold d-block">Correct Answer</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qp_tfCorrect" id="qp_tfTrue" value="true">
                            <label class="form-check-label" for="qp_tfTrue">True</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="qp_tfCorrect" id="qp_tfFalse" value="false">
                            <label class="form-check-label" for="qp_tfFalse">False</label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    `);

    modal.setFooter(`
        <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="addQuestionForm" class="btn btn-outline-primary" id="qp_confirmBtn">${buttonText}</button>
    `);

    // ---- Choice count helpers ----
    function currentChoiceCount() {
        return document.getElementById("qp_choicesList").children.length;
    }

    function updateChoiceControls() {
        const count = currentChoiceCount();

        document.getElementById("qp_addChoiceBtn").classList.toggle("d-none", count >= MAX_CHOICES);

        // Disable (not remove) every row's delete button while at the floor,
        // so it's visibly clear why removal is blocked rather than the
        // button just silently vanishing.
        document.querySelectorAll("#qp_choicesList .qp-choice-remove").forEach((btn) => {
            btn.disabled = count <= MIN_CHOICES;
        });
    }

    function addChoiceRow(text = "", isCorrect = false) {
        if (currentChoiceCount() >= MAX_CHOICES) {
            return;
        }

        const list = document.getElementById("qp_choicesList");
        const index = list.children.length;
        const row = document.createElement("div");
        row.className = "d-flex align-items-center gap-2 mb-2 qp-choice-row";
        row.innerHTML = `
            <input type="text" class="form-control qp-choice-text" name="choices[]"
                placeholder="Choice ${String.fromCharCode(65 + index)}" value="${text}">
            <div class="form-check">
                <input class="form-check-input qp-choice-correct" type="radio"
                    name="correct" ${isCorrect ? "checked" : ""}>
                <label class="form-check-label">Correct</label>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger qp-choice-remove">
                <i class="fas fa-times"></i>
            </button>
        `;

        row.querySelector(".qp-choice-remove").addEventListener("click", function () {
            if (currentChoiceCount() <= MIN_CHOICES) {
                return; // extra guard — button is disabled by this point anyway
            }
            row.remove();
            updateChoiceControls();
        });

        list.appendChild(row);
        updateChoiceControls();
    }

    document.getElementById("qp_choicesList").innerHTML = "";
    if (type === "mc" && choices.length > 0) {
        // never seed more rows than the max, even if source data has more
        choices.slice(0, MAX_CHOICES).forEach((c) => addChoiceRow(c.choice_text, c.is_correct));
    } else {
        addChoiceRow();
        addChoiceRow();
    }

    document.getElementById("qp_addChoiceBtn").addEventListener("click", () => addChoiceRow());
    updateChoiceControls();

    // ---- MC <-> True/False toggle ----
    function toggleType() {
        const isMC = document.getElementById("qp_typeMC").checked;
        document.getElementById("qp_mcContainer").classList.toggle("d-none", !isMC);
        document.getElementById("qp_tfContainer").classList.toggle("d-none", isMC);
    }

    document.getElementById("qp_typeMC").addEventListener("change", toggleType);
    document.getElementById("qp_typeTF").addEventListener("change", toggleType);
    toggleType();

    // Prefill True/False correct answer when editing an existing t/f question
    if (type === "t/f" && choices.length > 0) {
        const trueChoice = choices.find((c) => (c.choice_text || "").toLowerCase() === "true");
        if (trueChoice) {
            document.getElementById(trueChoice.is_correct ? "qp_tfTrue" : "qp_tfFalse").checked = true;
        }
    }

    // ---- Client-side validation before the REAL submit ----
    // The form really posts to the backend (method="POST", real action URL) —
    // this listener only blocks obviously-bad input; it never intercepts a
    // valid submission or builds/discards its own copy of the data.
    document.getElementById("addQuestionForm").addEventListener("submit", function (event) {
        const questionTextValue = document.getElementById("qp_questionText").value.trim();
        if (!questionTextValue) {
            event.preventDefault();
            alert("Please enter the question text.");
            return;
        }

        const isMC = document.getElementById("qp_typeMC").checked;

        if (isMC) {
            const rows = document.querySelectorAll("#qp_choicesList .qp-choice-row");
            let filledCount = 0;
            let hasCorrect = false;

            rows.forEach((row) => {
                if (row.querySelector(".qp-choice-text").value.trim()) filledCount++;
                if (row.querySelector(".qp-choice-correct").checked) hasCorrect = true;
            });

            if (filledCount < MIN_CHOICES) {
                event.preventDefault();
                alert(`Please fill in at least ${MIN_CHOICES} choices.`);
                return;
            }
            if (!hasCorrect) {
                event.preventDefault();
                alert("Please mark one choice as correct.");
                return;
            }
        } else {
            const correct = document.querySelector('input[name="qp_tfCorrect"]:checked');
            if (!correct) {
                event.preventDefault();
                alert("Please select True or False as the correct answer.");
                return;
            }
        }
        // valid — let the real POST submission proceed
    });

    modal.show();
}

document.addEventListener("DOMContentLoaded", function () {
    const now = new Date();
    const start = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
    const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);

    document.getElementById("startDate").value = start.toISOString().slice(0, 16);
    document.getElementById("endDate").value = end.toISOString().slice(0, 16);
});
