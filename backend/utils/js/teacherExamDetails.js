// Populate modal with choice data
document.addEventListener("DOMContentLoaded", function () {
    const editChoiceModal = document.getElementById("editChoiceModal");

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

// Handle form submission with confirmation
document.getElementById("editChoiceForm").addEventListener("submit", function (e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);

    // Send AJAX request to update choice
    fetch(form.action, {
        method: "POST",
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                // Close edit modal
                const editModal = bootstrap.Modal.getInstance(document.getElementById("editChoiceModal"));
                editModal.hide();

                // Show success confirmation
                const confirmModal = new bootstrap.Modal(document.getElementById("confirmSaveModal"));
                confirmModal.show();

                // Reload page after confirmation modal is closed
                document.getElementById("confirmSaveModal").addEventListener("hidden.bs.modal", function () {
                    location.reload();
                });
            } else {
                alert("Error updating choice: " + (data.message || "Unknown error"));
            }
        })
        .catch((error) => {
            alert("Error: " + error);
        });
});
