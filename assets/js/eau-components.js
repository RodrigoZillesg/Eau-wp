/**
 * EAU System - Componentes JS
 * Versão: 1.9.0
 */

(function($) {
    'use strict';

    /**
     * Inicializa Lucide Icons
     */
    function initLucideIcons() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        } else {
            console.warn('EAU Components: Lucide not loaded yet, retrying...');
            setTimeout(initLucideIcons, 100);
        }
    }

    /**
     * Init
     */
    $(document).ready(function() {
        initLucideIcons();
    });

    // Re-init icons after AJAX
    $(document).on('eau:icons:refresh', function() {
        initLucideIcons();
    });

})(jQuery);
