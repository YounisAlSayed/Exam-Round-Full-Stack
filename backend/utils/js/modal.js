function createModal({ id, title = "", size = "md", centered = true, scrollable = false } = {}) {
    if (!id) {
        throw new Error("createModal() requires an id");
    }

    let modalEl = document.getElementById(id);

    if (!modalEl) {
        const sizeClass = size === "md" ? "" : `modal-${size}`;
        const dialogClasses = ["modal-dialog", sizeClass, centered ? "modal-dialog-centered" : "", scrollable ? "modal-dialog-scrollable" : ""]
            .filter(Boolean)
            .join(" ");

        modalEl = document.createElement("div");
        modalEl.className = "modal fade dynamic-modal";
        modalEl.id = id;
        modalEl.tabIndex = -1;
        modalEl.setAttribute("aria-hidden", "true");

        modalEl.innerHTML = `
            <div class="${dialogClasses}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" data-role="title"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" data-role="body"></div>
                    <div class="modal-footer" data-role="footer"></div>
                </div>
            </div>
        `;

        document.body.appendChild(modalEl);

        // Clean up from the DOM once closed, so repeated dynamic modals
        // with different ids don't pile up unused nodes over a long session.
        modalEl.addEventListener("hidden.bs.modal", () => {
            if (modalEl.dataset.persist !== "true") {
                modalEl.remove();
            }
        });
    }

    const titleEl = modalEl.querySelector('[data-role="title"]');
    const bodyEl = modalEl.querySelector('[data-role="body"]');
    const footerEl = modalEl.querySelector('[data-role="footer"]');

    if (title) titleEl.innerHTML = title;

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

    return {
        element: modalEl,
        bsModal,
        setTitle: (html) => {
            titleEl.innerHTML = html;
        },
        setBody: (html) => {
            bodyEl.innerHTML = html;
        },
        setFooter: (html) => {
            footerEl.innerHTML = html;
        },
        show: () => bsModal.show(),
        hide: () => bsModal.hide(),
    };
}
