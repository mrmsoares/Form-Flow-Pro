/**
 * FormFlow Pro - Elementor Frontend Integration
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Elementor Frontend Handler
     */
    const FormFlowElementorFrontend = {
        /**
         * Initialize
         */
        init() {
            $(window).on('elementor/frontend/init', () => {
                this.initWidgets();
            });

            // Also initialize for non-Elementor pages
            $(document).ready(() => {
                this.initForms();
            });
        },

        /**
         * Initialize Elementor widgets
         */
        initWidgets() {
            elementorFrontend.hooks.addAction(
                'frontend/element_ready/formflow-form.default',
                ($scope) => {
                    this.initFormWidget($scope);
                }
            );
        },

        /**
         * Initialize form widget
         */
        initFormWidget($scope) {
            const $form = $scope.find('.formflow-ajax-form');

            if ($form.length) {
                this.setupAjaxForm($form);
            }
        },

        /**
         * Initialize all forms on page
         */
        initForms() {
            $('.formflow-ajax-form').each((index, form) => {
                this.setupAjaxForm($(form));
            });
        },

        /**
         * Setup AJAX form submission
         */
        setupAjaxForm($form) {
            // Prevent multiple initializations
            if ($form.data('formflow-initialized')) {
                return;
            }

            $form.data('formflow-initialized', true);

            $form.on('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit($form);
            });

            // Add real-time validation
            $form.find('input, textarea, select').on('blur', function () {
                FormFlowElementorFrontend.validateField($(this));
            });
        },

        /**
         * Handle form submission
         */
        handleFormSubmit($form) {
            const $submitButton = $form.find('button[type="submit"]');
            const $messagesContainer = $form.find('.formflow-form-messages');
            const originalButtonText = $submitButton.text();

            // Clear previous messages
            $messagesContainer.empty().removeClass('success error');

            // Validate all fields
            if (!this.validateForm($form)) {
                this.showMessage(
                    $messagesContainer,
                    'Please fill in all required fields correctly.',
                    'error'
                );
                return;
            }

            // Disable submit button
            $submitButton.prop('disabled', true).text('Submitting...');

            // Prepare form data
            const formData = new FormData($form[0]);

            // Submit via AJAX
            $.ajax({
                url: formflowElementor.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.handleSuccess($form, response.data);
                    } else {
                        this.handleError($form, response.data.message || 'Submission failed');
                    }
                },
                error: (xhr, status, error) => {
                    this.handleError($form, 'An unexpected error occurred. Please try again.');
                },
                complete: () => {
                    $submitButton.prop('disabled', false).text(originalButtonText);
                }
            });
        },

        /**
         * Handle successful submission
         */
        handleSuccess($form, data) {
            const $messagesContainer = $form.find('.formflow-form-messages');
            const successMessage = $form.data('success-message') || 'Thank you! Your submission has been received.';
            const redirectUrl = $form.data('redirect');

            // Show success message
            this.showMessage($messagesContainer, successMessage, 'success');

            // Reset form
            $form[0].reset();
            $form.find('.formflow-field').removeClass('has-error');

            // Trigger custom event
            $form.trigger('formflow:submit:success', [data]);

            // Handle digital signature if required
            if (data.signature_url) {
                window.location.href = data.signature_url;
                return;
            }

            // Redirect if URL is set
            if (redirectUrl) {
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 2000);
            }

            // Scroll to message
            this.scrollToMessage($messagesContainer);
        },

        /**
         * Handle submission error
         */
        handleError($form, errorMessage) {
            const $messagesContainer = $form.find('.formflow-form-messages');
            const customErrorMessage = $form.data('error-message') || errorMessage;

            this.showMessage($messagesContainer, customErrorMessage, 'error');

            // Trigger custom event
            $form.trigger('formflow:submit:error', [errorMessage]);

            // Scroll to message
            this.scrollToMessage($messagesContainer);
        },

        /**
         * Validate entire form
         */
        validateForm($form) {
            let isValid = true;

            $form.find('input, textarea, select').each(function () {
                const $field = $(this);
                if (!FormFlowElementorFrontend.validateField($field)) {
                    isValid = false;
                }
            });

            return isValid;
        },

        /**
         * Validate single field
         */
        validateField($field) {
            const $fieldContainer = $field.closest('.formflow-field');
            const isRequired = $field.prop('required');
            const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();
            const value = $field.val();

            // Remove previous error state
            $fieldContainer.removeClass('has-error');
            $fieldContainer.find('.field-error').remove();

            // Check if required field is empty
            if (isRequired && !value) {
                this.showFieldError($fieldContainer, 'This field is required');
                return false;
            }

            // Validate email
            if (fieldType === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    this.showFieldError($fieldContainer, 'Please enter a valid email address');
                    return false;
                }
            }

            // Validate URL
            if (fieldType === 'url' && value) {
                try {
                    new URL(value);
                } catch (e) {
                    this.showFieldError($fieldContainer, 'Please enter a valid URL');
                    return false;
                }
            }

            // Validate number
            if (fieldType === 'number' && value) {
                const min = parseFloat($field.attr('min'));
                const max = parseFloat($field.attr('max'));
                const numValue = parseFloat(value);

                if (!isNaN(min) && numValue < min) {
                    this.showFieldError($fieldContainer, `Value must be at least ${min}`);
                    return false;
                }

                if (!isNaN(max) && numValue > max) {
                    this.showFieldError($fieldContainer, `Value must be at most ${max}`);
                    return false;
                }
            }

            return true;
        },

        /**
         * Show field error
         */
        showFieldError($fieldContainer, message) {
            $fieldContainer.addClass('has-error');
            $fieldContainer.append(`<span class="field-error">${message}</span>`);
        },

        /**
         * Show message
         */
        showMessage($container, message, type) {
            const icon = type === 'success' ? '✓' : '✗';
            const html = `
                <div class="formflow-message formflow-message-${type}">
                    <span class="message-icon">${icon}</span>
                    <span class="message-text">${message}</span>
                </div>
            `;

            $container.html(html).addClass(type).fadeIn();
        },

        /**
         * Scroll to message
         */
        scrollToMessage($container) {
            if ($container.length) {
                $('html, body').animate({
                    scrollTop: $container.offset().top - 100
                }, 500);
            }
        }
    };

    // Initialize
    FormFlowElementorFrontend.init();

})(jQuery);
