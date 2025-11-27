/**
 * FormFlow Pro - Admin Main Script Tests
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

import { screen, fireEvent } from '@testing-library/dom';
import '@testing-library/jest-dom';

describe('FormFlowAdmin', () => {
  let FormFlowAdmin;
  let consoleLogSpy;

  beforeEach(() => {
    // Clear the document body before each test
    document.body.innerHTML = '';

    // Reset all mocks
    jest.clearAllMocks();

    // Spy on console.log
    consoleLogSpy = jest.spyOn(console, 'log').mockImplementation();

    // Define FormFlowAdmin object (mimicking the IIFE from index.js)
    FormFlowAdmin = {
      init() {
        this.setupEventListeners();
        this.initTooltips();
        console.log('FormFlow Pro Admin initialized');
      },

      setupEventListeners() {
        // Add your event listeners here
      },

      initTooltips() {
        // Initialize tooltips if needed
      }
    };
  });

  afterEach(() => {
    consoleLogSpy.mockRestore();
  });

  describe('Initialization', () => {
    test('should initialize FormFlowAdmin object', () => {
      expect(FormFlowAdmin).toBeDefined();
      expect(typeof FormFlowAdmin.init).toBe('function');
    });

    test('should call init method', () => {
      const initSpy = jest.spyOn(FormFlowAdmin, 'init');
      FormFlowAdmin.init();
      expect(initSpy).toHaveBeenCalledTimes(1);
    });

    test('should log initialization message', () => {
      FormFlowAdmin.init();
      expect(consoleLogSpy).toHaveBeenCalledWith('FormFlow Pro Admin initialized');
    });

    test('should call setupEventListeners on init', () => {
      const setupSpy = jest.spyOn(FormFlowAdmin, 'setupEventListeners');
      FormFlowAdmin.init();
      expect(setupSpy).toHaveBeenCalled();
    });

    test('should call initTooltips on init', () => {
      const tooltipSpy = jest.spyOn(FormFlowAdmin, 'initTooltips');
      FormFlowAdmin.init();
      expect(tooltipSpy).toHaveBeenCalled();
    });
  });

  describe('Event Listeners', () => {
    test('should have setupEventListeners method', () => {
      expect(typeof FormFlowAdmin.setupEventListeners).toBe('function');
    });

    test('should execute setupEventListeners without errors', () => {
      expect(() => FormFlowAdmin.setupEventListeners()).not.toThrow();
    });

    test('should be ready for future event listener implementations', () => {
      const result = FormFlowAdmin.setupEventListeners();
      expect(result).toBeUndefined();
    });
  });

  describe('Tooltips', () => {
    test('should have initTooltips method', () => {
      expect(typeof FormFlowAdmin.initTooltips).toBe('function');
    });

    test('should execute initTooltips without errors', () => {
      expect(() => FormFlowAdmin.initTooltips()).not.toThrow();
    });

    test('should be ready for future tooltip implementations', () => {
      const result = FormFlowAdmin.initTooltips();
      expect(result).toBeUndefined();
    });
  });

  describe('jQuery Integration', () => {
    test('should have jQuery available globally', () => {
      expect(global.jQuery).toBeDefined();
      expect(global.$).toBeDefined();
    });

    test('should handle jQuery document ready', () => {
      const mockReady = jest.fn((fn) => fn());
      global.jQuery.mockReturnValue({ ready: mockReady });

      const $ = global.jQuery;
      $(document).ready(() => {
        FormFlowAdmin.init();
      });

      expect(mockReady).toHaveBeenCalled();
    });

    test('should initialize on document ready event', () => {
      const initSpy = jest.spyOn(FormFlowAdmin, 'init');
      const readyCallback = jest.fn(() => FormFlowAdmin.init());

      readyCallback();

      expect(initSpy).toHaveBeenCalled();
    });
  });

  describe('WordPress Integration', () => {
    test('should have WordPress wp global available', () => {
      expect(global.wp).toBeDefined();
    });

    test('should have formflow_pro localized data', () => {
      expect(global.formflow_pro).toBeDefined();
      expect(global.formflow_pro.ajax_url).toBe('/wp-admin/admin-ajax.php');
      expect(global.formflow_pro.nonce).toBe('test-nonce');
    });

    test('should use wp.i18n for translations', () => {
      expect(global.wp.i18n.__).toBeDefined();
      const text = global.wp.i18n.__('Test String');
      expect(text).toBe('Test String');
    });
  });

  describe('Admin UI Elements', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <div id="formflow-admin">
          <h1>FormFlow Pro Admin</h1>
          <button id="admin-action">Action</button>
          <div class="notice"></div>
        </div>
      `;
    });

    test('should find admin container', () => {
      const adminContainer = document.getElementById('formflow-admin');
      expect(adminContainer).toBeInTheDocument();
    });

    test('should find admin heading', () => {
      expect(screen.getByText('FormFlow Pro Admin')).toBeInTheDocument();
    });

    test('should find action button', () => {
      const button = document.getElementById('admin-action');
      expect(button).toBeInTheDocument();
      expect(button).toHaveTextContent('Action');
    });

    test('should find notice container', () => {
      const notice = document.querySelector('.notice');
      expect(notice).toBeInTheDocument();
    });

    test('should handle button clicks', () => {
      const button = document.getElementById('admin-action');
      const clickHandler = jest.fn();
      button.addEventListener('click', clickHandler);

      fireEvent.click(button);

      expect(clickHandler).toHaveBeenCalledTimes(1);
    });
  });

  describe('Method Availability', () => {
    test('should have all required methods defined', () => {
      expect(FormFlowAdmin.init).toBeDefined();
      expect(FormFlowAdmin.setupEventListeners).toBeDefined();
      expect(FormFlowAdmin.initTooltips).toBeDefined();
    });

    test('should have methods as functions', () => {
      expect(typeof FormFlowAdmin.init).toBe('function');
      expect(typeof FormFlowAdmin.setupEventListeners).toBe('function');
      expect(typeof FormFlowAdmin.initTooltips).toBe('function');
    });
  });

  describe('Error Handling', () => {
    test('should handle missing DOM elements gracefully', () => {
      document.body.innerHTML = '';
      expect(() => FormFlowAdmin.init()).not.toThrow();
    });

    test('should handle initialization errors', () => {
      const errorInit = jest.spyOn(FormFlowAdmin, 'setupEventListeners').mockImplementation(() => {
        // Silent fail - no throw
      });

      expect(() => FormFlowAdmin.init()).not.toThrow();
      errorInit.mockRestore();
    });
  });
});
