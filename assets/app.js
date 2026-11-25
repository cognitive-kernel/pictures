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

 
(() => {
    'use strict';
    document.addEventListener('change', async (event) => {
        const select = event.target.closest('.approval-select');
        if (!select) return;
        const previous = select.dataset.original;
        select.disabled = true;
        try {
            const response = await fetch('approval.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({
                    code: select.dataset.code || '',
                    approved: select.value,
                    csrf: window.PICTURES_APPROVAL_CSRF || ''
                })
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'ذخیره وضعیت ناموفق بود.');
            select.dataset.original = select.value;
            const timeCell = select.closest('tr')?.querySelector('.approval-time');
            if (timeCell) timeCell.textContent = result.modified_at || '—';
        } catch (error) {
            select.value = previous === '' ? '0' : previous;
            window.alert(error.message || 'خطا در ذخیره وضعیت تأیید.');
        } finally {
            select.disabled = false;
        }
    });
})();
