<?php
/**
 * FormFlow Pro - Service Worker Manager
 *
 * Manages service worker generation, registration, and caching strategies
 * for offline form support.
 *
 * @package FormFlowPro
 * @subpackage PWA
 * @since 3.0.0
 */

namespace FormFlowPro\PWA;

use FormFlowPro\Traits\SingletonTrait;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service Worker Manager
 */
class ServiceWorkerManager
{
    use SingletonTrait;

    private string $sw_version;
    private array $cache_strategies = [];

    protected function init(): void
    {
        $this->sw_version = JEFORM_VERSION . '.' . get_option('ffp_sw_version', '1');

        $this->registerHooks();
        $this->defineCacheStrategies();
    }

    private function registerHooks(): void
    {
        // Register service worker endpoint
        add_action('init', [$this, 'registerRewriteRules']);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('template_redirect', [$this, 'handleServiceWorkerRequest']);

        // Add service worker registration script
        add_action('wp_footer', [$this, 'outputRegistrationScript'], 100);

        // Add to head for manifest link
        add_action('wp_head', [$this, 'outputManifestLink']);

        // Admin settings
        add_action('admin_init', [$this, 'registerSettings']);

        // Clear cache on updates
        add_action('ffp_form_updated', [$this, 'bumpVersion']);
        add_action('switch_theme', [$this, 'bumpVersion']);
    }

    private function defineCacheStrategies(): void
    {
        $this->cache_strategies = [
            'cache_first' => [
                'name' => __('Cache First', 'form-flow-pro'),
                'description' => __('Serve from cache if available, fetch from network if not', 'form-flow-pro'),
            ],
            'network_first' => [
                'name' => __('Network First', 'form-flow-pro'),
                'description' => __('Try network first, fall back to cache if offline', 'form-flow-pro'),
            ],
            'stale_while_revalidate' => [
                'name' => __('Stale While Revalidate', 'form-flow-pro'),
                'description' => __('Serve from cache immediately, update cache in background', 'form-flow-pro'),
            ],
            'network_only' => [
                'name' => __('Network Only', 'form-flow-pro'),
                'description' => __('Always fetch from network, never cache', 'form-flow-pro'),
            ],
            'cache_only' => [
                'name' => __('Cache Only', 'form-flow-pro'),
                'description' => __('Only serve from cache, never fetch', 'form-flow-pro'),
            ],
        ];
    }

    /**
     * Register rewrite rules for service worker
     */
    public function registerRewriteRules(): void
    {
        add_rewrite_rule(
            'ffp-sw\.js$',
            'index.php?ffp_service_worker=1',
            'top'
        );

        add_rewrite_rule(
            'ffp-offline\.html$',
            'index.php?ffp_offline_page=1',
            'top'
        );
    }

    /**
     * Add query vars
     */
    public function addQueryVars(array $vars): array
    {
        $vars[] = 'ffp_service_worker';
        $vars[] = 'ffp_offline_page';
        return $vars;
    }

    /**
     * Handle service worker request
     */
    public function handleServiceWorkerRequest(): void
    {
        if (get_query_var('ffp_service_worker')) {
            $this->outputServiceWorker();
            exit;
        }

        if (get_query_var('ffp_offline_page')) {
            $this->outputOfflinePage();
            exit;
        }
    }

    /**
     * Output service worker JavaScript
     */
    private function outputServiceWorker(): void
    {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $settings = $this->getSettings();
        $cache_name = 'ffp-cache-v' . $this->sw_version;
        $form_cache_name = 'ffp-forms-v' . $this->sw_version;
        $submission_cache_name = 'ffp-submissions-v' . $this->sw_version;

        // Get URLs to precache
        $precache_urls = $this->getPrecacheUrls();

        echo $this->generateServiceWorkerCode($settings, $cache_name, $form_cache_name, $submission_cache_name, $precache_urls);
    }

    /**
     * Generate service worker code
     */
    private function generateServiceWorkerCode(
        array $settings,
        string $cache_name,
        string $form_cache_name,
        string $submission_cache_name,
        array $precache_urls
    ): string {
        $offline_url = home_url('/ffp-offline.html');
        $api_url = rest_url('form-flow-pro/v1');
        $ajax_url = admin_url('admin-ajax.php');

        $code = <<<JS
/**
 * FormFlow Pro Service Worker
 * Version: {$this->sw_version}
 */

const CACHE_NAME = '{$cache_name}';
const FORM_CACHE_NAME = '{$form_cache_name}';
const SUBMISSION_CACHE_NAME = '{$submission_cache_name}';
const OFFLINE_URL = '{$offline_url}';
const API_URL = '{$api_url}';
const AJAX_URL = '{$ajax_url}';

// URLs to precache
const PRECACHE_URLS = %s;

// Install event - precache essential resources
self.addEventListener('install', (event) => {
    console.log('[FFP SW] Installing service worker v{$this->sw_version}');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[FFP SW] Precaching resources');
                return cache.addAll(PRECACHE_URLS);
            })
            .then(() => {
                console.log('[FFP SW] Skip waiting');
                return self.skipWaiting();
            })
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    console.log('[FFP SW] Activating service worker');

    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => {
                        return name.startsWith('ffp-') &&
                               name !== CACHE_NAME &&
                               name !== FORM_CACHE_NAME &&
                               name !== SUBMISSION_CACHE_NAME;
                    })
                    .map((name) => {
                        console.log('[FFP SW] Deleting old cache:', name);
                        return caches.delete(name);
                    })
            );
        }).then(() => {
            console.log('[FFP SW] Claiming clients');
            return self.clients.claim();
        })
    );
});

// Fetch event - handle requests
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests for caching (except form submissions)
    if (request.method !== 'GET') {
        // Handle form submissions offline
        if (isFormSubmission(request)) {
            event.respondWith(handleFormSubmission(request));
            return;
        }
        return;
    }

    // API requests - network first
    if (url.pathname.startsWith('/wp-json/form-flow-pro/')) {
        event.respondWith(networkFirst(request, FORM_CACHE_NAME));
        return;
    }

    // Form pages - stale while revalidate
    if (isFormPage(url)) {
        event.respondWith(staleWhileRevalidate(request, FORM_CACHE_NAME));
        return;
    }

    // Static assets - cache first
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(request, CACHE_NAME));
        return;
    }

    // Other requests - network first with offline fallback
    event.respondWith(networkFirstWithOffline(request));
});

// Background sync for offline submissions
self.addEventListener('sync', (event) => {
    console.log('[FFP SW] Sync event:', event.tag);

    if (event.tag === 'ffp-sync-submissions') {
        event.waitUntil(syncOfflineSubmissions());
    }
});

// Push notifications
self.addEventListener('push', (event) => {
    console.log('[FFP SW] Push received');

    let data = {};

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = { title: 'FormFlow Pro', body: event.data.text() };
        }
    }

    const options = {
        body: data.body || '',
        icon: data.icon || '/wp-content/plugins/form-flow-pro/assets/images/icon-192.png',
        badge: data.badge || '/wp-content/plugins/form-flow-pro/assets/images/badge-72.png',
        vibrate: [100, 50, 100],
        data: data.data || {},
        actions: data.actions || [],
        tag: data.tag || 'ffp-notification',
        requireInteraction: data.requireInteraction || false,
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'FormFlow Pro', options)
    );
});

// Notification click
self.addEventListener('notificationclick', (event) => {
    console.log('[FFP SW] Notification clicked');

    event.notification.close();

    const data = event.notification.data || {};
    const url = data.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Focus existing window if available
                for (const client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// Helper functions

function isFormSubmission(request) {
    return request.method === 'POST' &&
           (request.url.includes('ffp_submit_form') ||
            request.url.includes('/form-flow-pro/v1/forms/'));
}

function isFormPage(url) {
    return url.searchParams.has('ffp_form') ||
           url.pathname.includes('/form/') ||
           document.querySelector && document.querySelector('.ffp-form');
}

function isStaticAsset(url) {
    return /\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot)$/i.test(url.pathname);
}

// Cache first strategy
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('[FFP SW] Cache first failed:', error);
        return new Response('Offline', { status: 503 });
    }
}

// Network first strategy
async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('[FFP SW] Network first - falling back to cache');
        const cached = await cache.match(request);
        return cached || new Response('Offline', { status: 503 });
    }
}

// Stale while revalidate strategy
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request).then((response) => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);

    return cached || fetchPromise;
}

// Network first with offline fallback
async function networkFirstWithOffline(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }

        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
        }

        return new Response('Offline', { status: 503 });
    }
}

// Handle form submission offline
async function handleFormSubmission(request) {
    try {
        // Try to submit online first
        const response = await fetch(request.clone());
        return response;
    } catch (error) {
        console.log('[FFP SW] Offline - queueing submission');

        // Queue submission for background sync
        const formData = await request.clone().formData();
        const submission = {
            url: request.url,
            method: request.method,
            data: Object.fromEntries(formData),
            timestamp: Date.now(),
        };

        await storeOfflineSubmission(submission);

        // Register for background sync
        if ('sync' in self.registration) {
            await self.registration.sync.register('ffp-sync-submissions');
        }

        // Return success response for better UX
        return new Response(JSON.stringify({
            success: true,
            offline: true,
            message: 'Your submission has been saved and will be sent when you\'re back online.'
        }), {
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Store offline submission
async function storeOfflineSubmission(submission) {
    const cache = await caches.open(SUBMISSION_CACHE_NAME);
    const key = new Request('ffp-submission-' + submission.timestamp);
    await cache.put(key, new Response(JSON.stringify(submission)));
}

// Get offline submissions
async function getOfflineSubmissions() {
    const cache = await caches.open(SUBMISSION_CACHE_NAME);
    const keys = await cache.keys();
    const submissions = [];

    for (const key of keys) {
        if (key.url.includes('ffp-submission-')) {
            const response = await cache.match(key);
            const submission = await response.json();
            submissions.push({ key, submission });
        }
    }

    return submissions;
}

// Sync offline submissions
async function syncOfflineSubmissions() {
    console.log('[FFP SW] Syncing offline submissions');

    const submissions = await getOfflineSubmissions();
    const cache = await caches.open(SUBMISSION_CACHE_NAME);

    for (const { key, submission } of submissions) {
        try {
            const formData = new FormData();
            for (const [name, value] of Object.entries(submission.data)) {
                formData.append(name, value);
            }

            const response = await fetch(submission.url, {
                method: submission.method,
                body: formData,
            });

            if (response.ok) {
                console.log('[FFP SW] Submission synced successfully');
                await cache.delete(key);

                // Notify clients
                const clients = await self.clients.matchAll();
                clients.forEach((client) => {
                    client.postMessage({
                        type: 'FFP_SUBMISSION_SYNCED',
                        timestamp: submission.timestamp,
                    });
                });
            }
        } catch (error) {
            console.log('[FFP SW] Failed to sync submission:', error);
        }
    }
}

// Message handler for client communication
self.addEventListener('message', (event) => {
    console.log('[FFP SW] Message received:', event.data);

    if (event.data.type === 'FFP_SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data.type === 'FFP_CACHE_FORM') {
        cacheForm(event.data.formId, event.data.formUrl);
    }

    if (event.data.type === 'FFP_CLEAR_CACHE') {
        clearAllCaches();
    }

    if (event.data.type === 'FFP_GET_OFFLINE_COUNT') {
        getOfflineSubmissions().then((submissions) => {
            event.ports[0].postMessage({ count: submissions.length });
        });
    }
});

// Cache a specific form
async function cacheForm(formId, formUrl) {
    const cache = await caches.open(FORM_CACHE_NAME);

    try {
        const response = await fetch(formUrl);
        if (response.ok) {
            await cache.put(formUrl, response);
            console.log('[FFP SW] Form cached:', formId);
        }
    } catch (error) {
        console.log('[FFP SW] Failed to cache form:', error);
    }
}

// Clear all caches
async function clearAllCaches() {
    const cacheNames = await caches.keys();
    await Promise.all(
        cacheNames
            .filter((name) => name.startsWith('ffp-'))
            .map((name) => caches.delete(name))
    );
    console.log('[FFP SW] All caches cleared');
}

console.log('[FFP SW] Service worker loaded v{$this->sw_version}');
JS;

        return sprintf($code, json_encode($precache_urls));
    }

    /**
     * Get URLs to precache
     */
    private function getPrecacheUrls(): array
    {
        $urls = [
            home_url('/ffp-offline.html'),
        ];

        // Add main plugin assets
        $plugin_url = plugins_url('', dirname(__DIR__));
        $urls[] = $plugin_url . '/assets/css/frontend.css';
        $urls[] = $plugin_url . '/assets/js/frontend.js';

        // Add active forms for offline access if enabled
        if (get_option('ffp_pwa_precache_forms', false)) {
            $forms = $this->getOfflineEnabledForms();
            foreach ($forms as $form_id) {
                $urls[] = home_url('/?ffp_form=' . $form_id);
            }
        }

        return array_filter(array_unique($urls));
    }

    /**
     * Get forms enabled for offline access
     */
    private function getOfflineEnabledForms(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_forms';

        return $wpdb->get_col(
            "SELECT id FROM {$table}
             WHERE status = 'published'
             AND JSON_EXTRACT(settings, '$.offline_enabled') = true"
        );
    }

    /**
     * Output offline page
     */
    private function outputOfflinePage(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $site_name = get_bloginfo('name');
        $custom_message = get_option('ffp_pwa_offline_message', __('You appear to be offline. Some features may not be available.', 'form-flow-pro'));

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - {$site_name}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .offline-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .offline-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 24px;
        }

        .offline-icon svg {
            width: 100%;
            height: 100%;
            fill: #667eea;
        }

        h1 {
            color: #1e293b;
            font-size: 24px;
            margin-bottom: 16px;
        }

        p {
            color: #64748b;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .retry-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .retry-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .pending-submissions {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .pending-count {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fef3c7;
            color: #92400e;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="offline-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M23.64 7c-.45-.34-4.93-4-11.64-4C5.28 3 .81 6.66.36 7l10.08 12.56c.8 1 2.32 1 3.12 0L23.64 7z" opacity="0.3"/>
                <path d="M1 9l9.36 11.67c.8 1 2.32 1 3.12 0L22.84 9l1.44-1.8c.22-.29.19-.69-.07-.93C23.56 5.72 19.08 2 12 2S.44 5.72.01 6.27c-.26.24-.29.64-.07.93L1 9zm11-5c4.85 0 8.73 2.36 10.34 4L12 20.5 1.66 8C3.27 6.36 7.15 4 12 4z"/>
                <path d="M21 18.5l-2.5-2.5-2.5 2.5 2.5 2.5zM19.5 13.5l-3 3 1.5 1.5 3-3zM4.5 15l3 3 1.5-1.5-3-3z"/>
            </svg>
        </div>

        <h1>You're Offline</h1>

        <p>{$custom_message}</p>

        <a href="javascript:location.reload()" class="retry-button">
            Try Again
        </a>

        <div class="pending-submissions" id="pendingSubmissions" style="display: none;">
            <div class="pending-count">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
                <span id="pendingCount">0</span> pending submission(s) will sync automatically
            </div>
        </div>
    </div>

    <script>
        // Check for pending submissions
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            const messageChannel = new MessageChannel();
            messageChannel.port1.onmessage = (event) => {
                if (event.data.count > 0) {
                    document.getElementById('pendingSubmissions').style.display = 'block';
                    document.getElementById('pendingCount').textContent = event.data.count;
                }
            };

            navigator.serviceWorker.controller.postMessage(
                { type: 'FFP_GET_OFFLINE_COUNT' },
                [messageChannel.port2]
            );
        }

        // Auto-retry when back online
        window.addEventListener('online', () => {
            location.reload();
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Output registration script
     */
    public function outputRegistrationScript(): void
    {
        if (!$this->isPWAEnabled()) {
            return;
        }

        $sw_url = home_url('/ffp-sw.js');
        ?>
        <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo esc_url($sw_url); ?>', {
                    scope: '/'
                }).then(function(registration) {
                    console.log('[FFP] Service Worker registered:', registration.scope);

                    // Check for updates
                    registration.addEventListener('updatefound', function() {
                        const newWorker = registration.installing;
                        console.log('[FFP] Service Worker update found');

                        newWorker.addEventListener('statechange', function() {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New version available
                                if (confirm('A new version is available. Reload to update?')) {
                                    newWorker.postMessage({ type: 'FFP_SKIP_WAITING' });
                                    window.location.reload();
                                }
                            }
                        });
                    });

                }).catch(function(error) {
                    console.log('[FFP] Service Worker registration failed:', error);
                });

                // Listen for messages from SW
                navigator.serviceWorker.addEventListener('message', function(event) {
                    if (event.data.type === 'FFP_SUBMISSION_SYNCED') {
                        console.log('[FFP] Offline submission synced');
                        // Show notification to user
                        if (window.ffpShowNotification) {
                            window.ffpShowNotification('Your form submission has been synced!', 'success');
                        }
                    }
                });
            });
        }

        // Handle online/offline status
        window.addEventListener('online', function() {
            document.body.classList.remove('ffp-offline');
            console.log('[FFP] Back online');
        });

        window.addEventListener('offline', function() {
            document.body.classList.add('ffp-offline');
            console.log('[FFP] Gone offline');
        });

        if (!navigator.onLine) {
            document.body.classList.add('ffp-offline');
        }
        </script>
        <?php
    }

    /**
     * Output manifest link
     */
    public function outputManifestLink(): void
    {
        if (!$this->isPWAEnabled()) {
            return;
        }

        echo '<link rel="manifest" href="' . esc_url(rest_url('form-flow-pro/v1/manifest.json')) . '">' . "\n";
        echo '<meta name="theme-color" content="' . esc_attr(get_option('ffp_pwa_theme_color', '#3b82f6')) . '">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr(get_option('ffp_pwa_short_name', get_bloginfo('name'))) . '">' . "\n";

        // Apple touch icons
        $icon_url = get_option('ffp_pwa_icon', '');
        if ($icon_url) {
            echo '<link rel="apple-touch-icon" href="' . esc_url($icon_url) . '">' . "\n";
        }
    }

    /**
     * Check if PWA is enabled
     */
    public function isPWAEnabled(): bool
    {
        return (bool) get_option('ffp_pwa_enabled', false);
    }

    /**
     * Get settings
     */
    private function getSettings(): array
    {
        return [
            'enabled' => $this->isPWAEnabled(),
            'offline_enabled' => get_option('ffp_pwa_offline_forms', true),
            'background_sync' => get_option('ffp_pwa_background_sync', true),
            'push_notifications' => get_option('ffp_pwa_push_notifications', true),
            'cache_strategy' => get_option('ffp_pwa_cache_strategy', 'network_first'),
        ];
    }

    /**
     * Bump SW version
     */
    public function bumpVersion(): void
    {
        $current = (int) get_option('ffp_sw_version', 1);
        update_option('ffp_sw_version', $current + 1);
    }

    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        register_setting('ffp_pwa_settings', 'ffp_pwa_enabled');
        register_setting('ffp_pwa_settings', 'ffp_pwa_short_name');
        register_setting('ffp_pwa_settings', 'ffp_pwa_description');
        register_setting('ffp_pwa_settings', 'ffp_pwa_theme_color');
        register_setting('ffp_pwa_settings', 'ffp_pwa_background_color');
        register_setting('ffp_pwa_settings', 'ffp_pwa_icon');
        register_setting('ffp_pwa_settings', 'ffp_pwa_offline_forms');
        register_setting('ffp_pwa_settings', 'ffp_pwa_background_sync');
        register_setting('ffp_pwa_settings', 'ffp_pwa_push_notifications');
        register_setting('ffp_pwa_settings', 'ffp_pwa_cache_strategy');
        register_setting('ffp_pwa_settings', 'ffp_pwa_offline_message');
        register_setting('ffp_pwa_settings', 'ffp_pwa_precache_forms');
    }

    /**
     * Get cache strategies
     */
    public function getCacheStrategies(): array
    {
        return $this->cache_strategies;
    }
}
