(() => {
    'use strict';
    const modal = document.getElementById('image-lightbox');
    const modalImage = document.getElementById('lightbox-image');
    const modalTitle = document.getElementById('lightbox-title');
    const closeButton = document.getElementById('lightbox-close');
    if (!modal || !modalImage || !closeButton) return;

    let lastFocusedElement = null;
    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modalImage.removeAttribute('src');
        document.body.classList.remove('modal-open');
        if (lastFocusedElement) lastFocusedElement.focus();
    }

    document.querySelectorAll('.js-open-image').forEach((button) => {
        button.addEventListener('click', () => {
            lastFocusedElement = button;
            modalTitle.textContent = button.dataset.title || 'تصویر کالا';
            modalImage.alt = modalTitle.textContent;
            modalImage.src = button.dataset.image || '';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            closeButton.focus();
        });
    });

    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
    modalImage.addEventListener('error', () => {
        modalTitle.textContent = 'بارگذاری تصویر ناموفق بود';
    });
})();
