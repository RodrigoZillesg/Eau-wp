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
            this.initPurchaseAccess();
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

            // Registration state (v1.69.0)
            let registrationState = {
                type: 'self', // 'self' or 'others'
                selectedMembers: [],
                couponCode: '',
                couponData: null,
                eventPrice: 0,
                totalPrice: 0,
                discountAmount: 0
            };

            // Get event price from hidden field
            const eventPriceEl = document.getElementById('eau-event-price');
            if (eventPriceEl) {
                registrationState.eventPrice = parseFloat(eventPriceEl.value) || 0;
                registrationState.totalPrice = registrationState.eventPrice;
            }

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

            // Registration type toggle (v1.69.0)
            const typeOptions = document.querySelectorAll('.eau-reg-type-option');
            const memberSelectionPanel = document.getElementById('eau-member-selection');

            if (typeOptions.length > 0) {
                typeOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        const radio = this.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = true;
                            typeOptions.forEach(opt => opt.classList.remove('selected'));
                            this.classList.add('selected');

                            registrationState.type = radio.value;

                            if (radio.value === 'others' && memberSelectionPanel) {
                                memberSelectionPanel.style.display = 'block';
                                self.loadInstitutionMembers(confirmBtn.dataset.eventId, nonceEl.value);
                            } else if (memberSelectionPanel) {
                                memberSelectionPanel.style.display = 'none';
                                registrationState.selectedMembers = [];
                                self.updatePriceSummary(registrationState);
                            }
                        }
                    });
                });
            }

            // Coupon functionality (v1.69.0)
            const applyCouponBtn = document.getElementById('eau-apply-coupon');
            const couponInput = document.getElementById('eau-coupon-code');
            const couponResult = document.getElementById('eau-coupon-result');

            if (applyCouponBtn && couponInput) {
                applyCouponBtn.addEventListener('click', function() {
                    const code = couponInput.value.trim().toUpperCase();
                    if (!code) {
                        couponResult.innerHTML = '<span class="error">Please enter a coupon code.</span>';
                        couponResult.className = 'eau-reg-coupon-result error';
                        return;
                    }

                    self.validateCoupon(code, confirmBtn.dataset.eventId, nonceEl.value, registrationState, couponResult);
                });

                // Apply coupon on Enter key
                couponInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyCouponBtn.click();
                    }
                });
            }

            // Member selection handling (v1.69.0)
            self.registrationState = registrationState;

            if (confirmBtn && nonceEl) {
                confirmBtn.addEventListener('click', function() {
                    const eventId = this.dataset.eventId;
                    const btnText = this.querySelector('.btn-text');
                    const btnLoading = this.querySelector('.btn-loading');

                    // Validate terms checkbox
                    const termsCheckbox = document.getElementById('eau-reg-terms');
                    if (termsCheckbox && !termsCheckbox.checked) {
                        messageEl.className = 'eau-reg-message eau-reg-message-error';
                        messageEl.innerHTML = 'Please agree to the terms and conditions to continue.';
                        return;
                    }

                    // Validate member selection for 'others' type
                    if (registrationState.type === 'others' && registrationState.selectedMembers.length === 0) {
                        messageEl.className = 'eau-reg-message eau-reg-message-error';
                        messageEl.innerHTML = 'Please select at least one member to register.';
                        return;
                    }

                    this.disabled = true;
                    if (btnText) btnText.style.display = 'none';
                    if (btnLoading) btnLoading.style.display = 'inline';

                    // Get additional fields
                    const dietaryEl = document.getElementById('eau-reg-dietary');
                    const accessibilityEl = document.getElementById('eau-reg-accessibility');
                    const notesEl = document.getElementById('eau-reg-notes');
                    const couponIdEl = document.getElementById('eau-coupon-id');

                    const formData = new FormData();

                    // Determine which action to use based on registration type
                    if (registrationState.type === 'others') {
                        formData.append('action', 'eau_register_multiple_for_event');
                        registrationState.selectedMembers.forEach(memberId => {
                            formData.append('member_ids[]', memberId);
                        });
                    } else {
                        formData.append('action', 'eau_register_for_event');
                    }

                    formData.append('event_id', eventId);
                    formData.append('nonce', nonceEl.value);

                    // Additional fields
                    if (dietaryEl) formData.append('dietary_requirements', dietaryEl.value);
                    if (accessibilityEl) formData.append('accessibility_requirements', accessibilityEl.value);
                    if (notesEl) formData.append('additional_notes', notesEl.value);
                    formData.append('agree_terms', '1');

                    // Coupon code
                    if (registrationState.couponCode) {
                        formData.append('coupon_code', registrationState.couponCode);
                    }

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

                            // Check if payment is required (v1.71.0)
                            if (data.data.requires_payment && data.data.checkout_url) {
                                // Redirect to checkout for payment
                                setTimeout(function() {
                                    window.location.href = data.data.checkout_url;
                                }, 1000);
                            } else {
                                // Free event - reload page
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            }
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
         * Load institution members for registration (v1.69.0)
         */
        loadInstitutionMembers: function(eventId, nonce) {
            const self = this;
            const memberList = document.getElementById('eau-member-list');
            const memberSelectionPanel = document.getElementById('eau-member-selection');

            if (!memberList) return;

            // Show loading
            memberList.innerHTML = `
                <div class="eau-reg-member-loading">
                    <div class="eau-skeleton" style="height: 40px; margin-bottom: 8px;"></div>
                    <div class="eau-skeleton" style="height: 40px; margin-bottom: 8px;"></div>
                    <div class="eau-skeleton" style="height: 40px;"></div>
                </div>
            `;

            const formData = new FormData();
            formData.append('action', 'eau_get_institution_members_for_event');
            formData.append('event_id', eventId);
            formData.append('nonce', nonce);

            fetch(eauEventsFrontendData.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update institution name and logo in the header
                    const institutionHeader = memberSelectionPanel ? memberSelectionPanel.querySelector('.eau-reg-institution-header') : null;
                    if (institutionHeader) {
                        const logoContainer = institutionHeader.querySelector('.eau-reg-institution-logo');
                        const logoEl = logoContainer ? logoContainer.querySelector('img') : null;
                        const iconEl = logoContainer ? logoContainer.querySelector('.eau-reg-info-icon') : null;
                        const nameEl = institutionHeader.querySelector('.eau-reg-institution-name');

                        // Update name
                        if (nameEl) {
                            nameEl.textContent = data.data.institution_name || 'Your Institution';
                        }

                        // Update logo/icon visibility
                        if (data.data.institution_logo) {
                            if (logoEl) {
                                logoEl.src = data.data.institution_logo;
                                logoEl.style.display = '';
                            }
                            if (iconEl) {
                                iconEl.style.display = 'none';
                            }
                        } else {
                            if (logoEl) {
                                logoEl.style.display = 'none';
                            }
                            if (iconEl) {
                                iconEl.style.display = '';
                            }
                        }
                    }

                    self.renderMemberList(data.data.members, memberList);
                } else {
                    memberList.innerHTML = '<p style="padding: 12px; color: #dc2626;">' + (data.data.message || 'Failed to load members.') + '</p>';
                }
            })
            .catch(error => {
                memberList.innerHTML = '<p style="padding: 12px; color: #dc2626;">An error occurred loading members.</p>';
            });
        },

        /**
         * Render member list (v1.69.0)
         */
        renderMemberList: function(members, container) {
            const self = this;

            if (members.length === 0) {
                container.innerHTML = '<p style="padding: 12px; color: #6b7280;">No members found in your institution.</p>';
                return;
            }

            let html = '';
            members.forEach(member => {
                const disabledClass = member.already_registered ? 'disabled' : '';
                const currentUserClass = member.is_current_user ? 'current-user' : '';
                let badge = '';

                if (member.already_registered) {
                    badge = '<span class="eau-reg-member-badge registered">Already Registered</span>';
                } else if (member.is_current_user) {
                    badge = '<span class="eau-reg-member-badge you">(You)</span>';
                }

                html += `
                    <label class="eau-reg-member-item ${disabledClass} ${currentUserClass}">
                        <input type="checkbox" value="${member.user_id}" ${member.already_registered ? 'disabled' : ''}>
                        <div class="eau-reg-member-info">
                            <span class="eau-reg-member-name">${member.name}</span>
                            <span class="eau-reg-member-email">${member.email}</span>
                        </div>
                        ${badge}
                    </label>
                `;
            });

            container.innerHTML = html;

            // Bind checkbox events
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const memberId = parseInt(this.value);
                    const item = this.closest('.eau-reg-member-item');

                    if (this.checked) {
                        if (!self.registrationState.selectedMembers.includes(memberId)) {
                            self.registrationState.selectedMembers.push(memberId);
                        }
                        item.classList.add('selected');
                    } else {
                        self.registrationState.selectedMembers = self.registrationState.selectedMembers.filter(id => id !== memberId);
                        item.classList.remove('selected');
                    }

                    self.updatePriceSummary(self.registrationState);
                });
            });
        },

        /**
         * Validate coupon code (v1.69.0)
         */
        validateCoupon: function(code, eventId, nonce, state, resultEl) {
            const self = this;

            resultEl.innerHTML = '<span style="color: #6b7280;">Validating...</span>';

            const memberCount = state.type === 'others' ? Math.max(state.selectedMembers.length, 1) : 1;
            const orderTotal = state.eventPrice * memberCount;

            const formData = new FormData();
            formData.append('action', 'eau_validate_coupon');
            formData.append('code', code);
            formData.append('event_id', eventId);
            formData.append('nonce', nonce);
            formData.append('order_total', orderTotal);
            formData.append('member_count', memberCount);

            fetch(eauEventsFrontendData.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    state.couponCode = code;
                    state.couponData = data.data;
                    state.discountAmount = parseFloat(data.data.discount_amount);

                    // Show success message with remove button
                    resultEl.innerHTML = `
                        <div class="eau-reg-coupon-applied">
                            <span class="eau-reg-coupon-applied-text">
                                <span style="color: #10b981;">&#10003;</span>
                                Coupon applied: ${data.data.formatted_discount} off
                            </span>
                            <button type="button" class="eau-reg-coupon-remove" title="Remove coupon">&times;</button>
                        </div>
                    `;
                    resultEl.className = 'eau-reg-coupon-result success';

                    // Hide input and apply button
                    document.getElementById('eau-coupon-code').style.display = 'none';
                    document.getElementById('eau-apply-coupon').style.display = 'none';

                    // Bind remove button
                    const removeBtn = resultEl.querySelector('.eau-reg-coupon-remove');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function() {
                            self.removeCoupon(state, resultEl);
                        });
                    }

                    // Store coupon ID
                    const couponIdEl = document.getElementById('eau-coupon-id');
                    if (couponIdEl) {
                        couponIdEl.value = data.data.coupon_id;
                    }

                    self.updatePriceSummary(state);
                } else {
                    resultEl.innerHTML = '<span class="error">' + (data.data.message || 'Invalid coupon code.') + '</span>';
                    resultEl.className = 'eau-reg-coupon-result error';
                    state.couponCode = '';
                    state.couponData = null;
                    state.discountAmount = 0;
                    self.updatePriceSummary(state);
                }
            })
            .catch(error => {
                resultEl.innerHTML = '<span class="error">An error occurred. Please try again.</span>';
                resultEl.className = 'eau-reg-coupon-result error';
            });
        },

        /**
         * Remove applied coupon (v1.69.0)
         */
        removeCoupon: function(state, resultEl) {
            state.couponCode = '';
            state.couponData = null;
            state.discountAmount = 0;

            resultEl.innerHTML = '';
            resultEl.className = 'eau-reg-coupon-result';

            // Show input and apply button
            const couponInput = document.getElementById('eau-coupon-code');
            const applyBtn = document.getElementById('eau-apply-coupon');
            if (couponInput) {
                couponInput.style.display = '';
                couponInput.value = '';
            }
            if (applyBtn) applyBtn.style.display = '';

            // Clear coupon ID
            const couponIdEl = document.getElementById('eau-coupon-id');
            if (couponIdEl) couponIdEl.value = '';

            this.updatePriceSummary(state);
        },

        /**
         * Update price summary display (v1.69.0)
         */
        updatePriceSummary: function(state) {
            const unitPriceEl = document.getElementById('eau-unit-price');
            const qtyRow = document.getElementById('eau-qty-row');
            const qtyValueEl = document.getElementById('eau-qty-value');
            const subtotalRow = document.getElementById('eau-subtotal-row');
            const subtotalValueEl = document.getElementById('eau-subtotal-value');
            const discountRow = document.getElementById('eau-discount-row');
            const discountValueEl = document.getElementById('eau-discount-value');
            const totalValueEl = document.getElementById('eau-total-value');
            const selectedCountEl = document.getElementById('eau-selected-count');

            // Calculate quantity
            const qty = state.type === 'others' ? state.selectedMembers.length : 1;

            // Update selected count display
            if (selectedCountEl) {
                selectedCountEl.textContent = state.type === 'others' ? state.selectedMembers.length : 0;
            }

            // Calculate subtotal
            const subtotal = state.eventPrice * qty;

            // Calculate discount
            let discount = 0;
            if (state.couponData) {
                if (state.couponData.discount_type === 'percentage') {
                    discount = (subtotal * parseFloat(state.couponData.discount_value)) / 100;
                    // Apply max discount if set
                    if (state.couponData.max_discount && discount > parseFloat(state.couponData.max_discount)) {
                        discount = parseFloat(state.couponData.max_discount);
                    }
                } else {
                    discount = parseFloat(state.couponData.discount_value);
                }
                // Ensure discount doesn't exceed subtotal
                if (discount > subtotal) discount = subtotal;
            }

            // Calculate total
            const total = Math.max(0, subtotal - discount);

            // Update display
            if (qty > 1) {
                if (qtyRow) qtyRow.style.display = '';
                if (qtyValueEl) qtyValueEl.textContent = qty;
                if (subtotalRow) subtotalRow.style.display = '';
                if (subtotalValueEl) subtotalValueEl.textContent = '$' + subtotal.toFixed(2);
            } else {
                if (qtyRow) qtyRow.style.display = 'none';
                if (subtotalRow) subtotalRow.style.display = 'none';
            }

            if (discount > 0) {
                if (discountRow) discountRow.style.display = '';
                if (discountValueEl) discountValueEl.textContent = '-$' + discount.toFixed(2);
            } else {
                if (discountRow) discountRow.style.display = 'none';
            }

            if (totalValueEl) {
                totalValueEl.textContent = total === 0 ? 'Free' : '$' + total.toFixed(2);
            }

            // Store calculated values
            state.totalPrice = total;
            state.discountAmount = discount;
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
         * Initialize purchase access buttons (v1.68.10)
         */
        initPurchaseAccess: function() {
            const self = this;

            // Free access button
            const freeAccessBtn = document.querySelector('.eau-event-get-access-btn');
            if (freeAccessBtn) {
                freeAccessBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.handlePurchaseAccess(this, false);
                });
            }

            // Paid access button
            const paidAccessBtn = document.querySelector('.eau-event-purchase-access-btn');
            if (paidAccessBtn) {
                paidAccessBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.handlePurchaseAccess(this, true);
                });
            }
        },

        /**
         * Handle purchase access request (v1.68.10)
         */
        handlePurchaseAccess: function(button, isPaid) {
            const self = this;
            const eventId = button.dataset.eventId;
            const originalText = button.innerHTML;

            if (!eventId) {
                self.showToast('Invalid event.', 'error');
                return;
            }

            // Show loading state
            button.disabled = true;
            button.innerHTML = `
                <svg class="eau-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                </svg>
                ${isPaid ? 'Processing...' : 'Granting access...'}
            `;

            // Add spinner animation
            if (!document.querySelector('#eau-spinner-styles')) {
                const style = document.createElement('style');
                style.id = 'eau-spinner-styles';
                style.textContent = `
                    @keyframes eauSpin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                    .eau-spinner {
                        animation: eauSpin 1s linear infinite;
                        margin-right: 8px;
                    }
                `;
                document.head.appendChild(style);
            }

            // Get nonce from page
            const nonceEl = document.getElementById('eau-reg-nonce');
            const nonce = nonceEl ? nonceEl.value : (typeof eauEventsFrontendData !== 'undefined' ? eauEventsFrontendData.nonce : '');

            const formData = new FormData();
            formData.append('action', 'eau_purchase_recording_access');
            formData.append('event_id', eventId);
            formData.append('nonce', nonce);

            fetch(eauEventsFrontendData.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                button.disabled = false;
                button.innerHTML = originalText;

                if (data.success) {
                    if (data.data.redirect && data.data.checkout_url) {
                        // Redirect to payment
                        self.showToast(data.data.message, 'info');
                        setTimeout(() => {
                            window.location.href = data.data.checkout_url;
                        }, 1000);
                    } else {
                        // Free access granted - reload page
                        self.showToast(data.data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    self.showToast(data.data.message || 'An error occurred.', 'error');
                }
            })
            .catch(error => {
                button.disabled = false;
                button.innerHTML = originalText;
                self.showToast('An error occurred. Please try again.', 'error');
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
