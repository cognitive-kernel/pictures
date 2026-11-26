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

    document.addEventListener('click', async (event) => {
        const toggle = event.target.closest('.approval-switch');
        if (!toggle || toggle.disabled) return;

        const control = toggle.closest('.approval-control');
        if (!control || control.classList.contains('is-saving')) return;

        const previousValue = control.dataset.original === '1' ? '1' : '0';
        const nextValue = previousValue === '1' ? '0' : '1';
        control.classList.add('is-saving');
        toggle.disabled = true;

        try {
            const response = await fetch('approval.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({
                    code: control.dataset.code || '',
                    approved: nextValue,
                    csrf: window.PICTURES_APPROVAL_CSRF || ''
                })
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'ذخیره وضعیت ناموفق بود.');

            control.dataset.original = nextValue;
            toggle.classList.toggle('is-approved', nextValue === '1');
            toggle.classList.toggle('is-pending', nextValue === '0');
            toggle.setAttribute('aria-checked', nextValue === '1' ? 'true' : 'false');
            const label = control.querySelector('.approval-switch-label');
            if (label) label.textContent = nextValue === '1' ? 'تأییدشده' : 'ردشده';
            const timeCell = control.closest('tr')?.querySelector('.approval-time');
            if (timeCell) timeCell.textContent = result.modified_at || '—';
        } catch (error) {
            window.alert(error.message || 'خطا در ذخیره وضعیت تأیید.');
        } finally {
            control.classList.remove('is-saving');
            toggle.disabled = false;
        }
    });
})();
