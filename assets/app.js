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

    function closeDropdown(dropdown) {
        const trigger = dropdown.querySelector('.approval-trigger');
        const menu = dropdown.querySelector('.approval-menu');
        if (!trigger || !menu) return;
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('.approval-trigger');
        if (trigger) {
            const dropdown = trigger.closest('.approval-dropdown');
            const menu = dropdown?.querySelector('.approval-menu');
            if (!dropdown || !menu || trigger.disabled) return;
            const shouldOpen = menu.hidden;
            document.querySelectorAll('.approval-dropdown .approval-menu').forEach((item) => {
                item.hidden = true;
                item.closest('.approval-dropdown')?.querySelector('.approval-trigger')?.setAttribute('aria-expanded', 'false');
            });
            menu.hidden = !shouldOpen;
            trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            return;
        }

        const option = event.target.closest('.approval-option');
        if (option) {
            const dropdown = option.closest('.approval-dropdown');
            const triggerButton = dropdown?.querySelector('.approval-trigger');
            const menu = dropdown?.querySelector('.approval-menu');
            if (!dropdown || !triggerButton || !menu || dropdown.classList.contains('is-saving')) return;

            const nextValue = option.dataset.approvalValue;
            const previousValue = dropdown.dataset.original || '0';
            if (nextValue !== '0' && nextValue !== '1') return;
            closeDropdown(dropdown);
            if (nextValue === previousValue) return;

            dropdown.classList.add('is-saving');
            triggerButton.disabled = true;
            try {
                const response = await fetch('approval.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: new URLSearchParams({
                        code: dropdown.dataset.code || '',
                        approved: nextValue,
                        csrf: window.PICTURES_APPROVAL_CSRF || ''
                    })
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'ذخیره وضعیت ناموفق بود.');

                dropdown.dataset.original = nextValue;
                triggerButton.classList.toggle('is-approved', nextValue === '1');
                triggerButton.classList.toggle('is-pending', nextValue === '0');
                triggerButton.querySelector('.approval-trigger-label').textContent = nextValue === '1' ? 'تأیید شده' : 'تأیید نشده';
                const timeCell = dropdown.closest('tr')?.querySelector('.approval-time');
                if (timeCell) timeCell.textContent = result.modified_at || '—';
            } catch (error) {
                window.alert(error.message || 'خطا در ذخیره وضعیت تأیید.');
            } finally {
                dropdown.classList.remove('is-saving');
                triggerButton.disabled = false;
            }
            return;
        }

        document.querySelectorAll('.approval-dropdown').forEach((dropdown) => {
            if (!dropdown.contains(event.target)) closeDropdown(dropdown);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.approval-dropdown').forEach((dropdown) => closeDropdown(dropdown));
    });
})();
