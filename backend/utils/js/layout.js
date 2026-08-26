document.addEventListener("DOMContentLoaded", function () {
    // Initialize all toasts
    const toastEls = document.querySelectorAll(".flash-toast");
    toastEls.forEach(function (toastEl) {
        const toast = new bootstrap.Toast(toastEl, {
            delay: 5000, // 5 seconds
            autohide: true,
        });
        toast.show();
    });
});
