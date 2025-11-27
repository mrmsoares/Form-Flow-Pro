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

// Create a chainable jQuery mock
const createJQueryChain = () => {
  const chain = {
    ready: jest.fn((fn) => { fn(); return chain; }),
    on: jest.fn(() => chain),
    off: jest.fn(() => chain),
    trigger: jest.fn(() => chain),
    find: jest.fn(() => chain),
    closest: jest.fn(() => chain),
    parent: jest.fn(() => chain),
    parents: jest.fn(() => chain),
    children: jest.fn(() => chain),
    siblings: jest.fn(() => chain),
    first: jest.fn(() => chain),
    last: jest.fn(() => chain),
    eq: jest.fn(() => chain),
    filter: jest.fn(() => chain),
    not: jest.fn(() => chain),
    addClass: jest.fn(() => chain),
    removeClass: jest.fn(() => chain),
    toggleClass: jest.fn(() => chain),
    hasClass: jest.fn(() => false),
    attr: jest.fn((key, value) => value !== undefined ? chain : ''),
    removeAttr: jest.fn(() => chain),
    prop: jest.fn((key, value) => value !== undefined ? chain : false),
    data: jest.fn((key, value) => value !== undefined ? chain : null),
    val: jest.fn((value) => value !== undefined ? chain : ''),
    text: jest.fn((value) => value !== undefined ? chain : ''),
    html: jest.fn((value) => value !== undefined ? chain : ''),
    append: jest.fn(() => chain),
    appendTo: jest.fn(() => chain),
    prepend: jest.fn(() => chain),
    prependTo: jest.fn(() => chain),
    before: jest.fn(() => chain),
    after: jest.fn(() => chain),
    insertBefore: jest.fn(() => chain),
    insertAfter: jest.fn(() => chain),
    wrap: jest.fn(() => chain),
    unwrap: jest.fn(() => chain),
    remove: jest.fn(() => chain),
    detach: jest.fn(() => chain),
    empty: jest.fn(() => chain),
    clone: jest.fn(() => chain),
    replaceWith: jest.fn(() => chain),
    show: jest.fn(() => chain),
    hide: jest.fn(() => chain),
    toggle: jest.fn(() => chain),
    fadeIn: jest.fn((duration, callback) => { if (callback) callback(); return chain; }),
    fadeOut: jest.fn((duration, callback) => { if (callback) callback(); return chain; }),
    fadeToggle: jest.fn(() => chain),
    fadeTo: jest.fn(() => chain),
    slideUp: jest.fn((duration, callback) => { if (callback) callback(); return chain; }),
    slideDown: jest.fn((duration, callback) => { if (callback) callback(); return chain; }),
    slideToggle: jest.fn(() => chain),
    animate: jest.fn(() => chain),
    stop: jest.fn(() => chain),
    css: jest.fn((key, value) => value !== undefined ? chain : ''),
    width: jest.fn((value) => value !== undefined ? chain : 0),
    height: jest.fn((value) => value !== undefined ? chain : 0),
    innerWidth: jest.fn(() => 0),
    innerHeight: jest.fn(() => 0),
    outerWidth: jest.fn(() => 0),
    outerHeight: jest.fn(() => 0),
    offset: jest.fn(() => ({ top: 0, left: 0 })),
    position: jest.fn(() => ({ top: 0, left: 0 })),
    scrollTop: jest.fn((value) => value !== undefined ? chain : 0),
    scrollLeft: jest.fn((value) => value !== undefined ? chain : 0),
    each: jest.fn((callback) => { callback.call(chain, 0, chain); return chain; }),
    map: jest.fn(() => chain),
    get: jest.fn((index) => index !== undefined ? null : []),
    index: jest.fn(() => 0),
    is: jest.fn(() => false),
    length: 1,
    [0]: document.createElement('div'),
    toArray: jest.fn(() => []),
    serialize: jest.fn(() => ''),
    serializeArray: jest.fn(() => []),
    submit: jest.fn(() => chain),
    click: jest.fn(() => chain),
    focus: jest.fn(() => chain),
    blur: jest.fn(() => chain),
    change: jest.fn(() => chain),
    select: jest.fn(() => chain),
    keydown: jest.fn(() => chain),
    keyup: jest.fn(() => chain),
    keypress: jest.fn(() => chain),
    mousedown: jest.fn(() => chain),
    mouseup: jest.fn(() => chain),
    mouseover: jest.fn(() => chain),
    mouseout: jest.fn(() => chain),
    mouseenter: jest.fn(() => chain),
    mouseleave: jest.fn(() => chain),
    hover: jest.fn(() => chain),
    bind: jest.fn(() => chain),
    unbind: jest.fn(() => chain),
    delegate: jest.fn(() => chain),
    undelegate: jest.fn(() => chain),
    live: jest.fn(() => chain),
    die: jest.fn(() => chain),
    one: jest.fn(() => chain),
    promise: jest.fn(() => Promise.resolve(chain)),
    then: jest.fn((callback) => { callback(chain); return chain; }),
    done: jest.fn((callback) => { callback(chain); return chain; }),
    fail: jest.fn(() => chain),
    always: jest.fn((callback) => { callback(chain); return chain; }),
  };
  return chain;
};

// Main jQuery function mock
const jQueryMock = jest.fn((selector) => {
  if (typeof selector === 'function') {
    // $(document).ready() or $(function)
    selector();
    return createJQueryChain();
  }
  return createJQueryChain();
});

// Static jQuery methods
jQueryMock.ajax = jest.fn((options) => {
  const deferred = {
    done: jest.fn((callback) => { deferred._done = callback; return deferred; }),
    fail: jest.fn((callback) => { deferred._fail = callback; return deferred; }),
    always: jest.fn((callback) => { deferred._always = callback; return deferred; }),
    then: jest.fn((success, error) => {
      deferred._done = success;
      deferred._fail = error;
      return deferred;
    }),
  };
  // Auto-resolve with success by default
  setTimeout(() => {
    if (deferred._done) deferred._done({});
    if (deferred._always) deferred._always();
  }, 0);
  return deferred;
});
jQueryMock.post = jest.fn((url, data, callback) => {
  if (callback) setTimeout(() => callback({}), 0);
  return jQueryMock.ajax({ url, data, method: 'POST' });
});
jQueryMock.get = jest.fn((url, data, callback) => {
  if (callback) setTimeout(() => callback({}), 0);
  return jQueryMock.ajax({ url, data, method: 'GET' });
});
jQueryMock.getJSON = jest.fn((url, data, callback) => {
  if (callback) setTimeout(() => callback({}), 0);
  return jQueryMock.ajax({ url, data, dataType: 'json' });
});
jQueryMock.fn = {
  extend: jest.fn(),
  init: jest.fn(),
};
jQueryMock.extend = jest.fn((target, ...sources) => Object.assign(target || {}, ...sources));
jQueryMock.each = jest.fn((obj, callback) => {
  if (Array.isArray(obj)) {
    obj.forEach((item, index) => callback(index, item));
  } else {
    Object.keys(obj).forEach(key => callback(key, obj[key]));
  }
  return obj;
});
jQueryMock.map = jest.fn((arr, callback) => arr.map(callback));
jQueryMock.grep = jest.fn((arr, callback) => arr.filter(callback));
jQueryMock.inArray = jest.fn((value, arr) => arr.indexOf(value));
jQueryMock.isArray = jest.fn((obj) => Array.isArray(obj));
jQueryMock.isFunction = jest.fn((obj) => typeof obj === 'function');
jQueryMock.isPlainObject = jest.fn((obj) => obj !== null && typeof obj === 'object' && !Array.isArray(obj));
jQueryMock.isEmptyObject = jest.fn((obj) => Object.keys(obj).length === 0);
jQueryMock.trim = jest.fn((str) => str ? str.trim() : '');
jQueryMock.parseJSON = jest.fn((str) => JSON.parse(str));
jQueryMock.param = jest.fn((obj) => new URLSearchParams(obj).toString());
jQueryMock.noop = jest.fn();
jQueryMock.when = jest.fn((...deferreds) => Promise.all(deferreds));
jQueryMock.Deferred = jest.fn(() => {
  let resolveCallback, rejectCallback;
  const deferred = {
    resolve: jest.fn((...args) => { if (resolveCallback) resolveCallback(...args); }),
    reject: jest.fn((...args) => { if (rejectCallback) rejectCallback(...args); }),
    done: jest.fn((callback) => { resolveCallback = callback; return deferred; }),
    fail: jest.fn((callback) => { rejectCallback = callback; return deferred; }),
    always: jest.fn((callback) => { return deferred; }),
    promise: jest.fn(() => deferred),
  };
  return deferred;
});
jQueryMock.proxy = jest.fn((fn, context) => fn.bind(context));
jQueryMock.contains = jest.fn((container, contained) => container.contains(contained));
jQueryMock.data = jest.fn(() => ({}));
jQueryMock.removeData = jest.fn();
jQueryMock.hasData = jest.fn(() => false);
jQueryMock.expr = { ':': {} };
jQueryMock.support = {};
jQueryMock.event = { special: {} };

global.jQuery = jQueryMock;
global.$ = jQueryMock;

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

// Mock formflowData (alternative WordPress localization)
global.formflowData = {
  ajaxUrl: '/wp-admin/admin-ajax.php',
  nonce: 'test-nonce',
  restUrl: '/wp-json/formflow/v1/',
  i18n: {
    error: 'Error',
    success: 'Success',
    loading: 'Loading...',
    confirmDelete: 'Are you sure?',
    saved: 'Saved successfully',
  }
};

// Mock ajaxurl (WordPress global)
global.ajaxurl = '/wp-admin/admin-ajax.php';

// Mock window.location
delete window.location;
window.location = {
  href: 'http://localhost/',
  origin: 'http://localhost',
  pathname: '/',
  search: '',
  hash: '',
  reload: jest.fn(),
  assign: jest.fn(),
  replace: jest.fn(),
};

// Mock window.matchMedia
window.matchMedia = jest.fn().mockImplementation((query) => ({
  matches: false,
  media: query,
  onchange: null,
  addListener: jest.fn(),
  removeListener: jest.fn(),
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  dispatchEvent: jest.fn(),
}));

// Mock IntersectionObserver
global.IntersectionObserver = class IntersectionObserver {
  constructor(callback) {
    this.callback = callback;
  }
  observe() { return null; }
  unobserve() { return null; }
  disconnect() { return null; }
};

// Mock ResizeObserver
global.ResizeObserver = class ResizeObserver {
  constructor(callback) {
    this.callback = callback;
  }
  observe() { return null; }
  unobserve() { return null; }
  disconnect() { return null; }
};

// Mock MutationObserver
global.MutationObserver = class MutationObserver {
  constructor(callback) {
    this.callback = callback;
  }
  observe() { return null; }
  disconnect() { return null; }
  takeRecords() { return []; }
};

// Mock requestAnimationFrame
global.requestAnimationFrame = jest.fn((callback) => setTimeout(callback, 0));
global.cancelAnimationFrame = jest.fn((id) => clearTimeout(id));

// Mock localStorage
const localStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// Mock sessionStorage
const sessionStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};
Object.defineProperty(window, 'sessionStorage', { value: sessionStorageMock });

// Mock clipboard API
Object.defineProperty(navigator, 'clipboard', {
  value: {
    writeText: jest.fn().mockResolvedValue(),
    readText: jest.fn().mockResolvedValue(''),
  },
});

// Mock console methods for cleaner test output (optional)
// Uncomment if you want to suppress console output during tests
// global.console = {
//   ...console,
//   log: jest.fn(),
//   warn: jest.fn(),
//   error: jest.fn()
// };
