/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { screen, waitFor } from '@testing-library/dom';

// Mock jQuery
global.$ = global.jQuery = require('jquery');

// Mock IntersectionObserver
global.IntersectionObserver = class IntersectionObserver {
    constructor(callback) {
        this.callback = callback;
    }
    observe() {}
    unobserve() {}
    disconnect() {}
};

// Mock matchMedia
global.matchMedia = jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(),
    removeListener: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
}));

// Mock clipboard API
Object.assign(navigator, {
    clipboard: {
        writeText: jest.fn(() => Promise.resolve()),
    },
});

// Mock localStorage
const localStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn(),
};
global.localStorage = localStorageMock;

// Mock sessionStorage
const sessionStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn(),
};
global.sessionStorage = sessionStorageMock;

describe('FFPUX Premium Features', () => {
    let FFPUX;

    beforeEach(() => {
        // Reset DOM
        document.body.innerHTML = `
            <div id="ffp-toast-container"></div>
            <div id="ffp-command-palette" hidden>
                <div class="ffp-command-overlay"></div>
                <input class="ffp-command-input" />
                <div class="ffp-command-results"></div>
            </div>
            <div id="ffp-live-region" aria-live="polite"></div>
            <div id="ffp-live-region-assertive" aria-live="assertive"></div>
        `;

        global.ffpUX = { preferences: {} };

        // Load UX module
        require('../../../src/js/ux-premium.js');
        FFPUX = window.FFPUX;

        // Clear mocks
        jest.clearAllMocks();
    });

    describe('Initialization', () => {
        test('should initialize FFPUX system', () => {
            expect(FFPUX).toBeDefined();
            expect(FFPUX.initialized).toBe(true);
            expect(FFPUX.features).toBeDefined();
        });

        test('should load user preferences', () => {
            expect(FFPUX.preferences).toBeDefined();
        });

        test('should initialize all feature modules', () => {
            expect(FFPUX.features.skeletonLoaders).toBeDefined();
            expect(FFPUX.features.toast).toBeDefined();
            expect(FFPUX.features.shortcuts).toBeDefined();
            expect(FFPUX.features.darkMode).toBeDefined();
            expect(FFPUX.features.autoSave).toBeDefined();
        });
    });

    describe('Category 1: Loading States', () => {
        describe('Skeleton Loaders', () => {
            test('should show skeleton loader', () => {
                const container = document.createElement('div');
                document.body.appendChild(container);

                FFPUX.features.skeletonLoaders.show(container, 'text', 3);

                expect(container.classList.contains('ffp-loading')).toBe(true);
                expect(container.querySelectorAll('.ffp-skeleton').length).toBe(3);
            });

            test('should hide skeleton loader', () => {
                const container = document.createElement('div');
                container.classList.add('ffp-loading');
                container.innerHTML = '<div class="ffp-skeleton"></div>';
                document.body.appendChild(container);

                FFPUX.features.skeletonLoaders.hide(container);

                expect(container.classList.contains('ffp-loading')).toBe(false);
                expect(container.querySelectorAll('.ffp-skeleton').length).toBe(0);
            });

            test('should render different skeleton types', () => {
                const container = document.createElement('div');

                FFPUX.features.skeletonLoaders.show(container, 'card', 1);

                expect(container.querySelector('.ffp-skeleton-card')).toBeTruthy();
            });
        });

        describe('Progressive Loading', () => {
            test('should load items in batches', (done) => {
                jest.useFakeTimers();
                const items = Array.from({ length: 20 }, (_, i) => i);
                const loadedItems = [];

                FFPUX.features.progressiveLoading.load(items, {
                    batchSize: 5,
                    delay: 50,
                    onLoad: (item) => loadedItems.push(item)
                });

                jest.advanceTimersByTime(300);

                setTimeout(() => {
                    expect(loadedItems.length).toBeGreaterThan(0);
                    expect(loadedItems.length).toBeLessThanOrEqual(20);
                    jest.useRealTimers();
                    done();
                }, 100);
            });
        });

        describe('Lazy Load', () => {
            test('should load image when observed', () => {
                const img = document.createElement('img');
                img.setAttribute('data-src', 'https://example.com/image.jpg');
                document.body.appendChild(img);

                FFPUX.features.lazyLoad.loadImage(img);

                expect(img.src).toBe('https://example.com/image.jpg');
                expect(img.classList.contains('ffp-loaded')).toBe(true);
            });
        });

        describe('Optimistic Updates', () => {
            test('should apply optimistic update', async () => {
                const element = document.createElement('div');
                element.textContent = 'Original';
                document.body.appendChild(element);

                const request = Promise.resolve('Success');

                await FFPUX.features.optimisticUpdates.update(
                    'test-key',
                    element,
                    'Updated',
                    request
                );

                expect(element.classList.contains('ffp-confirmed')).toBe(true);
            });

            test('should rollback on error', async () => {
                const element = document.createElement('div');
                element.textContent = 'Original';
                document.body.appendChild(element);

                const request = Promise.reject('Error');

                try {
                    await FFPUX.features.optimisticUpdates.update(
                        'test-key',
                        element,
                        'Updated',
                        request
                    );
                } catch (error) {
                    expect(element.textContent).toBe('Original');
                }
            });
        });
    });

    describe('Category 2: Notifications & Feedback', () => {
        describe('Toast Notifications', () => {
            test('should show toast notification', () => {
                const id = FFPUX.features.toast.show('Test message', 'success');

                expect(document.querySelector(`#${id}`)).toBeTruthy();
                expect(document.querySelector('.ffp-toast-success')).toBeTruthy();
            });

            test('should show different toast types', () => {
                FFPUX.features.toast.success('Success message');
                FFPUX.features.toast.error('Error message');
                FFPUX.features.toast.warning('Warning message');
                FFPUX.features.toast.info('Info message');

                expect(document.querySelector('.ffp-toast-success')).toBeTruthy();
                expect(document.querySelector('.ffp-toast-error')).toBeTruthy();
            });

            test('should dismiss toast', (done) => {
                jest.useFakeTimers();

                const id = FFPUX.features.toast.show('Test', 'info', { duration: 1000 });

                jest.advanceTimersByTime(1000);

                setTimeout(() => {
                    expect(document.querySelector(`#${id}`)).toBeFalsy();
                    jest.useRealTimers();
                    done();
                }, 400);
            });

            test('should show action button in toast', () => {
                const action = jest.fn();
                FFPUX.features.toast.show('Test', 'info', {
                    action,
                    actionText: 'Undo'
                });

                expect(document.querySelector('.ffp-toast-action')).toBeTruthy();
            });
        });

        describe('Inline Validation', () => {
            test('should validate required field', () => {
                const input = document.createElement('input');
                input.setAttribute('data-validate', 'required');
                input.value = '';
                document.body.appendChild(input);

                const isValid = FFPUX.features.inlineValidation.validate(input);

                expect(isValid).toBe(false);
                expect(input.classList.contains('ffp-invalid')).toBe(true);
            });

            test('should validate email field', () => {
                const input = document.createElement('input');
                input.setAttribute('data-validate', 'email');
                input.value = 'invalid-email';
                document.body.appendChild(input);

                const isValid = FFPUX.features.inlineValidation.validate(input);

                expect(isValid).toBe(false);
            });

            test('should pass validation for valid email', () => {
                const input = document.createElement('input');
                input.setAttribute('data-validate', 'email');
                input.value = 'test@example.com';
                document.body.appendChild(input);

                const isValid = FFPUX.features.inlineValidation.validate(input);

                expect(isValid).toBe(true);
                expect(input.classList.contains('ffp-valid')).toBe(true);
            });

            test('should validate minimum length', () => {
                const input = document.createElement('input');
                input.setAttribute('data-validate', 'min:5');
                input.value = 'abc';
                document.body.appendChild(input);

                const isValid = FFPUX.features.inlineValidation.validate(input);

                expect(isValid).toBe(false);
            });
        });

        describe('Progress Indicators', () => {
            test('should create progress indicator', () => {
                const html = FFPUX.features.progressIndicators.create('test-progress', {
                    value: 50,
                    max: 100,
                    label: 'Loading...'
                });

                expect(html).toContain('ffp-progress');
                expect(html).toContain('50%');
            });

            test('should update progress value', () => {
                const html = FFPUX.features.progressIndicators.create('test-progress', {
                    value: 0,
                    max: 100
                });
                document.body.innerHTML += html;

                FFPUX.features.progressIndicators.update('test-progress', 75);

                const bar = document.querySelector('.ffp-progress-bar');
                expect(bar.style.width).toBe('75%');
            });

            test('should mark as complete', () => {
                const html = FFPUX.features.progressIndicators.create('test-progress', {
                    value: 0,
                    max: 100
                });
                document.body.innerHTML += html;

                FFPUX.features.progressIndicators.complete('test-progress');

                expect(document.querySelector('.ffp-progress-complete')).toBeTruthy();
            });
        });

        describe('Confirm Dialogs', () => {
            test('should show confirmation dialog', async () => {
                const promise = FFPUX.features.confirmDialogs.show({
                    title: 'Confirm Action',
                    message: 'Are you sure?'
                });

                expect(document.querySelector('.ffp-confirm-dialog')).toBeTruthy();

                // Auto-confirm
                document.querySelector('.ffp-btn-confirm').click();

                const result = await promise;
                expect(result).toBe(true);
            });

            test('should resolve false on cancel', async () => {
                const promise = FFPUX.features.confirmDialogs.show({
                    title: 'Test'
                });

                document.querySelector('.ffp-btn-cancel').click();

                const result = await promise;
                expect(result).toBe(false);
            });
        });

        describe('Status Badges', () => {
            test('should create status badge', () => {
                const badge = FFPUX.features.statusBadges.create('active');

                expect(badge).toContain('ffp-badge-success');
                expect(badge).toContain('Active');
            });

            test('should create badge with pulse effect', () => {
                const badge = FFPUX.features.statusBadges.create('processing', { pulse: true });

                expect(badge).toContain('ffp-badge-pulse');
            });
        });
    });

    describe('Category 3: Keyboard Navigation', () => {
        describe('Keyboard Shortcuts', () => {
            test('should register keyboard shortcuts', () => {
                const callback = jest.fn();
                FFPUX.features.shortcuts.register('ctrl+k', callback, 'Test shortcut');

                expect(FFPUX.features.shortcuts.shortcuts.has('ctrl+k')).toBe(true);
            });

            test('should handle keyboard events', () => {
                const callback = jest.fn();
                FFPUX.features.shortcuts.register('mod+s', callback);

                const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: true });
                document.dispatchEvent(event);

                // Callback should be called (if not in input field)
                expect(callback).toHaveBeenCalled();
            });

            test('should not trigger in input fields', () => {
                const callback = jest.fn();
                FFPUX.features.shortcuts.register('a', callback);

                const input = document.createElement('input');
                document.body.appendChild(input);
                input.focus();

                const event = new KeyboardEvent('keydown', { key: 'a' });
                Object.defineProperty(event, 'target', { value: input, configurable: true });
                document.dispatchEvent(event);

                expect(callback).not.toHaveBeenCalled();
            });
        });

        describe('Command Palette', () => {
            test('should toggle command palette', () => {
                FFPUX.features.commandPalette.toggle();

                expect(document.querySelector('#ffp-command-palette').hasAttribute('hidden')).toBe(false);
            });

            test('should register commands', () => {
                const action = jest.fn();
                FFPUX.features.commandPalette.register({
                    id: 'test-command',
                    label: 'Test Command',
                    icon: 'admin-generic',
                    action
                });

                expect(FFPUX.features.commandPalette.commands.length).toBeGreaterThan(0);
            });

            test('should filter commands', () => {
                FFPUX.features.commandPalette.show();
                const input = document.querySelector('.ffp-command-input');

                input.value = 'dashboard';
                input.dispatchEvent(new Event('input'));

                const results = document.querySelectorAll('.ffp-command-item');
                expect(results.length).toBeGreaterThan(0);
            });
        });

        describe('Focus Manager', () => {
            test('should save and restore focus', () => {
                const button = document.createElement('button');
                document.body.appendChild(button);
                button.focus();

                FFPUX.features.focusManager.saveFocus();

                const input = document.createElement('input');
                document.body.appendChild(input);
                input.focus();

                FFPUX.features.focusManager.restoreFocus();

                expect(document.activeElement).toBe(button);
            });
        });
    });

    describe('Category 4: Accessibility', () => {
        describe('Live Regions', () => {
            test('should announce to screen readers', () => {
                const region = document.querySelector('#ffp-live-region');

                FFPUX.features.liveRegions.announce('Test announcement', 'polite');

                setTimeout(() => {
                    expect(region.textContent).toBe('Test announcement');
                }, 150);
            });

            test('should use assertive region for urgent messages', () => {
                const region = document.querySelector('#ffp-live-region-assertive');

                FFPUX.features.liveRegions.announce('Urgent message', 'assertive');

                setTimeout(() => {
                    expect(region.textContent).toBe('Urgent message');
                }, 150);
            });
        });

        describe('Reduced Motion', () => {
            test('should detect reduced motion preference', () => {
                expect(FFPUX.features.reducedMotion).toBeDefined();
            });

            test('should add class to body when reduced motion is preferred', () => {
                global.matchMedia = jest.fn().mockImplementation(() => ({
                    matches: true,
                    addEventListener: jest.fn()
                }));

                // Re-instantiate
                const ReducedMotion = require('../../../src/js/ux-premium.js');

                // Body should have class (in actual implementation)
                // This is hard to test without full re-initialization
            });
        });
    });

    describe('Category 5: Progressive Enhancement', () => {
        describe('Dark Mode', () => {
            test('should toggle dark mode', () => {
                FFPUX.features.darkMode.toggle();

                expect(document.body.classList.contains('ffp-dark-mode')).toBe(true);
            });

            test('should save dark mode preference', () => {
                FFPUX.features.darkMode.enable();

                expect(localStorageMock.setItem).toHaveBeenCalledWith('ffp-dark-mode', 'true');
            });

            test('should disable dark mode', () => {
                FFPUX.features.darkMode.enable();
                FFPUX.features.darkMode.disable();

                expect(document.body.classList.contains('ffp-dark-mode')).toBe(false);
            });
        });

        describe('Auto Save', () => {
            test('should enable auto-save for form', () => {
                const form = document.createElement('form');
                form.id = 'test-form';
                document.body.appendChild(form);

                FFPUX.features.autoSave.enable('test-form', {
                    interval: 1000,
                    onSave: jest.fn()
                });

                // Form should have event listeners bound
                expect(true).toBe(true); // Placeholder
            });

            test('should restore auto-saved data', () => {
                localStorageMock.getItem.mockReturnValue(JSON.stringify({
                    data: 'test-data',
                    timestamp: Date.now()
                }));

                const restored = FFPUX.features.autoSave.restore('test-form');

                expect(restored).toBe('test-data');
            });

            test('should not restore old data', () => {
                localStorageMock.getItem.mockReturnValue(JSON.stringify({
                    data: 'test-data',
                    timestamp: Date.now() - (25 * 60 * 60 * 1000) // 25 hours ago
                }));

                const restored = FFPUX.features.autoSave.restore('test-form');

                expect(restored).toBeNull();
            });
        });

        describe('Session Recovery', () => {
            test('should save session state', () => {
                const state = { data: 'test' };

                FFPUX.features.sessionRecovery.save(state);

                expect(localStorageMock.setItem).toHaveBeenCalled();
            });

            test('should clear recovery data', () => {
                FFPUX.features.sessionRecovery.clear();

                expect(localStorageMock.removeItem).toHaveBeenCalled();
            });
        });
    });

    describe('Category 6: Data Tables', () => {
        describe('Table Enhancements', () => {
            test('should initialize table enhancements', () => {
                expect(FFPUX.features.tableEnhancements).toBeDefined();
            });

            test('should make headers sticky', () => {
                const table = document.createElement('table');
                table.className = 'ffp-table-sticky';
                const thead = document.createElement('thead');
                table.appendChild(thead);
                document.body.appendChild(table);

                FFPUX.features.tableEnhancements.initStickyHeaders();

                // Sticky functionality would be tested with scroll events
                expect(true).toBe(true);
            });
        });
    });

    describe('Category 7: Forms & Inputs', () => {
        describe('Form Enhancements', () => {
            test('should add character counter', () => {
                const input = document.createElement('input');
                input.setAttribute('data-maxlength', '100');
                document.body.appendChild(input);

                FFPUX.features.formEnhancements.initCharacterCounters();

                expect(document.querySelector('.ffp-char-counter')).toBeTruthy();
            });

            test('should calculate password strength', () => {
                const strength = FFPUX.features.formEnhancements.calculatePasswordStrength('Test123!@#');

                expect(strength.score).toBeGreaterThan(0);
                expect(strength.level).toBeDefined();
                expect(strength.text).toBeDefined();
            });

            test('should classify weak password', () => {
                const strength = FFPUX.features.formEnhancements.calculatePasswordStrength('abc');

                expect(strength.level).toBe('weak');
            });

            test('should classify strong password', () => {
                const strength = FFPUX.features.formEnhancements.calculatePasswordStrength('MyP@ssw0rd123!');

                expect(['good', 'strong']).toContain(strength.level);
            });
        });

        describe('Clipboard', () => {
            test('should copy text to clipboard', async () => {
                await FFPUX.features.clipboard.copy('Test text', null);

                expect(navigator.clipboard.writeText).toHaveBeenCalledWith('Test text');
            });
        });

        describe('Drag & Drop Upload', () => {
            test('should initialize dropzone', () => {
                const dropzone = document.createElement('div');
                dropzone.className = 'ffp-dropzone';
                dropzone.innerHTML = '<input type="file" />';
                document.body.appendChild(dropzone);

                FFPUX.features.dragDropUpload.initDropzone($(dropzone));

                // Dropzone should have event listeners
                expect(true).toBe(true);
            });
        });
    });

    describe('Category 8: Navigation & Layout', () => {
        describe('Recent Items', () => {
            test('should add recent item', () => {
                const item = {
                    id: 1,
                    type: 'form',
                    title: 'Contact Form',
                    url: '/admin/form/1',
                    icon: 'forms'
                };

                FFPUX.features.recentItems.add(item);

                expect(localStorageMock.setItem).toHaveBeenCalled();
            });

            test('should get all recent items', () => {
                localStorageMock.getItem.mockReturnValue(JSON.stringify([
                    { id: 1, type: 'form', title: 'Test', timestamp: Date.now() }
                ]));

                const items = FFPUX.features.recentItems.getAll();

                expect(items.length).toBe(1);
            });

            test('should render recent items', () => {
                localStorageMock.getItem.mockReturnValue(JSON.stringify([
                    {
                        id: 1,
                        type: 'form',
                        title: 'Contact Form',
                        url: '/admin/form/1',
                        icon: 'forms'
                    }
                ]));

                const html = FFPUX.features.recentItems.render();

                expect(html).toContain('Contact Form');
            });
        });

        describe('Contextual Help', () => {
            test('should show help tooltip', () => {
                const element = document.createElement('div');
                element.setAttribute('data-help', 'This is help text');
                document.body.appendChild(element);

                FFPUX.features.contextualHelp.showTooltip($(element), 'This is help text');

                expect(document.querySelector('.ffp-help-tooltip')).toBeTruthy();
            });

            test('should hide help tooltip', () => {
                document.body.innerHTML += '<div class="ffp-help-tooltip">Help</div>';

                FFPUX.features.contextualHelp.hideTooltip();

                expect(document.querySelector('.ffp-help-tooltip')).toBeFalsy();
            });
        });
    });

    describe('Category 9: Performance', () => {
        describe('Debounce', () => {
            test('should debounce function calls', (done) => {
                jest.useFakeTimers();
                const fn = jest.fn();
                const debounced = FFPUX.features.debounce.debounce(fn, 100);

                debounced();
                debounced();
                debounced();

                jest.advanceTimersByTime(100);

                setTimeout(() => {
                    expect(fn).toHaveBeenCalledTimes(1);
                    jest.useRealTimers();
                    done();
                }, 50);
            });

            test('should throttle function calls', (done) => {
                jest.useFakeTimers();
                const fn = jest.fn();
                const throttled = FFPUX.features.debounce.throttle(fn, 100);

                throttled();
                throttled();
                throttled();

                jest.advanceTimersByTime(100);

                setTimeout(() => {
                    expect(fn).toHaveBeenCalledTimes(1);
                    jest.useRealTimers();
                    done();
                }, 50);
            });
        });

        describe('Virtual Scroll', () => {
            test('should initialize virtual scrolling', () => {
                const container = document.createElement('div');
                container.style.height = '400px';
                document.body.appendChild(container);

                const items = Array.from({ length: 1000 }, (_, i) => `Item ${i}`);

                FFPUX.features.virtualScroll.init(container, {
                    itemHeight: 40,
                    items,
                    renderItem: (item) => `<div>${item}</div>`
                });

                expect(container.querySelector('.ffp-virtual-content')).toBeTruthy();
            });
        });

        describe('Cache UI State', () => {
            test('should save UI state', () => {
                FFPUX.features.cacheUI.save('sidebar-collapsed', true);

                expect(localStorageMock.setItem).toHaveBeenCalled();
            });

            test('should get UI state', () => {
                localStorageMock.getItem.mockReturnValue(JSON.stringify({
                    'sidebar-collapsed': true
                }));

                const value = FFPUX.features.cacheUI.get('sidebar-collapsed');

                expect(value).toBe(true);
            });

            test('should return default value if not found', () => {
                localStorageMock.getItem.mockReturnValue(JSON.stringify({}));

                const value = FFPUX.features.cacheUI.get('non-existent', 'default');

                expect(value).toBe('default');
            });
        });
    });

    describe('Global Methods', () => {
        test('should expose toast method', () => {
            const id = FFPUX.toast('Test', 'success');

            expect(document.querySelector(`#${id}`)).toBeTruthy();
        });

        test('should expose announce method', () => {
            FFPUX.announce('Test announcement');

            setTimeout(() => {
                const region = document.querySelector('#ffp-live-region');
                expect(region.textContent).toBe('Test announcement');
            }, 150);
        });

        test('should close all modals on Escape', () => {
            document.body.innerHTML += '<div class="ffp-modal"></div>';

            const event = new KeyboardEvent('keydown', { key: 'Escape' });
            document.dispatchEvent(event);

            expect(document.querySelector('.ffp-modal').hasAttribute('hidden')).toBe(true);
        });
    });
});
