/**
 * Eau Events CPT - Frontend JavaScript
 *
 * @package EauSystem
 * @since 1.28.1
 */

(function() {
    'use strict';

    /**
     * Events Frontend Controller
     */
    const EauEventsFrontend = {

        /**
         * Initialize
         */
        init: function() {
            this.initCountdown();
            this.initShareButton();
            this.initSaveButton();
            this.initFilterForm();
            this.initRegistrationModal();
            this.initJoinButton();
        },

        /**
         * Initialize registration modal
         */
        initRegistrationModal: function() {
            const modal = document.getElementById('eau-registration-modal');
            const registerBtn = document.querySelector('.eau-event-register-btn');

            if (!modal || !registerBtn) return;

            const closeBtn = modal.querySelector('.eau-reg-modal-close');
            const cancelBtn = document.getElementById('eau-cancel-registration');
            const overlay = modal.querySelector('.eau-reg-modal-overlay');
            const messageEl = document.getElementById('eau-registration-message');
            const confirmBtn = document.getElementById('eau-confirm-registration');
            const nonceEl = document.getElementById('eau-reg-nonce');

            const self = this;

            function openModal() {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                if (messageEl) {
                    messageEl.innerHTML = '';
                    messageEl.className = 'eau-reg-message';
                }
            }

            registerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (overlay) overlay.addEventListener('click', closeModal);

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeModal();
                }
            });

            if (confirmBtn && nonceEl) {
                confirmBtn.addEventListener('click', function() {
                    const eventId = this.dataset.eventId;
                    const btnText = this.querySelector('.btn-text');
                    const btnLoading = this.querySelector('.btn-loading');

                    this.disabled = true;
                    if (btnText) btnText.style.display = 'none';
                    if (btnLoading) btnLoading.style.display = 'inline';

                    const formData = new FormData();
                    formData.append('action', 'eau_register_for_event');
                    formData.append('event_id', eventId);
                    formData.append('nonce', nonceEl.value);

                    fetch(eauEventsFrontendData.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        confirmBtn.disabled = false;
                        if (btnText) btnText.style.display = 'inline';
                        if (btnLoading) btnLoading.style.display = 'none';

                        if (data.success) {
                            messageEl.className = 'eau-reg-message eau-reg-message-success';
                            messageEl.innerHTML = data.data.message;
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            messageEl.className = 'eau-reg-message eau-reg-message-error';
                            messageEl.innerHTML = data.data.message;
                        }
                    })
                    .catch(function(error) {
                        confirmBtn.disabled = false;
                        if (btnText) btnText.style.display = 'inline';
                        if (btnLoading) btnLoading.style.display = 'none';
                        messageEl.className = 'eau-reg-message eau-reg-message-error';
                        messageEl.innerHTML = 'An error occurred. Please try again.';
                    });
                });
            }
        },

        /**
         * Initialize join button (marks attendance)
         */
        initJoinButton: function() {
            const joinBtn = document.querySelector('.eau-event-join-btn');
            if (!joinBtn) return;

            joinBtn.addEventListener('click', function() {
                const eventId = this.dataset.eventId;
                if (!eventId) return;

                const formData = new FormData();
                formData.append('action', 'eau_mark_event_attended');
                formData.append('nonce', eauEventsFrontendData.nonce);
                formData.append('event_id', eventId);

                fetch(eauEventsFrontendData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
            });
        },

        /**
         * Initialize countdown timer
         */
        initCountdown: function() {
            const countdownEl = document.querySelector('.eau-event-countdown');
            if (!countdownEl) return;

            const startDate = countdownEl.dataset.start;
            if (!startDate) return;

            const targetDate = new Date(startDate).getTime();

            const updateCountdown = () => {
                const now = new Date().getTime();
                const distance = targetDate - now;

                if (distance < 0) {
                    countdownEl.innerHTML = '<div class="eau-countdown-expired">Event has started!</div>';
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                const daysEl = countdownEl.querySelector('[data-days]');
                const hoursEl = countdownEl.querySelector('[data-hours]');
                const minutesEl = countdownEl.querySelector('[data-minutes]');
                const secondsEl = countdownEl.querySelector('[data-seconds]');

                if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
                if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
                if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
                if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
            };

            // Update immediately
            updateCountdown();

            // Update every second
            setInterval(updateCountdown, 1000);
        },

        /**
         * Initialize share button
         */
        initShareButton: function() {
            const shareBtn = document.querySelector('.eau-event-share-btn');
            if (!shareBtn) return;

            shareBtn.addEventListener('click', async (e) => {
                e.preventDefault();

                const url = shareBtn.dataset.url;
                const title = shareBtn.dataset.title;

                // Try native share API first
                if (navigator.share) {
                    try {
                        await navigator.share({
                            title: title,
                            url: url
                        });
                        return;
                    } catch (err) {
                        // User cancelled or share failed
                        if (err.name === 'AbortError') return;
                    }
                }

                // Fallback: copy to clipboard
                try {
                    await navigator.clipboard.writeText(url);
                    this.showToast('Link copied to clipboard!', 'success');
                } catch (err) {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = url;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-9999px';
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    this.showToast('Link copied to clipboard!', 'success');
                }
            });
        },

        /**
         * Initialize save button
         */
        initSaveButton: function() {
            const saveBtn = document.querySelector('.eau-event-save-btn');
            if (!saveBtn) return;

            // Check if already saved (from localStorage)
            const eventId = saveBtn.dataset.eventId;
            const savedEvents = JSON.parse(localStorage.getItem('eau_saved_events') || '[]');

            if (savedEvents.includes(eventId)) {
                saveBtn.classList.add('eau-saved');
                saveBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                    Saved
                `;
            }

            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();

                const eventId = saveBtn.dataset.eventId;
                let savedEvents = JSON.parse(localStorage.getItem('eau_saved_events') || '[]');

                if (savedEvents.includes(eventId)) {
                    // Remove from saved
                    savedEvents = savedEvents.filter(id => id !== eventId);
                    saveBtn.classList.remove('eau-saved');
                    saveBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        Save
                    `;
                    this.showToast('Event removed from saved', 'info');
                } else {
                    // Add to saved
                    savedEvents.push(eventId);
                    saveBtn.classList.add('eau-saved');
                    saveBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        Saved
                    `;
                    this.showToast('Event saved!', 'success');
                }

                localStorage.setItem('eau_saved_events', JSON.stringify(savedEvents));
            });
        },

        /**
         * Initialize filter form (auto-submit on select change)
         */
        initFilterForm: function() {
            const filterForm = document.querySelector('.eau-events-filter-form');
            if (!filterForm) return;

            const selects = filterForm.querySelectorAll('select');
            selects.forEach(select => {
                select.addEventListener('change', () => {
                    filterForm.submit();
                });
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info') {
            // Remove existing toast
            const existingToast = document.querySelector('.eau-toast');
            if (existingToast) {
                existingToast.remove();
            }

            // Create toast
            const toast = document.createElement('div');
            toast.className = `eau-toast eau-toast-${type}`;
            toast.innerHTML = `
                <span class="eau-toast-message">${message}</span>
                <button type="button" class="eau-toast-close">&times;</button>
            `;

            // Add styles
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 12px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                z-index: 9999;
                animation: slideIn 0.3s ease;
            `;

            // Add animation keyframes
            if (!document.querySelector('#eau-toast-styles')) {
                const style = document.createElement('style');
                style.id = 'eau-toast-styles';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                    .eau-toast-close {
                        background: none;
                        border: none;
                        color: white;
                        font-size: 20px;
                        cursor: pointer;
                        padding: 0;
                        line-height: 1;
                        opacity: 0.8;
                    }
                    .eau-toast-close:hover {
                        opacity: 1;
                    }
                `;
                document.head.appendChild(style);
            }

            document.body.appendChild(toast);

            // Close button
            const closeBtn = toast.querySelector('.eau-toast-close');
            closeBtn.addEventListener('click', () => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            });

            // Auto dismiss
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 3000);
        }
    };

    /**
     * Initialize on DOM ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => EauEventsFrontend.init());
    } else {
        EauEventsFrontend.init();
    }

})();
