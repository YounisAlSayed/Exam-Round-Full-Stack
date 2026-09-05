const checkboxes = document.querySelectorAll('input[name="selected_questions[]"]');

const selectAllBtn = document.getElementById("selectAllBtn");

checkboxes.forEach((checkbox) => {
    checkbox.addEventListener("change", updateSelectedCount);
});

selectAllBtn?.addEventListener("click", () => {
    const allSelected = [...checkboxes].every((checkbox) => checkbox.checked);

    checkboxes.forEach((checkbox) => {
        checkbox.checked = !allSelected;
    });

    selectAllBtn.innerHTML = allSelected ? '<i class="fas fa-check-double me-1"></i> Select All' : '<i class="fas fa-xmark me-1"></i> Deselect All';
});
