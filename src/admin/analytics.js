/**
 * FormFlow Pro - Analytics Dashboard
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    const Analytics = {
        init() {
            this.loadCharts();
            console.log('Analytics initialized');
        },

        loadCharts() {
            // Load analytics charts
        }
    };

    $(document).ready(() => {
        Analytics.init();
    });

})(jQuery);
