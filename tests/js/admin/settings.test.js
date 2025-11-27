/**
 * FormFlow Pro - Settings Page Tests
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

import { screen, fireEvent, waitFor } from '@testing-library/dom';
import '@testing-library/jest-dom';

describe('SettingsManager', () => {
  let SettingsManager;
  let consoleLogSpy;
  let mockAjax;

  beforeEach(() => {
    // Clear the document body before each test
    document.body.innerHTML = '';

    // Reset all mocks
    jest.clearAllMocks();

    // Spy on console.log
    consoleLogSpy = jest.spyOn(console, 'log').mockImplementation();

    // Mock jQuery AJAX
    mockAjax = jest.fn();
    global.jQuery.ajax = mockAjax;
    global.$.ajax = mockAjax;

    // Mock navigator.clipboard
    Object.assign(navigator, {
      clipboard: {
        writeText: jest.fn(() => Promise.resolve())
      }
    });

    // Mock document.execCommand
    document.execCommand = jest.fn(() => true);

    // Mock formflowData
    global.formflowData = {
      ajax_url: '/wp-admin/admin-ajax.php',
      nonce: 'test-nonce'
    };

    // Define SettingsManager object
    SettingsManager = {
      init() {
        this.setupEventListeners();
        this.setupValidation();
        console.log('FormFlow Settings initialized');
      },

      setupEventListeners() {
        $('.copy-webhook-url').on('click', () => {
          this.copyWebhookUrl();
        });
        $('#test-api-connection').on('click', () => {
          this.testApiConnection();
        });
        $('#toggle-api-key-visibility').on('click', () => {
          this.toggleApiKeyVisibility();
        });
        $('.settings-form').on('submit', (e) => {
          return this.validateForm(e);
        });
        $('#cache_driver').on('change', (e) => {
          this.checkCacheDriverAvailability($(e.currentTarget).val());
        });
        $('.nav-tab').on('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            window.location.href = $(e.currentTarget).attr('href');
          }
        });
      },

      copyWebhookUrl() {
        const webhookUrl = $('#autentique_webhook_url').val();
        const $button = $('.copy-webhook-url');

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

      fallbackCopy(text, $button) {
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
        this.showCopySuccess($button);
      },

      showCopySuccess($button) {
        const originalHtml = $button.html();
        $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
        $button.addClass('button-success');

        setTimeout(() => {
          $button.html(originalHtml);
          $button.removeClass('button-success');
        }, 2000);
      },

      toggleApiKeyVisibility() {
        const $input = $('#autentique_api_key');
        const $button = $('#toggle-api-key-visibility');
        const $icon = $button.find('.dashicons');

        if ($input.attr('type') === 'password') {
          $input.attr('type', 'text');
          $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
          $input.attr('type', 'password');
          $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
      },

      testApiConnection() {
        const apiKey = $('#autentique_api_key').val();
        const $button = $('#test-api-connection');
        const $result = $('#api-test-result');

        if (!apiKey) {
          $result.html('<div class="notice notice-error inline" style="margin-top: 10px;"><p>Please enter an API key first.</p></div>');
          return;
        }

        $button.prop('disabled', true).text('Testing...');
        $result.html('<div class="notice notice-info inline" style="margin-top: 10px;"><p>Testing API connection...</p></div>');

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

      setupValidation() {
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

      validateForm(e) {
        const $form = $(e.target);
        let isValid = true;
        const errors = [];

        const activeTab = $('input[name="current_tab"]').val();

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

        if (!isValid) {
          alert('Please fix the following errors:\n\n' + errors.join('\n'));
          return false;
        }

        return true;
      },

      isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
      },

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

      confirmDestructiveAction(message) {
        return confirm(message || 'Are you sure you want to proceed? This action cannot be undone.');
      }
    };
  });

  afterEach(() => {
    consoleLogSpy.mockRestore();
    jest.clearAllTimers();
  });

  describe('Initialization', () => {
    test('should initialize SettingsManager object', () => {
      expect(SettingsManager).toBeDefined();
      expect(typeof SettingsManager.init).toBe('function');
    });

    test('should log initialization message', () => {
      SettingsManager.init();
      expect(consoleLogSpy).toHaveBeenCalledWith('FormFlow Settings initialized');
    });

    test('should call setupEventListeners on init', () => {
      const setupSpy = jest.spyOn(SettingsManager, 'setupEventListeners');
      SettingsManager.init();
      expect(setupSpy).toHaveBeenCalled();
    });

    test('should call setupValidation on init', () => {
      const validationSpy = jest.spyOn(SettingsManager, 'setupValidation');
      SettingsManager.init();
      expect(validationSpy).toHaveBeenCalled();
    });
  });

  describe('Copy Webhook URL', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <input type="text" id="autentique_webhook_url" value="https://example.com/webhook">
        <button class="copy-webhook-url">Copy</button>
      `;
    });

    test('should copy webhook URL using clipboard API', async () => {
      const $button = {
        html: jest.fn(),
        addClass: jest.fn(),
        removeClass: jest.fn()
      };

      global.jQuery.mockReturnValue({
        val: jest.fn(() => 'https://example.com/webhook'),
        html: $button.html,
        addClass: $button.addClass,
        removeClass: $button.removeClass
      });
      global.$ = global.jQuery;

      await SettingsManager.copyWebhookUrl();

      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('https://example.com/webhook');
    });

    test('should use fallback copy method when clipboard API fails', () => {
      navigator.clipboard.writeText = jest.fn(() => Promise.reject());

      const fallbackSpy = jest.spyOn(SettingsManager, 'fallbackCopy');
      SettingsManager.copyWebhookUrl();

      setTimeout(() => {
        expect(fallbackSpy).toHaveBeenCalled();
      }, 100);
    });

    test('should show copy success message', () => {
      const $button = {
        html: jest.fn(),
        addClass: jest.fn(),
        removeClass: jest.fn()
      };

      SettingsManager.showCopySuccess($button);

      expect($button.html).toHaveBeenCalledWith('<span class="dashicons dashicons-yes"></span> Copied!');
      expect($button.addClass).toHaveBeenCalledWith('button-success');
    });
  });

  describe('API Key Visibility Toggle', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <input type="password" id="autentique_api_key">
        <button id="toggle-api-key-visibility">
          <span class="dashicons dashicons-visibility"></span>
        </button>
      `;
    });

    test('should toggle API key visibility to text', () => {
      const $input = {
        attr: jest.fn((attr, value) => {
          if (value === undefined) return 'password';
          return $input;
        })
      };

      const $icon = {
        removeClass: jest.fn(() => $icon),
        addClass: jest.fn(() => $icon)
      };

      const $button = {
        find: jest.fn(() => $icon)
      };

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#autentique_api_key') return $input;
        if (selector === '#toggle-api-key-visibility') return $button;
        return global.jQuery;
      });
      global.$ = global.jQuery;

      SettingsManager.toggleApiKeyVisibility();

      expect($input.attr).toHaveBeenCalledWith('type', 'text');
    });

    test('should toggle API key visibility to password', () => {
      const $input = {
        attr: jest.fn((attr, value) => {
          if (value === undefined) return 'text';
          return $input;
        })
      };

      const $icon = {
        removeClass: jest.fn(() => $icon),
        addClass: jest.fn(() => $icon)
      };

      const $button = {
        find: jest.fn(() => $icon)
      };

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#autentique_api_key') return $input;
        if (selector === '#toggle-api-key-visibility') return $button;
        return global.jQuery;
      });
      global.$ = global.jQuery;

      SettingsManager.toggleApiKeyVisibility();

      expect($input.attr).toHaveBeenCalledWith('type', 'password');
    });
  });

  describe('Test API Connection', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <input type="text" id="autentique_api_key" value="test-api-key">
        <button id="test-api-connection">Test Connection</button>
        <div id="api-test-result"></div>
      `;
    });

    test('should show error when API key is empty', () => {
      const $result = {
        html: jest.fn()
      };

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#autentique_api_key') return { val: jest.fn(() => '') };
        if (selector === '#api-test-result') return $result;
        return global.jQuery;
      });
      global.$ = global.jQuery;

      SettingsManager.testApiConnection();

      expect($result.html).toHaveBeenCalledWith(expect.stringContaining('Please enter an API key first'));
    });

    test('should make AJAX request with API key', () => {
      const mockJQuery = jest.fn((selector) => {
        if (selector === '#autentique_api_key') {
          return { val: jest.fn(() => 'test-api-key') };
        }
        if (selector === '#test-api-connection') {
          return {
            prop: jest.fn(() => mockJQuery(selector)),
            text: jest.fn(() => mockJQuery(selector))
          };
        }
        if (selector === '#api-test-result') {
          return { html: jest.fn() };
        }
        return mockJQuery;
      });

      global.jQuery = mockJQuery;
      global.$ = mockJQuery;
      global.$.ajax = mockAjax;

      SettingsManager.testApiConnection();

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        url: '/wp-admin/admin-ajax.php',
        type: 'POST',
        data: expect.objectContaining({
          action: 'formflow_test_api_connection',
          api_key: 'test-api-key',
          nonce: 'test-nonce'
        })
      }));
    });

    test('should handle successful API connection', () => {
      const $result = { html: jest.fn() };
      const $button = {
        prop: jest.fn(() => $button),
        text: jest.fn(() => $button)
      };

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#autentique_api_key') return { val: jest.fn(() => 'test-api-key') };
        if (selector === '#test-api-connection') return $button;
        if (selector === '#api-test-result') return $result;
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: true, data: { message: 'Connected!' } });
        options.complete();
      });

      SettingsManager.testApiConnection();

      expect($result.html).toHaveBeenCalledWith(expect.stringContaining('Connection successful'));
    });

    test('should handle failed API connection', () => {
      const $result = { html: jest.fn() };
      const $button = {
        prop: jest.fn(() => $button),
        text: jest.fn(() => $button)
      };

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#autentique_api_key') return { val: jest.fn(() => 'test-api-key') };
        if (selector === '#test-api-connection') return $button;
        if (selector === '#api-test-result') return $result;
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: false, data: { message: 'Invalid API key' } });
        options.complete();
      });

      SettingsManager.testApiConnection();

      expect($result.html).toHaveBeenCalledWith(expect.stringContaining('Connection failed'));
    });

    test('should handle AJAX error', () => {
      const $result = { html: jest.fn() };
      const $button = {
        prop: jest.fn(() => $button),
        text: jest.fn(() => $button)
      };

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#autentique_api_key') return { val: jest.fn(() => 'test-api-key') };
        if (selector === '#test-api-connection') return $button;
        if (selector === '#api-test-result') return $result;
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.error();
        options.complete();
      });

      SettingsManager.testApiConnection();

      expect($result.html).toHaveBeenCalledWith(expect.stringContaining('Connection error'));
    });
  });

  describe('Email Validation', () => {
    test('should validate correct email format', () => {
      expect(SettingsManager.isValidEmail('test@example.com')).toBe(true);
      expect(SettingsManager.isValidEmail('user.name@domain.co.uk')).toBe(true);
    });

    test('should reject invalid email format', () => {
      expect(SettingsManager.isValidEmail('invalid')).toBe(false);
      expect(SettingsManager.isValidEmail('test@')).toBe(false);
      expect(SettingsManager.isValidEmail('@example.com')).toBe(false);
      expect(SettingsManager.isValidEmail('test @example.com')).toBe(false);
    });
  });

  describe('Form Validation', () => {
    test('should validate general tab with valid email', () => {
      const mockEvent = { target: document.createElement('form') };

      global.jQuery.mockImplementation((selector) => {
        if (selector === 'input[name="current_tab"]') {
          return { val: jest.fn(() => 'general') };
        }
        if (selector === '#company_email') {
          return { val: jest.fn(() => 'company@example.com') };
        }
        return { val: jest.fn(() => '') };
      });
      global.$ = global.jQuery;

      const result = SettingsManager.validateForm(mockEvent);
      expect(result).toBe(true);
    });

    test('should reject general tab with invalid email', () => {
      window.alert = jest.fn();
      const mockEvent = { target: document.createElement('form') };

      global.jQuery.mockImplementation((selector) => {
        if (selector === 'input[name="current_tab"]') {
          return { val: jest.fn(() => 'general') };
        }
        if (selector === '#company_email') {
          return { val: jest.fn(() => 'invalid-email') };
        }
        return { val: jest.fn(() => '') };
      });
      global.$ = global.jQuery;

      const result = SettingsManager.validateForm(mockEvent);
      expect(result).toBe(false);
      expect(window.alert).toHaveBeenCalled();
    });

    test('should require API key on autentique tab', () => {
      window.alert = jest.fn();
      const mockEvent = { target: document.createElement('form') };

      global.jQuery.mockImplementation((selector) => {
        if (selector === 'input[name="current_tab"]') {
          return { val: jest.fn(() => 'autentique') };
        }
        if (selector === '#autentique_api_key') {
          return { val: jest.fn(() => '') };
        }
        return { val: jest.fn(() => '') };
      });
      global.$ = global.jQuery;

      const result = SettingsManager.validateForm(mockEvent);
      expect(result).toBe(false);
      expect(window.alert).toHaveBeenCalledWith(expect.stringContaining('API Key is required'));
    });

    test('should validate email tab', () => {
      window.alert = jest.fn();
      const mockEvent = { target: document.createElement('form') };

      global.jQuery.mockImplementation((selector) => {
        if (selector === 'input[name="current_tab"]') {
          return { val: jest.fn(() => 'email') };
        }
        if (selector === '#email_from_address') {
          return { val: jest.fn(() => 'invalid') };
        }
        return { val: jest.fn(() => '') };
      });
      global.$ = global.jQuery;

      const result = SettingsManager.validateForm(mockEvent);
      expect(result).toBe(false);
    });
  });

  describe('Cache Driver Availability', () => {
    test('should check Redis driver availability', () => {
      SettingsManager.checkCacheDriverAvailability('redis');

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_check_cache_driver',
          driver: 'redis'
        })
      }));
    });

    test('should check Memcached driver availability', () => {
      SettingsManager.checkCacheDriverAvailability('memcached');

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_check_cache_driver',
          driver: 'memcached'
        })
      }));
    });

    test('should not check for file driver', () => {
      SettingsManager.checkCacheDriverAvailability('file');

      expect(mockAjax).not.toHaveBeenCalled();
    });

    test('should show warning for unavailable driver', () => {
      const mockRemove = jest.fn();
      const mockClosest = jest.fn(() => ({
        append: jest.fn()
      }));

      global.jQuery.mockImplementation((selector) => {
        if (selector === '.cache-driver-warning') {
          return { remove: mockRemove };
        }
        if (selector === '#cache_driver') {
          return { closest: mockClosest };
        }
        if (typeof selector === 'string' && selector.includes('notice')) {
          return selector;
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: false });
      });

      SettingsManager.checkCacheDriverAvailability('redis');

      expect(mockRemove).toHaveBeenCalled();
    });
  });

  describe('Destructive Actions', () => {
    test('should confirm destructive action with default message', () => {
      window.confirm = jest.fn(() => true);

      const result = SettingsManager.confirmDestructiveAction();

      expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('Are you sure'));
      expect(result).toBe(true);
    });

    test('should confirm destructive action with custom message', () => {
      window.confirm = jest.fn(() => false);

      const result = SettingsManager.confirmDestructiveAction('Delete all data?');

      expect(window.confirm).toHaveBeenCalledWith('Delete all data?');
      expect(result).toBe(false);
    });
  });

  describe('Event Listeners Setup', () => {
    test('should setup webhook copy listener', () => {
      const mockOn = jest.fn();
      global.jQuery.mockImplementation((selector) => {
        if (selector === '.copy-webhook-url') {
          return { on: mockOn };
        }
        return { on: jest.fn() };
      });
      global.$ = global.jQuery;

      SettingsManager.setupEventListeners();

      expect(mockOn).toHaveBeenCalledWith('click', expect.any(Function));
    });

    test('should setup API test listener', () => {
      const mockOn = jest.fn();
      global.jQuery.mockImplementation((selector) => {
        if (selector === '#test-api-connection') {
          return { on: mockOn };
        }
        return { on: jest.fn() };
      });
      global.$ = global.jQuery;

      SettingsManager.setupEventListeners();

      expect(mockOn).toHaveBeenCalledWith('click', expect.any(Function));
    });

    test('should setup form submit listener', () => {
      const mockOn = jest.fn();
      global.jQuery.mockImplementation((selector) => {
        if (selector === '.settings-form') {
          return { on: mockOn };
        }
        return { on: jest.fn() };
      });
      global.$ = global.jQuery;

      SettingsManager.setupEventListeners();

      expect(mockOn).toHaveBeenCalledWith('submit', expect.any(Function));
    });
  });
});
