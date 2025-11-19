/**
 * FormFlow Pro - Admin Main Script
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Initialize admin functionality
     */
    const FormFlowAdmin = {
        /**
         * Initialize
         */
        init() {
            this.setupEventListeners();
            this.initTooltips();
            console.log('FormFlow Pro Admin initialized');
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Add your event listeners here
        },

        /**
         * Initialize tooltips
         */
        initTooltips() {
            // Initialize tooltips if needed
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        FormFlowAdmin.init();
    });

})(jQuery);
