// Jest setup file
// Extends Jest with custom matchers from @testing-library/jest-dom
import '@testing-library/jest-dom';

// Mock WordPress globals
global.wp = {
  ajax: {
    send: jest.fn(),
    post: jest.fn()
  },
  i18n: {
    __: (text) => text,
    _x: (text) => text,
    _n: (single, plural, number) => (number === 1 ? single : plural),
    sprintf: (format, ...args) => {
      let i = 0;
      return format.replace(/%s/g, () => args[i++] || '');
    }
  }
};

// Mock jQuery
global.jQuery = global.$ = jest.fn(() => ({
  ready: jest.fn((fn) => fn()),
  on: jest.fn(),
  off: jest.fn(),
  trigger: jest.fn(),
  find: jest.fn(() => global.jQuery()),
  addClass: jest.fn(() => global.jQuery()),
  removeClass: jest.fn(() => global.jQuery()),
  toggleClass: jest.fn(() => global.jQuery()),
  hasClass: jest.fn(() => false),
  attr: jest.fn(),
  prop: jest.fn(),
  val: jest.fn(),
  text: jest.fn(),
  html: jest.fn(),
  append: jest.fn(),
  prepend: jest.fn(),
  remove: jest.fn(),
  show: jest.fn(),
  hide: jest.fn(),
  fadeIn: jest.fn(),
  fadeOut: jest.fn(),
  ajax: jest.fn()
}));

global.jQuery.ajax = jest.fn();
global.jQuery.post = jest.fn();
global.jQuery.get = jest.fn();

// Mock formflow_pro object (WordPress localized script data)
global.formflow_pro = {
  ajax_url: '/wp-admin/admin-ajax.php',
  nonce: 'test-nonce',
  i18n: {
    error: 'Error',
    success: 'Success',
    loading: 'Loading...'
  }
};

// Mock console methods for cleaner test output (optional)
// Uncomment if you want to suppress console output during tests
// global.console = {
//   ...console,
//   log: jest.fn(),
//   warn: jest.fn(),
//   error: jest.fn()
// };
