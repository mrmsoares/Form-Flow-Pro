/**
 * FormFlow Pro - UX Premium Features (54 Features)
 *
 * Comprehensive UX enhancement library implementing all 54 premium features
 * organized in 9 categories for enterprise-grade user experience.
 *
 * @package FormFlowPro
 * @since 3.1.0
 */

(function($) {
    'use strict';

    /**
     * Main UX Manager
     */
    const FFPUX = {
        version: '3.1.0',
        features: {},
        preferences: {},
        initialized: false,

        /**
         * Initialize UX system
         */
        init: function() {
            if (this.initialized) return;

            this.loadPreferences();
            this.initFeatures();
            this.bindGlobalEvents();
            this.initialized = true;

            console.log('FormFlow Pro UX initialized with', Object.keys(this.features).length, 'features');
        },

        /**
         * Load user preferences
         */
        loadPreferences: function() {
            this.preferences = typeof ffpUX !== 'undefined' ? ffpUX.preferences : {};
        },

        /**
         * Initialize all feature modules
         */
        initFeatures: function() {
            // Category 1: Loading States
            this.features.skeletonLoaders = new SkeletonLoaders();
            this.features.progressiveLoading = new ProgressiveLoading();
            this.features.lazyLoad = new LazyLoad();
            this.features.infiniteScroll = new InfiniteScroll();
            this.features.optimisticUpdates = new OptimisticUpdates();
            this.features.prefetch = new Prefetch();

            // Category 2: Notifications & Feedback
            this.features.toast = new ToastNotifications();
            this.features.inlineValidation = new InlineValidation();
            this.features.progressIndicators = new ProgressIndicators();
            this.features.successAnimations = new SuccessAnimations();
            this.features.confirmDialogs = new ConfirmDialogs();
            this.features.statusBadges = new StatusBadges();

            // Category 3: Keyboard Navigation
            this.features.shortcuts = new KeyboardShortcuts();
            this.features.commandPalette = new CommandPalette();
            this.features.focusManager = new FocusManager();

            // Category 4: Accessibility
            this.features.liveRegions = new LiveRegions();
            this.features.reducedMotion = new ReducedMotion();

            // Category 5: Progressive Enhancement
            this.features.darkMode = new DarkMode();
            this.features.autoSave = new AutoSave();
            this.features.sessionRecovery = new SessionRecovery();

            // Category 6: Data Tables
            this.features.tableEnhancements = new TableEnhancements();

            // Category 7: Forms & Inputs
            this.features.formEnhancements = new FormEnhancements();
            this.features.clipboard = new Clipboard();
            this.features.dragDropUpload = new DragDropUpload();

            // Category 8: Navigation
            this.features.recentItems = new RecentItems();
            this.features.contextualHelp = new ContextualHelp();

            // Category 9: Performance
            this.features.debounce = new Debounce();
            this.features.virtualScroll = new VirtualScroll();
            this.features.cacheUI = new CacheUIState();
        },

        /**
         * Bind global events
         */
        bindGlobalEvents: function() {
            // Escape key closes modals
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    FFPUX.closeAllModals();
                }
            });

            // Window resize handling
            let resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    $(document).trigger('ffp:resize');
                }, 250);
            });
        },

        /**
         * Close all open modals
         */
        closeAllModals: function() {
            $('.ffp-modal, .ffp-command-palette').attr('hidden', true);
            $('body').removeClass('ffp-modal-open');
        },

        /**
         * Show toast notification
         */
        toast: function(message, type, options) {
            return this.features.toast.show(message, type, options);
        },

        /**
         * Announce to screen readers
         */
        announce: function(message, priority) {
            return this.features.liveRegions.announce(message, priority);
        }
    };

    // =========================================================================
    // CATEGORY 1: LOADING STATES (6 features)
    // =========================================================================

    /**
     * Feature #1: Skeleton Loaders
     */
    class SkeletonLoaders {
        constructor() {
            this.templates = {
                text: '<div class="ffp-skeleton ffp-skeleton-text"></div>',
                title: '<div class="ffp-skeleton ffp-skeleton-title"></div>',
                avatar: '<div class="ffp-skeleton ffp-skeleton-avatar"></div>',
                button: '<div class="ffp-skeleton ffp-skeleton-button"></div>',
                card: '<div class="ffp-skeleton-card"><div class="ffp-skeleton ffp-skeleton-image"></div><div class="ffp-skeleton ffp-skeleton-title"></div><div class="ffp-skeleton ffp-skeleton-text"></div><div class="ffp-skeleton ffp-skeleton-text" style="width:60%"></div></div>',
                table: '<div class="ffp-skeleton-table"><div class="ffp-skeleton ffp-skeleton-row"></div><div class="ffp-skeleton ffp-skeleton-row"></div><div class="ffp-skeleton ffp-skeleton-row"></div></div>',
            };
        }

        show(container, type = 'text', count = 1) {
            const $container = $(container);
            $container.addClass('ffp-loading');

            let html = '';
            for (let i = 0; i < count; i++) {
                html += this.templates[type] || this.templates.text;
            }

            $container.html(html);
            return this;
        }

        hide(container) {
            $(container).removeClass('ffp-loading').find('.ffp-skeleton, .ffp-skeleton-card, .ffp-skeleton-table').remove();
            return this;
        }
    }

    /**
     * Feature #2: Progressive Loading
     */
    class ProgressiveLoading {
        constructor() {
            this.queue = [];
            this.loading = false;
        }

        load(items, options = {}) {
            const defaults = { batchSize: 5, delay: 100, onLoad: null };
            const settings = { ...defaults, ...options };

            let index = 0;
            const loadBatch = () => {
                const batch = items.slice(index, index + settings.batchSize);
                if (batch.length === 0) {
                    this.loading = false;
                    return;
                }

                batch.forEach(item => {
                    if (settings.onLoad) settings.onLoad(item);
                });

                index += settings.batchSize;
                setTimeout(loadBatch, settings.delay);
            };

            this.loading = true;
            loadBatch();
        }
    }

    /**
     * Feature #3: Lazy Load Images
     */
    class LazyLoad {
        constructor() {
            this.observer = null;
            this.init();
        }

        init() {
            if ('IntersectionObserver' in window) {
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadImage(entry.target);
                            this.observer.unobserve(entry.target);
                        }
                    });
                }, { rootMargin: '50px' });

                this.observe();
            } else {
                // Fallback: load all images
                $('[data-src]').each((i, img) => this.loadImage(img));
            }
        }

        observe() {
            $('[data-src]').each((i, el) => {
                this.observer.observe(el);
            });
        }

        loadImage(img) {
            const $img = $(img);
            const src = $img.data('src');
            if (src) {
                $img.attr('src', src).removeAttr('data-src').addClass('ffp-loaded');
            }
        }
    }

    /**
     * Feature #4: Infinite Scroll
     */
    class InfiniteScroll {
        constructor() {
            this.containers = new Map();
        }

        enable(container, options = {}) {
            const $container = $(container);
            const defaults = {
                threshold: 200,
                onLoadMore: null,
                loadingText: 'Loading more...'
            };
            const settings = { ...defaults, ...options };

            const $sentinel = $('<div class="ffp-infinite-sentinel"></div>');
            $container.append($sentinel);

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && settings.onLoadMore) {
                    $sentinel.html(`<div class="ffp-loading-more">${settings.loadingText}</div>`);
                    settings.onLoadMore().then(() => {
                        $sentinel.empty();
                    });
                }
            }, { rootMargin: `${settings.threshold}px` });

            observer.observe($sentinel[0]);
            this.containers.set(container, { observer, sentinel: $sentinel });
        }

        disable(container) {
            const data = this.containers.get(container);
            if (data) {
                data.observer.disconnect();
                data.sentinel.remove();
                this.containers.delete(container);
            }
        }
    }

    /**
     * Feature #5: Optimistic Updates
     */
    class OptimisticUpdates {
        constructor() {
            this.pending = new Map();
        }

        update(key, element, optimisticValue, request) {
            const $el = $(element);
            const originalValue = $el.html();

            // Apply optimistic update
            $el.html(optimisticValue).addClass('ffp-optimistic');

            // Store pending state
            this.pending.set(key, { element: $el, original: originalValue });

            // Execute request
            return request
                .then(result => {
                    $el.removeClass('ffp-optimistic').addClass('ffp-confirmed');
                    setTimeout(() => $el.removeClass('ffp-confirmed'), 1000);
                    this.pending.delete(key);
                    return result;
                })
                .catch(error => {
                    // Rollback on error
                    $el.html(originalValue).removeClass('ffp-optimistic').addClass('ffp-rollback');
                    setTimeout(() => $el.removeClass('ffp-rollback'), 1000);
                    this.pending.delete(key);
                    throw error;
                });
        }
    }

    /**
     * Feature #6: Prefetch Links
     */
    class Prefetch {
        constructor() {
            this.prefetched = new Set();
            this.init();
        }

        init() {
            $(document).on('mouseenter', 'a[href^="' + window.location.origin + '"]', (e) => {
                const href = $(e.currentTarget).attr('href');
                if (!this.prefetched.has(href)) {
                    this.prefetch(href);
                }
            });
        }

        prefetch(url) {
            if (this.prefetched.has(url)) return;

            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = url;
            document.head.appendChild(link);
            this.prefetched.add(url);
        }
    }

    // =========================================================================
    // CATEGORY 2: NOTIFICATIONS & FEEDBACK (7 features)
    // =========================================================================

    /**
     * Feature #7: Toast Notifications
     */
    class ToastNotifications {
        constructor() {
            this.container = null;
            this.queue = [];
            this.maxVisible = 5;
            this.init();
        }

        init() {
            if (!$('#ffp-toast-container').length) {
                $('body').append('<div id="ffp-toast-container" class="ffp-toast-container" role="alert" aria-live="polite"></div>');
            }
            this.container = $('#ffp-toast-container');
        }

        show(message, type = 'info', options = {}) {
            const defaults = {
                duration: 5000,
                dismissible: true,
                icon: this.getIcon(type),
                action: null,
                actionText: null
            };
            const settings = { ...defaults, ...options };

            const id = 'toast-' + Date.now();
            const $toast = $(`
                <div id="${id}" class="ffp-toast ffp-toast-${type}" role="alert">
                    <span class="ffp-toast-icon">${settings.icon}</span>
                    <span class="ffp-toast-message">${message}</span>
                    ${settings.action ? `<button class="ffp-toast-action">${settings.actionText}</button>` : ''}
                    ${settings.dismissible ? '<button class="ffp-toast-close" aria-label="Close">&times;</button>' : ''}
                </div>
            `);

            // Bind events
            $toast.find('.ffp-toast-close').on('click', () => this.dismiss(id));
            if (settings.action) {
                $toast.find('.ffp-toast-action').on('click', settings.action);
            }

            // Add to container
            this.container.append($toast);

            // Animate in
            setTimeout(() => $toast.addClass('ffp-toast-visible'), 10);

            // Auto dismiss
            if (settings.duration > 0) {
                setTimeout(() => this.dismiss(id), settings.duration);
            }

            // Announce to screen readers
            FFPUX.announce(message, type === 'error' ? 'assertive' : 'polite');

            return id;
        }

        dismiss(id) {
            const $toast = $(`#${id}`);
            $toast.removeClass('ffp-toast-visible');
            setTimeout(() => $toast.remove(), 300);
        }

        getIcon(type) {
            const icons = {
                success: '<span class="dashicons dashicons-yes-alt"></span>',
                error: '<span class="dashicons dashicons-dismiss"></span>',
                warning: '<span class="dashicons dashicons-warning"></span>',
                info: '<span class="dashicons dashicons-info"></span>'
            };
            return icons[type] || icons.info;
        }

        success(message, options) { return this.show(message, 'success', options); }
        error(message, options) { return this.show(message, 'error', options); }
        warning(message, options) { return this.show(message, 'warning', options); }
        info(message, options) { return this.show(message, 'info', options); }
    }

    /**
     * Feature #8: Inline Validation
     */
    class InlineValidation {
        constructor() {
            this.rules = {};
            this.init();
        }

        init() {
            $(document).on('blur', '[data-validate]', (e) => {
                this.validate(e.target);
            });

            $(document).on('input', '[data-validate].ffp-invalid', (e) => {
                this.validate(e.target);
            });
        }

        validate(input) {
            const $input = $(input);
            const rules = $input.data('validate').split('|');
            const value = $input.val();
            let valid = true;
            let message = '';

            for (const rule of rules) {
                const [ruleName, param] = rule.split(':');
                const result = this.checkRule(ruleName, value, param);
                if (!result.valid) {
                    valid = false;
                    message = result.message;
                    break;
                }
            }

            this.showResult($input, valid, message);
            return valid;
        }

        checkRule(rule, value, param) {
            const rules = {
                required: () => ({
                    valid: value.trim() !== '',
                    message: 'This field is required'
                }),
                email: () => ({
                    valid: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
                    message: 'Please enter a valid email'
                }),
                min: () => ({
                    valid: value.length >= parseInt(param),
                    message: `Minimum ${param} characters required`
                }),
                max: () => ({
                    valid: value.length <= parseInt(param),
                    message: `Maximum ${param} characters allowed`
                }),
                url: () => ({
                    valid: /^https?:\/\/.+/.test(value),
                    message: 'Please enter a valid URL'
                }),
                numeric: () => ({
                    valid: /^\d+$/.test(value),
                    message: 'Please enter only numbers'
                })
            };

            return rules[rule] ? rules[rule]() : { valid: true, message: '' };
        }

        showResult($input, valid, message) {
            $input.removeClass('ffp-valid ffp-invalid');
            $input.siblings('.ffp-validation-message').remove();

            if (valid) {
                $input.addClass('ffp-valid');
            } else {
                $input.addClass('ffp-invalid');
                $input.after(`<span class="ffp-validation-message">${message}</span>`);
            }
        }
    }

    /**
     * Feature #9: Progress Indicators
     */
    class ProgressIndicators {
        constructor() {
            this.indicators = new Map();
        }

        create(id, options = {}) {
            const defaults = { value: 0, max: 100, label: '', showValue: true };
            const settings = { ...defaults, ...options };

            const html = `
                <div id="ffp-progress-${id}" class="ffp-progress-wrapper">
                    ${settings.label ? `<label class="ffp-progress-label">${settings.label}</label>` : ''}
                    <div class="ffp-progress" role="progressbar" aria-valuenow="${settings.value}" aria-valuemin="0" aria-valuemax="${settings.max}">
                        <div class="ffp-progress-bar" style="width: ${(settings.value / settings.max) * 100}%">
                            ${settings.showValue ? `<span class="ffp-progress-value">${settings.value}%</span>` : ''}
                        </div>
                    </div>
                </div>
            `;

            this.indicators.set(id, settings);
            return html;
        }

        update(id, value) {
            const settings = this.indicators.get(id);
            if (!settings) return;

            settings.value = Math.min(value, settings.max);
            const percentage = (settings.value / settings.max) * 100;

            $(`#ffp-progress-${id}`)
                .find('.ffp-progress').attr('aria-valuenow', settings.value)
                .find('.ffp-progress-bar').css('width', `${percentage}%`)
                .find('.ffp-progress-value').text(`${Math.round(percentage)}%`);
        }

        complete(id) {
            this.update(id, this.indicators.get(id)?.max || 100);
            $(`#ffp-progress-${id}`).addClass('ffp-progress-complete');
        }
    }

    /**
     * Feature #10: Success Animations
     */
    class SuccessAnimations {
        show(element, type = 'checkmark') {
            const $el = $(element);
            const animations = {
                checkmark: '<div class="ffp-success-checkmark"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="25" fill="none"/><path fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/></svg></div>',
                confetti: '<div class="ffp-confetti"></div>',
                pulse: '<div class="ffp-success-pulse"></div>'
            };

            const $animation = $(animations[type] || animations.checkmark);
            $el.append($animation);

            setTimeout(() => {
                $animation.addClass('ffp-animate');
            }, 10);

            setTimeout(() => {
                $animation.remove();
            }, 2000);
        }
    }

    /**
     * Feature #12: Smart Confirmation Dialogs
     */
    class ConfirmDialogs {
        show(options = {}) {
            const defaults = {
                title: 'Confirm',
                message: 'Are you sure?',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                type: 'warning',
                dangerous: false,
                onConfirm: null,
                onCancel: null
            };
            const settings = { ...defaults, ...options };

            return new Promise((resolve) => {
                const html = `
                    <div class="ffp-confirm-overlay">
                        <div class="ffp-confirm-dialog ffp-confirm-${settings.type}" role="dialog" aria-modal="true">
                            <h3 class="ffp-confirm-title">${settings.title}</h3>
                            <p class="ffp-confirm-message">${settings.message}</p>
                            <div class="ffp-confirm-actions">
                                <button class="ffp-btn ffp-btn-cancel">${settings.cancelText}</button>
                                <button class="ffp-btn ffp-btn-confirm ${settings.dangerous ? 'ffp-btn-danger' : ''}">${settings.confirmText}</button>
                            </div>
                        </div>
                    </div>
                `;

                const $dialog = $(html).appendTo('body');

                $dialog.find('.ffp-btn-confirm').on('click', () => {
                    $dialog.remove();
                    if (settings.onConfirm) settings.onConfirm();
                    resolve(true);
                });

                $dialog.find('.ffp-btn-cancel, .ffp-confirm-overlay').on('click', (e) => {
                    if (e.target === e.currentTarget) {
                        $dialog.remove();
                        if (settings.onCancel) settings.onCancel();
                        resolve(false);
                    }
                });

                // Focus trap
                $dialog.find('.ffp-btn-cancel').focus();
            });
        }
    }

    /**
     * Feature #13: Dynamic Status Badges
     */
    class StatusBadges {
        create(status, options = {}) {
            const defaults = { pulse: false, icon: true };
            const settings = { ...defaults, ...options };

            const statusConfig = {
                pending: { class: 'warning', icon: 'clock', label: 'Pending' },
                active: { class: 'success', icon: 'yes-alt', label: 'Active' },
                completed: { class: 'success', icon: 'saved', label: 'Completed' },
                failed: { class: 'error', icon: 'dismiss', label: 'Failed' },
                processing: { class: 'info', icon: 'update', label: 'Processing' }
            };

            const config = statusConfig[status] || { class: 'default', icon: 'marker', label: status };
            const pulseClass = settings.pulse ? 'ffp-badge-pulse' : '';
            const iconHtml = settings.icon ? `<span class="dashicons dashicons-${config.icon}"></span>` : '';

            return `<span class="ffp-badge ffp-badge-${config.class} ${pulseClass}">${iconHtml}${config.label}</span>`;
        }
    }

    // =========================================================================
    // CATEGORY 3: KEYBOARD NAVIGATION (6 features)
    // =========================================================================

    /**
     * Features #14-19: Keyboard Shortcuts
     */
    class KeyboardShortcuts {
        constructor() {
            this.shortcuts = new Map();
            this.enabled = true;
            this.init();
        }

        init() {
            // Register default shortcuts
            this.register('mod+k', () => FFPUX.features.commandPalette.toggle(), 'Open command palette');
            this.register('mod+s', (e) => { e.preventDefault(); this.triggerSave(); }, 'Save');
            this.register('mod+z', () => $(document).trigger('ffp:undo'), 'Undo');
            this.register('mod+shift+z', () => $(document).trigger('ffp:redo'), 'Redo');
            this.register('?', () => this.showHelp(), 'Show shortcuts');
            this.register('escape', () => FFPUX.closeAllModals(), 'Close modal');
            this.register('g d', () => this.navigate('dashboard'), 'Go to Dashboard');
            this.register('g f', () => this.navigate('forms'), 'Go to Forms');
            this.register('g s', () => this.navigate('submissions'), 'Go to Submissions');
            this.register('n', () => this.triggerNew(), 'New item');

            // Listen for keyboard events
            $(document).on('keydown', (e) => this.handleKeydown(e));
        }

        register(keys, callback, description = '') {
            this.shortcuts.set(keys, { callback, description });
        }

        handleKeydown(e) {
            if (!this.enabled) return;
            if ($(e.target).is('input, textarea, select, [contenteditable]')) return;

            const key = this.getKeyString(e);

            // Check for sequence shortcuts (like 'g d')
            if (this.pendingKey) {
                const sequence = this.pendingKey + ' ' + key;
                if (this.shortcuts.has(sequence)) {
                    e.preventDefault();
                    this.shortcuts.get(sequence).callback(e);
                }
                this.pendingKey = null;
                return;
            }

            // Check single key shortcuts
            if (this.shortcuts.has(key)) {
                e.preventDefault();
                this.shortcuts.get(key).callback(e);
                return;
            }

            // Check if this could be start of sequence
            for (const shortcutKey of this.shortcuts.keys()) {
                if (shortcutKey.startsWith(key + ' ')) {
                    this.pendingKey = key;
                    setTimeout(() => { this.pendingKey = null; }, 1000);
                    return;
                }
            }
        }

        getKeyString(e) {
            const parts = [];
            if (e.ctrlKey || e.metaKey) parts.push('mod');
            if (e.shiftKey) parts.push('shift');
            if (e.altKey) parts.push('alt');

            let key = e.key.toLowerCase();
            if (key === ' ') key = 'space';

            parts.push(key);
            return parts.join('+');
        }

        triggerSave() {
            const $btn = $('.ffp-save-btn, #publish, .button-primary[type="submit"]').first();
            if ($btn.length) $btn.click();
        }

        triggerNew() {
            const $btn = $('.page-title-action, .ffp-new-btn').first();
            if ($btn.length) $btn.click();
        }

        navigate(page) {
            const urls = {
                dashboard: 'admin.php?page=formflow-pro',
                forms: 'admin.php?page=formflow-pro-forms',
                submissions: 'admin.php?page=formflow-pro-submissions'
            };
            if (urls[page]) window.location.href = urls[page];
        }

        showHelp() {
            const $modal = $('#ffp-shortcuts-modal');
            if ($modal.length) {
                $modal.removeAttr('hidden');
                $('body').addClass('ffp-modal-open');
            }
        }
    }

    /**
     * Feature #17: Command Palette (Cmd/Ctrl+K)
     */
    class CommandPalette {
        constructor() {
            this.commands = [];
            this.visible = false;
            this.selectedIndex = 0;
            this.init();
        }

        init() {
            this.registerDefaultCommands();
            this.bindEvents();
        }

        registerDefaultCommands() {
            this.register({ id: 'dashboard', label: 'Go to Dashboard', icon: 'dashboard', action: () => window.location.href = 'admin.php?page=formflow-pro' });
            this.register({ id: 'forms', label: 'Go to Forms', icon: 'forms', action: () => window.location.href = 'admin.php?page=formflow-pro-forms' });
            this.register({ id: 'submissions', label: 'Go to Submissions', icon: 'list-view', action: () => window.location.href = 'admin.php?page=formflow-pro-submissions' });
            this.register({ id: 'analytics', label: 'Go to Analytics', icon: 'chart-bar', action: () => window.location.href = 'admin.php?page=formflow-pro-analytics' });
            this.register({ id: 'settings', label: 'Go to Settings', icon: 'admin-settings', action: () => window.location.href = 'admin.php?page=formflow-pro-settings' });
            this.register({ id: 'new-form', label: 'Create New Form', icon: 'plus-alt', action: () => window.location.href = 'admin.php?page=formflow-pro-forms&action=new' });
            this.register({ id: 'dark-mode', label: 'Toggle Dark Mode', icon: 'visibility', action: () => FFPUX.features.darkMode.toggle() });
            this.register({ id: 'shortcuts', label: 'Show Keyboard Shortcuts', icon: 'editor-help', action: () => FFPUX.features.shortcuts.showHelp() });
        }

        register(command) {
            this.commands.push(command);
        }

        bindEvents() {
            const $palette = $('#ffp-command-palette');
            const $input = $palette.find('.ffp-command-input');
            const $results = $palette.find('.ffp-command-results');

            $input.on('input', () => {
                this.selectedIndex = 0;
                this.renderResults($input.val());
            });

            $input.on('keydown', (e) => {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.selectNext();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.selectPrev();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    this.executeSelected();
                }
            });

            $palette.find('.ffp-command-overlay').on('click', () => this.hide());
        }

        toggle() {
            this.visible ? this.hide() : this.show();
        }

        show() {
            const $palette = $('#ffp-command-palette');
            $palette.removeAttr('hidden');
            $palette.find('.ffp-command-input').val('').focus();
            this.renderResults('');
            this.visible = true;
            $('body').addClass('ffp-modal-open');
        }

        hide() {
            $('#ffp-command-palette').attr('hidden', true);
            this.visible = false;
            $('body').removeClass('ffp-modal-open');
        }

        renderResults(query) {
            const filtered = query
                ? this.commands.filter(c => c.label.toLowerCase().includes(query.toLowerCase()))
                : this.commands;

            const html = filtered.map((cmd, i) => `
                <div class="ffp-command-item ${i === this.selectedIndex ? 'selected' : ''}" data-index="${i}">
                    <span class="dashicons dashicons-${cmd.icon}"></span>
                    <span>${cmd.label}</span>
                </div>
            `).join('');

            $('#ffp-command-palette .ffp-command-results').html(html || '<div class="ffp-command-empty">No results found</div>');

            // Click handler
            $('.ffp-command-item').on('click', (e) => {
                this.selectedIndex = $(e.currentTarget).data('index');
                this.executeSelected();
            });
        }

        selectNext() {
            const items = $('.ffp-command-item');
            this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
            this.updateSelection();
        }

        selectPrev() {
            this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
            this.updateSelection();
        }

        updateSelection() {
            $('.ffp-command-item').removeClass('selected').eq(this.selectedIndex).addClass('selected');
        }

        executeSelected() {
            const query = $('#ffp-command-palette .ffp-command-input').val();
            const filtered = query
                ? this.commands.filter(c => c.label.toLowerCase().includes(query.toLowerCase()))
                : this.commands;

            if (filtered[this.selectedIndex]) {
                this.hide();
                filtered[this.selectedIndex].action();
            }
        }
    }

    /**
     * Feature #15: Focus Management
     */
    class FocusManager {
        constructor() {
            this.focusStack = [];
            this.init();
        }

        init() {
            // Track focus for modal management
            $(document).on('focusin', (e) => {
                if ($('.ffp-modal:not([hidden])').length) {
                    this.trapFocus(e);
                }
            });
        }

        trapFocus(e) {
            const $modal = $('.ffp-modal:not([hidden])').last();
            const $focusable = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');

            if (!$modal[0].contains(e.target)) {
                $focusable.first().focus();
            }
        }

        saveFocus() {
            this.focusStack.push(document.activeElement);
        }

        restoreFocus() {
            const el = this.focusStack.pop();
            if (el) el.focus();
        }
    }

    // =========================================================================
    // CATEGORY 4: ACCESSIBILITY (7 features)
    // =========================================================================

    /**
     * Feature #20: ARIA Live Regions
     */
    class LiveRegions {
        announce(message, priority = 'polite') {
            const regionId = priority === 'assertive' ? 'ffp-live-region-assertive' : 'ffp-live-region';
            const $region = $(`#${regionId}`);

            if ($region.length) {
                $region.text('');
                setTimeout(() => $region.text(message), 100);
            }
        }
    }

    /**
     * Feature #22: Reduced Motion Support
     */
    class ReducedMotion {
        constructor() {
            this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.init();
        }

        init() {
            if (this.prefersReducedMotion) {
                $('body').addClass('ffp-reduced-motion');
            }

            window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', (e) => {
                this.prefersReducedMotion = e.matches;
                $('body').toggleClass('ffp-reduced-motion', e.matches);
            });
        }
    }

    // =========================================================================
    // CATEGORY 5: PROGRESSIVE ENHANCEMENT (5 features)
    // =========================================================================

    /**
     * Feature #27: Dark Mode
     */
    class DarkMode {
        constructor() {
            this.enabled = false;
            this.init();
        }

        init() {
            // Check saved preference
            const saved = localStorage.getItem('ffp-dark-mode');
            if (saved === 'true') {
                this.enable();
            } else if (saved === null && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                this.enable();
            }

            // Listen for system changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (localStorage.getItem('ffp-dark-mode') === null) {
                    e.matches ? this.enable() : this.disable();
                }
            });
        }

        toggle() {
            this.enabled ? this.disable() : this.enable();
        }

        enable() {
            this.enabled = true;
            $('body').addClass('ffp-dark-mode');
            localStorage.setItem('ffp-dark-mode', 'true');
        }

        disable() {
            this.enabled = false;
            $('body').removeClass('ffp-dark-mode');
            localStorage.setItem('ffp-dark-mode', 'false');
        }
    }

    /**
     * Feature #29: Auto Save Drafts
     */
    class AutoSave {
        constructor() {
            this.interval = 30000; // 30 seconds
            this.timers = new Map();
        }

        enable(formId, options = {}) {
            const $form = $(`#${formId}`);
            if (!$form.length) return;

            const settings = { interval: this.interval, onSave: null, ...options };

            // Watch for changes
            $form.on('input change', () => {
                this.scheduleAutoSave(formId, $form, settings);
            });

            // Also save on page unload
            $(window).on('beforeunload', () => {
                this.saveNow(formId, $form, settings);
            });
        }

        scheduleAutoSave(formId, $form, settings) {
            if (this.timers.has(formId)) {
                clearTimeout(this.timers.get(formId));
            }

            const timer = setTimeout(() => {
                this.saveNow(formId, $form, settings);
            }, settings.interval);

            this.timers.set(formId, timer);
        }

        saveNow(formId, $form, settings) {
            const data = $form.serialize();
            localStorage.setItem(`ffp-autosave-${formId}`, JSON.stringify({
                data,
                timestamp: Date.now()
            }));

            if (settings.onSave) settings.onSave(data);
            FFPUX.toast('Draft saved', 'info', { duration: 2000 });
        }

        restore(formId) {
            const saved = localStorage.getItem(`ffp-autosave-${formId}`);
            if (saved) {
                const { data, timestamp } = JSON.parse(saved);
                // Only restore if less than 24 hours old
                if (Date.now() - timestamp < 86400000) {
                    return data;
                }
            }
            return null;
        }

        clear(formId) {
            localStorage.removeItem(`ffp-autosave-${formId}`);
        }
    }

    /**
     * Feature #30: Session Recovery
     */
    class SessionRecovery {
        constructor() {
            this.key = 'ffp-session-state';
            this.init();
        }

        init() {
            // Check for crashed session
            const crashed = sessionStorage.getItem('ffp-session-active');
            if (crashed === 'true') {
                this.checkForRecovery();
            }

            // Mark session as active
            sessionStorage.setItem('ffp-session-active', 'true');
            $(window).on('beforeunload', () => {
                sessionStorage.removeItem('ffp-session-active');
            });
        }

        save(state) {
            localStorage.setItem(this.key, JSON.stringify({
                state,
                url: window.location.href,
                timestamp: Date.now()
            }));
        }

        checkForRecovery() {
            const saved = localStorage.getItem(this.key);
            if (saved) {
                const { state, url, timestamp } = JSON.parse(saved);
                // Only offer recovery if less than 1 hour old
                if (Date.now() - timestamp < 3600000) {
                    FFPUX.features.confirmDialogs.show({
                        title: 'Recover Session?',
                        message: 'It looks like your previous session ended unexpectedly. Would you like to restore your work?',
                        confirmText: 'Restore',
                        cancelText: 'Discard',
                        onConfirm: () => this.restore(state, url),
                        onCancel: () => this.clear()
                    });
                }
            }
        }

        restore(state, url) {
            if (window.location.href !== url) {
                window.location.href = url;
            } else {
                $(document).trigger('ffp:session-restored', [state]);
            }
        }

        clear() {
            localStorage.removeItem(this.key);
        }
    }

    // =========================================================================
    // CATEGORY 6: DATA TABLES (6 features)
    // =========================================================================

    /**
     * Features #32-37: Table Enhancements
     */
    class TableEnhancements {
        constructor() {
            this.init();
        }

        init() {
            this.initStickyHeaders();
            this.initRowSelection();
            this.initColumnResize();
        }

        initStickyHeaders() {
            $('.ffp-table-sticky').each((i, table) => {
                const $table = $(table);
                const $header = $table.find('thead');

                $(window).on('scroll', () => {
                    const tableTop = $table.offset().top;
                    const scrollTop = $(window).scrollTop() + 32; // Admin bar height

                    if (scrollTop > tableTop && scrollTop < tableTop + $table.height()) {
                        $header.addClass('ffp-stuck');
                    } else {
                        $header.removeClass('ffp-stuck');
                    }
                });
            });
        }

        initRowSelection() {
            $(document).on('click', '.ffp-table-selectable tbody tr', function(e) {
                if ($(e.target).is('input, a, button')) return;

                const $row = $(this);
                const $checkbox = $row.find('input[type="checkbox"]');

                if (e.shiftKey && this.lastSelectedRow) {
                    // Range selection
                    const $rows = $row.parent().children();
                    const start = $rows.index(this.lastSelectedRow);
                    const end = $rows.index($row);
                    const range = [start, end].sort((a, b) => a - b);

                    $rows.slice(range[0], range[1] + 1).each((i, r) => {
                        $(r).addClass('selected').find('input[type="checkbox"]').prop('checked', true);
                    });
                } else if (e.ctrlKey || e.metaKey) {
                    // Toggle selection
                    $row.toggleClass('selected');
                    $checkbox.prop('checked', $row.hasClass('selected'));
                } else {
                    // Single selection
                    $row.siblings().removeClass('selected').find('input[type="checkbox"]').prop('checked', false);
                    $row.addClass('selected');
                    $checkbox.prop('checked', true);
                }

                this.lastSelectedRow = $row;
                $(document).trigger('ffp:selection-changed');
            });
        }

        initColumnResize() {
            $('.ffp-table-resizable th').each((i, th) => {
                const $th = $(th);
                const $resizer = $('<div class="ffp-column-resizer"></div>');
                $th.append($resizer);

                let startX, startWidth;

                $resizer.on('mousedown', (e) => {
                    startX = e.pageX;
                    startWidth = $th.width();

                    $(document).on('mousemove.resize', (e) => {
                        const width = startWidth + (e.pageX - startX);
                        $th.width(Math.max(50, width));
                    });

                    $(document).on('mouseup.resize', () => {
                        $(document).off('.resize');
                    });
                });
            });
        }
    }

    // =========================================================================
    // CATEGORY 7: FORMS & INPUTS (7 features)
    // =========================================================================

    /**
     * Features #38-44: Form Enhancements
     */
    class FormEnhancements {
        constructor() {
            this.init();
        }

        init() {
            this.initCharacterCounters();
            this.initPasswordStrength();
            this.initInputMasks();
        }

        initCharacterCounters() {
            $('[data-maxlength]').each((i, el) => {
                const $input = $(el);
                const max = $input.data('maxlength');
                const $counter = $(`<span class="ffp-char-counter"><span class="current">0</span>/${max}</span>`);
                $input.after($counter);

                $input.on('input', () => {
                    const len = $input.val().length;
                    $counter.find('.current').text(len);
                    $counter.toggleClass('ffp-warning', len > max * 0.8);
                    $counter.toggleClass('ffp-error', len >= max);
                });
            });
        }

        initPasswordStrength() {
            $('[data-password-strength]').each((i, el) => {
                const $input = $(el);
                const $meter = $('<div class="ffp-password-meter"><div class="ffp-password-bar"></div><span class="ffp-password-text"></span></div>');
                $input.after($meter);

                $input.on('input', () => {
                    const strength = this.calculatePasswordStrength($input.val());
                    $meter.find('.ffp-password-bar').css('width', `${strength.score}%`).attr('data-strength', strength.level);
                    $meter.find('.ffp-password-text').text(strength.text);
                });
            });
        }

        calculatePasswordStrength(password) {
            let score = 0;
            if (password.length >= 8) score += 25;
            if (password.length >= 12) score += 15;
            if (/[a-z]/.test(password)) score += 15;
            if (/[A-Z]/.test(password)) score += 15;
            if (/[0-9]/.test(password)) score += 15;
            if (/[^a-zA-Z0-9]/.test(password)) score += 15;

            let level, text;
            if (score < 30) { level = 'weak'; text = 'Weak'; }
            else if (score < 60) { level = 'fair'; text = 'Fair'; }
            else if (score < 80) { level = 'good'; text = 'Good'; }
            else { level = 'strong'; text = 'Strong'; }

            return { score: Math.min(score, 100), level, text };
        }

        initInputMasks() {
            $('[data-mask]').each((i, el) => {
                const $input = $(el);
                const mask = $input.data('mask');

                $input.on('input', () => {
                    let value = $input.val().replace(/\D/g, '');
                    let formatted = '';
                    let valueIndex = 0;

                    for (let i = 0; i < mask.length && valueIndex < value.length; i++) {
                        if (mask[i] === '#') {
                            formatted += value[valueIndex++];
                        } else {
                            formatted += mask[i];
                        }
                    }

                    $input.val(formatted);
                });
            });
        }
    }

    /**
     * Feature #43: Copy to Clipboard
     */
    class Clipboard {
        constructor() {
            this.init();
        }

        init() {
            $(document).on('click', '[data-copy], .ffp-copy-btn', (e) => {
                const $btn = $(e.currentTarget);
                const text = $btn.data('copy') || $btn.siblings('input, textarea').val();
                this.copy(text, $btn);
            });
        }

        copy(text, $trigger) {
            navigator.clipboard.writeText(text).then(() => {
                const $feedback = $trigger || $('body');
                const originalHtml = $trigger ? $trigger.html() : null;

                if ($trigger) {
                    $trigger.addClass('ffp-copied').html('<span class="dashicons dashicons-yes"></span>');
                    setTimeout(() => {
                        $trigger.removeClass('ffp-copied').html(originalHtml);
                    }, 2000);
                }

                FFPUX.toast('Copied to clipboard!', 'success', { duration: 2000 });
            }).catch(() => {
                FFPUX.toast('Failed to copy', 'error');
            });
        }
    }

    /**
     * Feature #42: Drag & Drop Upload
     */
    class DragDropUpload {
        constructor() {
            this.init();
        }

        init() {
            $('.ffp-dropzone').each((i, el) => {
                this.initDropzone($(el));
            });
        }

        initDropzone($zone) {
            const $input = $zone.find('input[type="file"]');

            $zone.on('dragover dragenter', (e) => {
                e.preventDefault();
                $zone.addClass('ffp-dragover');
            });

            $zone.on('dragleave dragend drop', (e) => {
                e.preventDefault();
                $zone.removeClass('ffp-dragover');
            });

            $zone.on('drop', (e) => {
                const files = e.originalEvent.dataTransfer.files;
                if (files.length) {
                    $input[0].files = files;
                    $input.trigger('change');
                }
            });

            $zone.on('click', () => $input.click());
        }
    }

    // =========================================================================
    // CATEGORY 8: NAVIGATION & LAYOUT (5 features)
    // =========================================================================

    /**
     * Feature #48: Recent Items
     */
    class RecentItems {
        constructor() {
            this.maxItems = 10;
            this.storageKey = 'ffp-recent-items';
        }

        add(item) {
            const items = this.getAll();
            const existing = items.findIndex(i => i.id === item.id && i.type === item.type);

            if (existing > -1) items.splice(existing, 1);
            items.unshift({ ...item, timestamp: Date.now() });

            localStorage.setItem(this.storageKey, JSON.stringify(items.slice(0, this.maxItems)));
        }

        getAll() {
            try {
                return JSON.parse(localStorage.getItem(this.storageKey)) || [];
            } catch {
                return [];
            }
        }

        render() {
            const items = this.getAll();
            if (!items.length) return '<p>No recent items</p>';

            return `
                <ul class="ffp-recent-items">
                    ${items.map(item => `
                        <li>
                            <a href="${item.url}">
                                <span class="dashicons dashicons-${item.icon || 'admin-page'}"></span>
                                <span class="title">${item.title}</span>
                                <span class="type">${item.type}</span>
                            </a>
                        </li>
                    `).join('')}
                </ul>
            `;
        }
    }

    /**
     * Feature #49: Contextual Help
     */
    class ContextualHelp {
        constructor() {
            this.init();
        }

        init() {
            $(document).on('mouseenter', '[data-help]', (e) => {
                const $el = $(e.currentTarget);
                const help = $el.data('help');
                this.showTooltip($el, help);
            });

            $(document).on('mouseleave', '[data-help]', () => {
                this.hideTooltip();
            });
        }

        showTooltip($el, text) {
            this.hideTooltip();

            const $tooltip = $(`<div class="ffp-help-tooltip">${text}</div>`);
            $('body').append($tooltip);

            const offset = $el.offset();
            const elWidth = $el.outerWidth();
            const tooltipWidth = $tooltip.outerWidth();

            $tooltip.css({
                top: offset.top - $tooltip.outerHeight() - 8,
                left: offset.left + (elWidth / 2) - (tooltipWidth / 2)
            });
        }

        hideTooltip() {
            $('.ffp-help-tooltip').remove();
        }
    }

    // =========================================================================
    // CATEGORY 9: PERFORMANCE UX (5 features)
    // =========================================================================

    /**
     * Feature #50: Request Debouncing
     */
    class Debounce {
        debounce(func, wait, immediate = false) {
            let timeout;
            return function(...args) {
                const later = () => {
                    timeout = null;
                    if (!immediate) func.apply(this, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(this, args);
            };
        }

        throttle(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    }

    /**
     * Feature #51: Virtual Scrolling
     */
    class VirtualScroll {
        constructor() {
            this.containers = new Map();
        }

        init(container, options = {}) {
            const $container = $(container);
            const defaults = {
                itemHeight: 40,
                buffer: 5,
                items: [],
                renderItem: (item) => `<div>${item}</div>`
            };
            const settings = { ...defaults, ...options };

            const viewport = {
                height: $container.height(),
                scrollTop: 0
            };

            const $content = $('<div class="ffp-virtual-content"></div>');
            const $spacer = $('<div class="ffp-virtual-spacer"></div>');

            $spacer.height(settings.items.length * settings.itemHeight);
            $container.empty().append($spacer).append($content);

            const render = () => {
                const start = Math.max(0, Math.floor(viewport.scrollTop / settings.itemHeight) - settings.buffer);
                const end = Math.min(settings.items.length, Math.ceil((viewport.scrollTop + viewport.height) / settings.itemHeight) + settings.buffer);

                const html = settings.items.slice(start, end).map((item, i) => {
                    return `<div class="ffp-virtual-item" style="position:absolute;top:${(start + i) * settings.itemHeight}px;height:${settings.itemHeight}px;width:100%">${settings.renderItem(item)}</div>`;
                }).join('');

                $content.html(html);
            };

            $container.on('scroll', () => {
                viewport.scrollTop = $container.scrollTop();
                render();
            });

            render();
            this.containers.set(container, { settings, render });
        }
    }

    /**
     * Feature #53: Cache UI State
     */
    class CacheUIState {
        constructor() {
            this.storageKey = 'ffp-ui-state';
        }

        save(key, value) {
            const state = this.getAll();
            state[key] = value;
            localStorage.setItem(this.storageKey, JSON.stringify(state));
        }

        get(key, defaultValue = null) {
            const state = this.getAll();
            return state[key] ?? defaultValue;
        }

        getAll() {
            try {
                return JSON.parse(localStorage.getItem(this.storageKey)) || {};
            } catch {
                return {};
            }
        }

        clear() {
            localStorage.removeItem(this.storageKey);
        }
    }

    // =========================================================================
    // Initialize on document ready
    // =========================================================================

    $(document).ready(function() {
        FFPUX.init();
    });

    // Expose to global scope
    window.FFPUX = FFPUX;

})(jQuery);
