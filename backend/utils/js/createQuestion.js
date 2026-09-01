const MIN_CHOICES = 2;
const MAX_CHOICES = 4;

const form = document.getElementById("addQuestionForm");

const questionText = document.getElementById("qp_questionText");
const questionMark = document.getElementById("qp_questionMark");

const typeMC = document.getElementById("qp_typeMC");
const typeTF = document.getElementById("qp_typeTF");

const mcContainer = document.getElementById("qp_mcContainer");
const tfContainer = document.getElementById("qp_tfContainer");

const choicesList = document.getElementById("qp_choicesList");
const addChoiceBtn = document.getElementById("qp_addChoiceBtn");

const tfTrue = document.getElementById("qp_tfTrue");
const tfFalse = document.getElementById("qp_tfFalse");

function getChoiceRows() {
    return Array.from(choicesList.querySelectorAll(".qp-choice-row"));
}

function getChoiceCount() {
    return getChoiceRows().length;
}

function updateChoiceControls() {
    const rows = getChoiceRows();
    const count = rows.length;

    addChoiceBtn.classList.toggle("d-none", count >= MAX_CHOICES);

    rows.forEach((row) => {
        const removeBtn = row.querySelector(".qp-choice-remove");

        removeBtn.disabled = count <= MIN_CHOICES;
    });

    rows.forEach((row, index) => {
        const input = row.querySelector(".qp-choice-text");

        input.placeholder = `Choice ${String.fromCharCode(65 + index)}`;
    });
}

function addChoiceRow(text = "", isCorrect = false, choiceId) {
    if (getChoiceCount() >= MAX_CHOICES) {
        return;
    }

    const index = getChoiceCount();

    const row = document.createElement("div");

    row.className = "d-flex align-items-center gap-2 mb-2 qp-choice-row";

    const input = document.createElement("input");

    input.type = "text";
    input.name = "choices[]";
    input.className = "form-control qp-choice-text";
    input.placeholder = `Choice ${String.fromCharCode(65 + index)}`;

    input.value = text;

    const correctContainer = document.createElement("div");

    correctContainer.className = "form-check";

    const correctRadio = document.createElement("input");

    correctRadio.type = "radio";
    correctRadio.name = "correct_choice";
    correctRadio.value = index;
    correctRadio.className = "form-check-input qp-choice-correct";

    correctRadio.checked = isCorrect;

    const correctLabel = document.createElement("label");

    correctLabel.className = "form-check-label";
    correctLabel.textContent = "Correct";

    const removeBtn = document.createElement("button");

    removeBtn.type = "button";
    removeBtn.className = "btn btn-sm btn-outline-danger qp-choice-remove";

    removeBtn.innerHTML = '<i class="fas fa-times"></i>';

    removeBtn.addEventListener("click", () => {
        if (getChoiceCount() <= MIN_CHOICES) {
            return;
        }
        const wasCorrect = correctRadio.checked;

        row.remove();

        if (wasCorrect) {
            const radios = choicesList.querySelectorAll(".qp-choice-correct");

            radios.forEach((radio) => {
                radio.checked = false;
            });
        }

        reindexChoices();
        updateChoiceControls();
    });

    correctContainer.appendChild(correctRadio);
    correctContainer.appendChild(correctLabel);

    row.appendChild(input);
    row.appendChild(correctContainer);
    row.appendChild(removeBtn);

    choicesList.appendChild(row);
    updateChoiceControls();
}

function reindexChoices() {
    const rows = getChoiceRows();

    rows.forEach((row, index) => {
        const input = row.querySelector(".qp-choice-text");
        const radio = row.querySelector(".qp-choice-correct");

        input.placeholder = `Choice ${String.fromCharCode(65 + index)}`;
        radio.value = index;
    });
}

function initializeChoices() {
    choicesList.innerHTML = "";

    questionChoices.forEach((choice) => {
        addChoiceRow(choice["choice_text"], choice["is_correct"], choice["id"]);
    });
}

function toggleQuestionType() {
    const isMC = typeMC.checked;
    mcContainer.classList.toggle("d-none", !isMC);
    tfContainer.classList.toggle("d-none", isMC);
    const mcInputs = choicesList.querySelectorAll("input");
    mcInputs.forEach((input) => {
        input.disabled = !isMC;
    });

    tfTrue.disabled = isMC;
    tfFalse.disabled = isMC;

    if (isMC) {
        questionText.focus();
    } else {
        document.querySelectorAll(".qp-choice-correct").forEach((radio) => {
            radio.disabled = true;
        });
    }
}

typeMC.addEventListener("change", toggleQuestionType);
typeTF.addEventListener("change", toggleQuestionType);
addChoiceBtn.addEventListener("click", () => addChoiceRow());

form.addEventListener("submit", function (event) {
    const question = questionText.value.trim();

    if (!question) {
        event.preventDefault();
        alert("Please enter the question text.");
        questionText.focus();
        return;
    }
    const marks = Number(questionMark.value);

    if (!Number.isInteger(marks) || marks < 1 || marks > 100) {
        event.preventDefault();
        alert("Marks must be a number between 1 and 100.");
        questionMark.focus();
        return;
    }

    if (typeMC.checked) {
        const rows = getChoiceRows();
        if (rows.length < MIN_CHOICES || rows.length > MAX_CHOICES) {
            event.preventDefault();
            alert(`You must have between ${MIN_CHOICES} and ${MAX_CHOICES} choices.`);
            return;
        }

        let filledCount = 0;

        rows.forEach((row) => {
            const input = row.querySelector(".qp-choice-text");
            if (input.value.trim()) {
                filledCount++;
            }
        });

        if (filledCount < MIN_CHOICES) {
            event.preventDefault();
            alert(`Please fill in at least ${MIN_CHOICES} choices.`);
            return;
        }

        if (filledCount !== rows.length) {
            event.preventDefault();
            alert("Please fill in all choices.");
            return;
        }

        const correct = document.querySelector('input[name="correct_choice"]:checked');

        if (!correct) {
            event.preventDefault();
            alert("Please mark one choice as correct.");
            return;
        }
    }

    if (typeTF.checked) {
        const correctTF = document.querySelector('input[name="tf_correct"]:checked');
        if (!correctTF) {
            event.preventDefault();
            alert("Please select True or False as the correct answer.");
            return;
        }
    }
});
initializeChoices();
toggleQuestionType();
