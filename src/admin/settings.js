/**
 * FormFlow Pro - Settings Page
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Settings Manager
     */
    const SettingsManager = {
        /**
         * Initialize
         */
        init() {
            this.setupEventListeners();
            this.setupValidation();
            console.log('FormFlow Settings initialized');
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Copy webhook URL
            $('.copy-webhook-url').on('click', () => {
                this.copyWebhookUrl();
            });

            // Test API connection
            $('#test-api-connection').on('click', () => {
                this.testApiConnection();
            });

            // Form validation
            $('.settings-form').on('submit', (e) => {
                return this.validateForm(e);
            });

            // Cache driver change detection
            $('#cache_driver').on('change', (e) => {
                this.checkCacheDriverAvailability($(e.currentTarget).val());
            });

            // Tab switching with keyboard
            $('.nav-tab').on('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = $(e.currentTarget).attr('href');
                }
            });
        },

        /**
         * Copy webhook URL to clipboard
         */
        copyWebhookUrl() {
            const webhookUrl = $('#autentique_webhook_url').val();
            const $button = $('.copy-webhook-url');

            // Modern clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(webhookUrl).then(() => {
                    this.showCopySuccess($button);
                }).catch(() => {
                    this.fallbackCopy(webhookUrl, $button);
                });
            } else {
                this.fallbackCopy(webhookUrl, $button);
            }
        },

        /**
         * Fallback copy method for older browsers
         */
        fallbackCopy(text, $button) {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            this.showCopySuccess($button);
        },

        /**
         * Show copy success feedback
         */
        showCopySuccess($button) {
            const originalHtml = $button.html();
            $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
            $button.addClass('button-success');

            setTimeout(() => {
                $button.html(originalHtml);
                $button.removeClass('button-success');
            }, 2000);
        },

        /**
         * Test Autentique API connection
         */
        testApiConnection() {
            const apiKey = $('#autentique_api_key').val();
            const $button = $('#test-api-connection');
            const $result = $('#api-test-result');

            if (!apiKey) {
                $result.html('<div class="notice notice-error inline" style="margin-top: 10px;"><p>Please enter an API key first.</p></div>');
                return;
            }

            // Show loading
            $button.prop('disabled', true).text('Testing...');
            $result.html('<div class="notice notice-info inline" style="margin-top: 10px;"><p>Testing API connection...</p></div>');

            // Test connection via AJAX
            $.ajax({
                url: formflowData.ajax_url,
                type: 'POST',
                data: {
                    action: 'formflow_test_api_connection',
                    api_key: apiKey,
                    nonce: formflowData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $result.html(`
                            <div class="notice notice-success inline" style="margin-top: 10px;">
                                <p><strong>✓ Connection successful!</strong></p>
                                <p>${response.data.message || 'API is working correctly.'}</p>
                            </div>
                        `);
                    } else {
                        $result.html(`
                            <div class="notice notice-error inline" style="margin-top: 10px;">
                                <p><strong>✗ Connection failed</strong></p>
                                <p>${response.data.message || 'Could not connect to Autentique API.'}</p>
                            </div>
                        `);
                    }
                },
                error: () => {
                    $result.html(`
                        <div class="notice notice-error inline" style="margin-top: 10px;">
                            <p><strong>✗ Connection error</strong></p>
                            <p>An unexpected error occurred. Please try again.</p>
                        </div>
                    `);
                },
                complete: () => {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        /**
         * Setup form validation
         */
        setupValidation() {
            // Add validation for email fields
            $('input[type="email"]').on('blur', function() {
                const email = $(this).val();
                if (email && !SettingsManager.isValidEmail(email)) {
                    $(this).addClass('invalid');
                    $(this).siblings('.description').append('<span class="error-message" style="color: #dc3232;"> Invalid email format</span>');
                } else {
                    $(this).removeClass('invalid');
                    $(this).siblings('.description').find('.error-message').remove();
                }
            });

            // Add validation for number fields
            $('input[type="number"]').on('blur', function() {
                const value = parseInt($(this).val());
                const min = parseInt($(this).attr('min'));
                const max = parseInt($(this).attr('max'));

                if (min && value < min) {
                    $(this).val(min);
                }
                if (max && value > max) {
                    $(this).val(max);
                }
            });
        },

        /**
         * Validate form before submission
         */
        validateForm(e) {
            const $form = $(e.target);
            let isValid = true;
            const errors = [];

            // Get active tab
            const activeTab = $('input[name="current_tab"]').val();

            // Tab-specific validation
            if (activeTab === 'general') {
                const companyEmail = $('#company_email').val();
                if (companyEmail && !this.isValidEmail(companyEmail)) {
                    errors.push('Invalid company email format');
                    isValid = false;
                }
            }

            if (activeTab === 'autentique') {
                const apiKey = $('#autentique_api_key').val();
                if (!apiKey) {
                    errors.push('API Key is required');
                    isValid = false;
                }
            }

            if (activeTab === 'email') {
                const fromEmail = $('#email_from_address').val();
                if (fromEmail && !this.isValidEmail(fromEmail)) {
                    errors.push('Invalid from email address');
                    isValid = false;
                }
            }

            // Show errors if any
            if (!isValid) {
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }

            return true;
        },

        /**
         * Validate email format
         */
        isValidEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * Check cache driver availability
         */
        checkCacheDriverAvailability(driver) {
            if (driver === 'redis' || driver === 'memcached') {
                $.ajax({
                    url: formflowData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'formflow_check_cache_driver',
                        driver: driver,
                        nonce: formflowData.nonce
                    },
                    success: (response) => {
                        if (!response.success) {
                            const message = driver === 'redis' ?
                                'Redis extension is not available on your server. Please install it or use a different cache driver.' :
                                'Memcached extension is not available on your server. Please install it or use a different cache driver.';

                            const notice = $(`
                                <div class="notice notice-warning inline cache-driver-warning" style="margin-top: 10px;">
                                    <p><strong>Warning:</strong> ${message}</p>
                                </div>
                            `);

                            $('.cache-driver-warning').remove();
                            $('#cache_driver').closest('td').append(notice);
                        } else {
                            $('.cache-driver-warning').remove();
                        }
                    }
                });
            } else {
                $('.cache-driver-warning').remove();
            }
        },

        /**
         * Confirm destructive actions
         */
        confirmDestructiveAction(message) {
            return confirm(message || 'Are you sure you want to proceed? This action cannot be undone.');
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        SettingsManager.init();
    });

})(jQuery);
