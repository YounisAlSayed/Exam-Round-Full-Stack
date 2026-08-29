console.log(basePath);
document.getElementById("createExamBtn").addEventListener("click", function () {
    let modal = createModal({ id: "questionPreviewModal", title: "Create Exam", size: "lg" });
    modal.setBody(`
        <form id="createExamForm" method="POST" action="${basePath}/api/exams/create/courses/${courseID}">
            <div class="modal-body p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-info-circle me-2 text-primary"></i>Exam Details
                            </h5>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="examTitle" class="form-label fw-semibold">Exam Title <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="examTitle" name="title"
                                        placeholder="e.g., Midterm Exam - Web Development"
                                        required>
                                </div>

                                <div class="col-md-6">
                                    <label for="startDate" class="form-label fw-semibold">Start Date & Time <span
                                            class="text-danger">*</span></label>
                                    <input type="datetime-local"
                                        class="form-control"
                                        id="startDate" name="start_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="endDate" class="form-label fw-semibold">End Date & Time <span
                                            class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control"
                                        id="endDate"
                                        name="end_date" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="totalMarks" class="form-label fw-semibold">Total Marks <span
                                            class="text-danger">*</span></label>
                                    <input type="number" class="form-control"
                                        id="totalMarks" name="total_marks" placeholder="e.g., 100" min="1" required>
                                </div>

                                <div class="col-md-12">
                                    <label for="randomizeOrder" class="form-label fw-semibold">Randomize Question Order</label>
                                    <input type="checkbox" class="form-check-input" id="randomizeOrder" name="randomize">
                                </div>
                            </div>
                        </div>
        </form>`);
    modal.setFooter(`
        <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="createExamForm" class="btn btn-outline-primary" id="qp_confirmBtn">Create Exam</button>`);

    modal.show();
});
