// Populate modal with choice data
document.addEventListener("DOMContentLoaded", function () {
    const editChoiceModal = document.getElementById("editChoiceModal");

    if (!editChoiceModal) {
        return;
    }

    editChoiceModal.addEventListener("show.bs.modal", function (event) {
        const trigger = event.relatedTarget;

        // Get data from the clicked element
        const choiceId = trigger.getAttribute("data-choice-id");
        const choiceText = trigger.getAttribute("data-choice-text");
        const questionId = trigger.getAttribute("data-question-id");
        const examId = trigger.getAttribute("data-exam-id");
        const isCorrect = trigger.getAttribute("data-is-correct") === "true";

        // Populate form fields
        document.getElementById("choiceIdInput").value = choiceId;
        document.getElementById("choiceTextInput").value = choiceText;
        document.getElementById("questionIdInput").value = questionId;
        document.getElementById("examIdInput").value = examId;
        document.getElementById("choiceIsCorrect").checked = isCorrect;

        // Set form action
        const form = document.getElementById("editChoiceForm");
        form.action = base_path + "/api/exams/" + examId + "/questions/" + questionId + "/choices/" + choiceId + "/edit";
    });
});

// Keep edits as normal server-side form posts.
const editChoiceForm = document.getElementById("editChoiceForm");
if (editChoiceForm) {
    editChoiceForm.addEventListener("submit", function (event) {
        if (!confirm("Save changes to this choice?")) {
            event.preventDefault();
        }
    });
}
