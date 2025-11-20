/**
 * Eau Toast Notification System
 * Sistema profissional de notificações que persiste entre page reloads
 */

(function() {
    'use strict';

    const EauToast = {
        container: null,
        toasts: [],
        defaultDuration: 5000,

        /**
         * Inicializa o sistema de toasts
         */
        init: function() {
            // Cria container se não existir
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'eau-toast-container';
                document.body.appendChild(this.container);
            }

            // Verifica se há toasts pendentes no sessionStorage
            this.checkPendingToasts();
        },

        /**
         * Verifica e exibe toasts pendentes salvos antes de reload
         */
        checkPendingToasts: function() {
            const pending = sessionStorage.getItem('eau_pending_toast');
            if (pending) {
                try {
                    const data = JSON.parse(pending);
                    sessionStorage.removeItem('eau_pending_toast');

                    // Pequeno delay para garantir que a página carregou
                    setTimeout(() => {
                        this.show(data.type, data.title, data.message, data.duration);
                    }, 500);
                } catch (e) {
                    console.error('Erro ao processar toast pendente:', e);
                }
            }
        },

        /**
         * Salva toast para exibir após reload
         */
        saveForReload: function(type, title, message, duration) {
            const data = {
                type: type,
                title: title,
                message: message,
                duration: duration || this.defaultDuration
            };
            sessionStorage.setItem('eau_pending_toast', JSON.stringify(data));
        },

        /**
         * Exibe um toast
         */
        show: function(type, title, message, duration) {
            if (!this.container) {
                this.init();
            }

            duration = duration || this.defaultDuration;

            // Cria o elemento do toast
            const toast = document.createElement('div');
            toast.className = `eau-toast ${type}`;

            // Ícone baseado no tipo
            const icons = {
                success: 'yes-alt',
                error: 'dismiss',
                warning: 'warning',
                info: 'info'
            };

            const icon = icons[type] || 'info';

            toast.innerHTML = `
                <div class="eau-toast-icon">
                    <span class="dashicons dashicons-${icon}"></span>
                </div>
                <div class="eau-toast-content">
                    <p class="eau-toast-title">${this.escapeHtml(title)}</p>
                    ${message ? `<p class="eau-toast-message">${this.escapeHtml(message)}</p>` : ''}
                </div>
                <button class="eau-toast-close" type="button">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <div class="eau-toast-progress"></div>
            `;

            // Adiciona ao container
            this.container.appendChild(toast);
            this.toasts.push(toast);

            // Event listener para fechar
            const closeBtn = toast.querySelector('.eau-toast-close');
            closeBtn.addEventListener('click', () => {
                this.remove(toast);
            });

            // Auto-remove após duração
            if (duration > 0) {
                setTimeout(() => {
                    this.remove(toast);
                }, duration);
            }

            return toast;
        },

        /**
         * Remove um toast
         */
        remove: function(toast) {
            toast.classList.add('removing');

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }

                const index = this.toasts.indexOf(toast);
                if (index > -1) {
                    this.toasts.splice(index, 1);
                }
            }, 300);
        },

        /**
         * Atalhos para tipos específicos
         */
        success: function(title, message, duration) {
            return this.show('success', title, message, duration);
        },

        error: function(title, message, duration) {
            return this.show('error', title, message, duration);
        },

        warning: function(title, message, duration) {
            return this.show('warning', title, message, duration);
        },

        info: function(title, message, duration) {
            return this.show('info', title, message, duration);
        },

        /**
         * Escapa HTML para prevenir XSS
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Expõe globalmente
    window.EauToast = EauToast;

    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            EauToast.init();
        });
    } else {
        EauToast.init();
    }

})();
