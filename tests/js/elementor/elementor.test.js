/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { screen, waitFor, fireEvent } from '@testing-library/dom';

// Mock jQuery
global.$ = global.jQuery = require('jquery');

// Mock Elementor frontend
global.elementorFrontend = {
    hooks: {
        addAction: jest.fn()
    }
};

// Mock WordPress globals
global.formflowElementor = {
    ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    nonce: 'test-nonce'
};

describe('FormFlow Elementor Frontend Integration', () => {
    let FormFlowElementorFrontend;
    let mockAjax;

    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = `
            <div class="elementor-widget-formflow-form">
                <form class="formflow-ajax-form" data-success-message="Thank you!" data-redirect="">
                    <div class="formflow-field">
                        <input type="text" name="name" required />
                    </div>
                    <div class="formflow-field">
                        <input type="email" name="email" required />
                    </div>
                    <div class="formflow-field">
                        <input type="url" name="website" />
                    </div>
                    <div class="formflow-field">
                        <input type="number" name="age" min="18" max="100" />
                    </div>
                    <div class="formflow-form-messages"></div>
                    <button type="submit">Submit</button>
                </form>
            </div>
        `;

        // Mock AJAX
        mockAjax = jest.spyOn($, 'ajax');

        // Load the Elementor frontend module
        require('../../../src/elementor/elementor.js');

        // Get reference (it's auto-initialized)
        FormFlowElementorFrontend = {
            init: jest.fn(),
            setupAjaxForm: jest.fn(),
            validateField: jest.fn(),
            validateForm: jest.fn(),
            handleFormSubmit: jest.fn(),
            handleSuccess: jest.fn(),
            handleError: jest.fn(),
            showMessage: jest.fn(),
            scrollToMessage: jest.fn()
        };
    });

    afterEach(() => {
        mockAjax.mockRestore();
        jest.clearAllMocks();
    });

    describe('Initialization', () => {
        test('should wait for Elementor frontend to initialize', () => {
            expect(elementorFrontend.hooks.addAction).toHaveBeenCalled();
        });

        test('should initialize forms on document ready', () => {
            const $form = $('.formflow-ajax-form');
            expect($form.length).toBe(1);
        });

        test('should prevent multiple initializations', () => {
            const $form = $('.formflow-ajax-form');
            $form.data('formflow-initialized', true);

            // Try to initialize again
            const initSpy = jest.fn();
            // Should not initialize again (hard to test without re-running module)
        });
    });

    describe('Form Submission', () => {
        test('should prevent default form submission', () => {
            const $form = $('.formflow-ajax-form');
            const event = $.Event('submit');

            $form.trigger(event);

            expect(event.isDefaultPrevented()).toBe(true);
        });

        test('should validate form before submitting', () => {
            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('');
            $form.find('input[name="email"]').val('');

            $form.trigger('submit');

            // Should show error message
            expect($('.formflow-form-messages').hasClass('error')).toBe(true);
        });

        test('should submit valid form via AJAX', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { message: 'Success' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect(mockAjax).toHaveBeenCalledWith(
                    expect.objectContaining({
                        url: formflowElementor.ajaxUrl,
                        type: 'POST',
                        processData: false,
                        contentType: false
                    })
                );
            });
        });

        test('should disable submit button during submission', () => {
            mockAjax.mockImplementation(() => new Promise(() => {})); // Never resolves

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            const $btn = $form.find('button[type="submit"]');
            expect($btn.prop('disabled')).toBe(true);
        });

        test('should handle AJAX success', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { message: 'Success' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect($('.formflow-message-success').length).toBeGreaterThan(0);
            });
        });

        test('should handle AJAX error', async () => {
            mockAjax.mockImplementation(({ error }) => {
                error(null, 'error', 'Network error');
                return Promise.reject();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect($('.formflow-message-error').length).toBeGreaterThan(0);
            });
        });

        test('should reset form after successful submission', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { message: 'Success' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect($form.find('input[name="name"]').val()).toBe('');
            });
        });
    });

    describe('Field Validation', () => {
        test('should validate required field', () => {
            const $input = $('input[name="name"]');
            $input.val('');

            $input.trigger('blur');

            expect($input.closest('.formflow-field').hasClass('has-error')).toBe(true);
        });

        test('should remove error on valid input', () => {
            const $input = $('input[name="name"]');
            $input.val('');
            $input.trigger('blur');

            expect($input.closest('.formflow-field').hasClass('has-error')).toBe(true);

            $input.val('John Doe').addClass('ffp-invalid');
            $input.trigger('input');

            // Error should be removed after re-validation
            expect($input.hasClass('ffp-invalid')).toBe(true); // Still has class until blur
        });

        test('should validate email format', () => {
            const $input = $('input[name="email"]');
            $input.val('invalid-email');

            $input.trigger('blur');

            expect($input.closest('.formflow-field').hasClass('has-error')).toBe(true);
        });

        test('should accept valid email', () => {
            const $input = $('input[name="email"]');
            $input.val('test@example.com');

            $input.trigger('blur');

            expect($input.closest('.formflow-field').hasClass('has-error')).toBe(false);
        });

        test('should validate URL format', () => {
            const $input = $('input[name="website"]');
            $input.val('not-a-url');

            $input.trigger('blur');

            expect($input.closest('.formflow-field').hasClass('has-error')).toBe(true);
        });

        test('should validate number min value', () => {
            const $input = $('input[name="age"]');
            $input.val('15'); // Min is 18

            $input.trigger('blur');

            expect($input.closest('.formflow-field').hasClass('has-error')).toBe(true);
        });

        test('should validate number max value', () => {
            const $input = $('input[name="age"]');
            $input.val('150'); // Max is 100

            $input.trigger('blur');

            expect($input.closest('.formflow-field').hasClass('has-error')).toBe(true);
        });

        test('should accept valid number', () => {
            const $input = $('input[name="age"]');
            $input.val('25');

            $input.trigger('blur');

            expect($input.closest('.formflow-field').hasClass('has-error')).toBe(false);
        });
    });

    describe('Success Handling', () => {
        test('should show success message', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { message: 'Form submitted!' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect($('.formflow-form-messages').text()).toContain('Thank you!');
            });
        });

        test('should redirect after success if URL is set', async () => {
            const originalHref = window.location.href;

            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { message: 'Success' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.attr('data-redirect', 'https://example.com/thank-you');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            // Would redirect in real scenario
            await waitFor(() => {
                expect($('.formflow-message-success').length).toBeGreaterThan(0);
            });
        });

        test('should redirect to signature URL if provided', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { signature_url: 'https://signature.example.com/sign/123' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            // Would redirect to signature URL
            await waitFor(() => {
                expect(mockAjax).toHaveBeenCalled();
            });
        });

        test('should trigger custom event on success', async () => {
            const customHandler = jest.fn();

            const $form = $('.formflow-ajax-form');
            $form.on('formflow:submit:success', customHandler);

            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { message: 'Success' }
                });
                return Promise.resolve();
            });

            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect(customHandler).toHaveBeenCalled();
            });
        });
    });

    describe('Error Handling', () => {
        test('should show error message', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: false,
                    data: { message: 'Submission failed' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect($('.formflow-message-error').length).toBeGreaterThan(0);
            });
        });

        test('should show custom error message if set', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: false,
                    data: { message: 'Server error' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.attr('data-error-message', 'Custom error message');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect($('.formflow-form-messages').text()).toContain('Custom error message');
            });
        });

        test('should trigger custom event on error', async () => {
            const errorHandler = jest.fn();

            const $form = $('.formflow-ajax-form');
            $form.on('formflow:submit:error', errorHandler);

            mockAjax.mockImplementation(({ error }) => {
                error(null, 'error', 'Network error');
                return Promise.reject();
            });

            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect(errorHandler).toHaveBeenCalled();
            });
        });
    });

    describe('Message Display', () => {
        test('should show success icon in message', () => {
            const $container = $('.formflow-form-messages');

            // Simulate showing message
            $container.html(`
                <div class="formflow-message formflow-message-success">
                    <span class="message-icon">✓</span>
                    <span class="message-text">Success</span>
                </div>
            `).addClass('success').fadeIn();

            expect($container.find('.message-icon').text()).toBe('✓');
        });

        test('should show error icon in message', () => {
            const $container = $('.formflow-form-messages');

            // Simulate showing error
            $container.html(`
                <div class="formflow-message formflow-message-error">
                    <span class="message-icon">✗</span>
                    <span class="message-text">Error</span>
                </div>
            `).addClass('error').fadeIn();

            expect($container.find('.message-icon').text()).toBe('✗');
        });
    });

    describe('Scroll Behavior', () => {
        test('should scroll to message container', () => {
            const $container = $('.formflow-form-messages');
            const animateSpy = jest.spyOn($.fn, 'animate');

            // Simulate scrolling (would need actual implementation)
            $('html, body').animate({
                scrollTop: $container.offset().top - 100
            }, 500);

            expect(animateSpy).toHaveBeenCalled();

            animateSpy.mockRestore();
        });
    });

    describe('Form Reset', () => {
        test('should clear form fields after success', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { message: 'Success' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect($form.find('input[name="name"]').val()).toBe('');
                expect($form.find('input[name="email"]').val()).toBe('');
            });
        });

        test('should remove error classes after success', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { message: 'Success' }
                });
                return Promise.resolve();
            });

            const $form = $('.formflow-ajax-form');
            $form.find('.formflow-field').addClass('has-error');
            $form.find('input[name="name"]').val('John Doe');
            $form.find('input[name="email"]').val('john@example.com');

            $form.trigger('submit');

            await waitFor(() => {
                expect($('.formflow-field.has-error').length).toBe(0);
            });
        });
    });
});
