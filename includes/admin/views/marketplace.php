<?php

/**
 * Marketplace & Extensions Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get current view
$current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'browse';

// Get installed extensions
$installed_extensions = get_option('formflow_installed_extensions', []);

// Mock extensions data (in production, this would come from API)
$available_extensions = [
    [
        'id' => 'pdf-export',
        'name' => __('PDF Export Pro', 'formflow-pro'),
        'description' => __('Export form submissions to professionally formatted PDF documents with custom templates.', 'formflow-pro'),
        'author' => 'FormFlow Pro',
        'version' => '1.2.0',
        'price' => 'free',
        'category' => 'export',
        'downloads' => 5420,
        'rating' => 4.8,
        'icon' => 'dashicons-pdf',
    ],
    [
        'id' => 'conditional-logic',
        'name' => __('Advanced Conditional Logic', 'formflow-pro'),
        'description' => __('Create complex conditional rules for fields, sections, and form behavior.', 'formflow-pro'),
        'author' => 'FormFlow Pro',
        'version' => '2.0.1',
        'price' => 'free',
        'category' => 'forms',
        'downloads' => 8930,
        'rating' => 4.9,
        'icon' => 'dashicons-randomize',
    ],
    [
        'id' => 'zapier-integration',
        'name' => __('Zapier Integration', 'formflow-pro'),
        'description' => __('Connect your forms to 5000+ apps via Zapier automation.', 'formflow-pro'),
        'author' => 'FormFlow Pro',
        'version' => '1.5.0',
        'price' => 'premium',
        'category' => 'integrations',
        'downloads' => 3200,
        'rating' => 4.7,
        'icon' => 'dashicons-admin-plugins',
    ],
    [
        'id' => 'user-registration',
        'name' => __('User Registration', 'formflow-pro'),
        'description' => __('Create WordPress user accounts from form submissions with role assignment.', 'formflow-pro'),
        'author' => 'FormFlow Pro',
        'version' => '1.1.0',
        'price' => 'free',
        'category' => 'users',
        'downloads' => 4100,
        'rating' => 4.6,
        'icon' => 'dashicons-admin-users',
    ],
    [
        'id' => 'slack-notifications',
        'name' => __('Slack Notifications', 'formflow-pro'),
        'description' => __('Send form submission notifications directly to Slack channels.', 'formflow-pro'),
        'author' => 'Community',
        'version' => '1.0.3',
        'price' => 'free',
        'category' => 'notifications',
        'downloads' => 2800,
        'rating' => 4.5,
        'icon' => 'dashicons-format-chat',
    ],
    [
        'id' => 'crm-sync',
        'name' => __('CRM Sync Pro', 'formflow-pro'),
        'description' => __('Sync form data with Salesforce, HubSpot, Pipedrive, and more CRMs.', 'formflow-pro'),
        'author' => 'FormFlow Pro',
        'version' => '2.1.0',
        'price' => 'premium',
        'category' => 'integrations',
        'downloads' => 1900,
        'rating' => 4.8,
        'icon' => 'dashicons-networking',
    ],
    [
        'id' => 'spam-protection',
        'name' => __('Advanced Spam Protection', 'formflow-pro'),
        'description' => __('AI-powered spam detection with honeypot, reCAPTCHA, and custom rules.', 'formflow-pro'),
        'author' => 'FormFlow Pro',
        'version' => '1.3.0',
        'price' => 'free',
        'category' => 'security',
        'downloads' => 6700,
        'rating' => 4.9,
        'icon' => 'dashicons-shield',
    ],
    [
        'id' => 'google-sheets',
        'name' => __('Google Sheets Sync', 'formflow-pro'),
        'description' => __('Automatically sync form submissions to Google Sheets in real-time.', 'formflow-pro'),
        'author' => 'FormFlow Pro',
        'version' => '1.4.0',
        'price' => 'free',
        'category' => 'export',
        'downloads' => 7200,
        'rating' => 4.7,
        'icon' => 'dashicons-media-spreadsheet',
    ],
];

// Categories
$categories = [
    'all' => __('All Extensions', 'formflow-pro'),
    'forms' => __('Forms', 'formflow-pro'),
    'integrations' => __('Integrations', 'formflow-pro'),
    'export' => __('Export', 'formflow-pro'),
    'notifications' => __('Notifications', 'formflow-pro'),
    'security' => __('Security', 'formflow-pro'),
    'users' => __('Users', 'formflow-pro'),
];

$current_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : 'all';

?>

<div class="wrap formflow-admin formflow-marketplace">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-store"></span>
        <?php esc_html_e('Extension Marketplace', 'formflow-pro'); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- Tabs Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix" style="margin-top: 20px;">
        <a href="?page=formflow-marketplace&view=browse" class="nav-tab <?php echo $current_view === 'browse' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-store"></span>
            <?php esc_html_e('Browse Extensions', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-marketplace&view=installed" class="nav-tab <?php echo $current_view === 'installed' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php esc_html_e('Installed', 'formflow-pro'); ?>
            <?php if (!empty($installed_extensions)) : ?>
                <span class="count" style="background: #0073aa; color: #fff; padding: 0 6px; border-radius: 10px; font-size: 11px; margin-left: 5px;">
                    <?php echo count($installed_extensions); ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="?page=formflow-marketplace&view=developer" class="nav-tab <?php echo $current_view === 'developer' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-editor-code"></span>
            <?php esc_html_e('Developer SDK', 'formflow-pro'); ?>
        </a>
    </nav>

    <?php if ($current_view === 'browse') : ?>
        <!-- Browse Extensions View -->
        <div class="marketplace-browse" style="margin-top: 20px;">
            <!-- Search and Filter -->
            <div class="tablenav top" style="display: flex; gap: 20px; align-items: center; margin-bottom: 20px;">
                <div class="search-box">
                    <input type="search" id="extension-search" placeholder="<?php esc_attr_e('Search extensions...', 'formflow-pro'); ?>" class="regular-text" style="min-width: 300px;">
                </div>

                <div class="category-filter">
                    <?php foreach ($categories as $cat_key => $cat_label) : ?>
                        <a href="?page=formflow-marketplace&category=<?php echo esc_attr($cat_key); ?>"
                           class="button <?php echo $current_category === $cat_key ? 'button-primary' : ''; ?>">
                            <?php echo esc_html($cat_label); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Extensions Grid -->
            <div class="extensions-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                <?php foreach ($available_extensions as $extension) :
                    if ($current_category !== 'all' && $extension['category'] !== $current_category) {
                        continue;
                    }
                    $is_installed = in_array($extension['id'], $installed_extensions);
                ?>
                    <div class="extension-card card" style="padding: 0; overflow: hidden; <?php echo $is_installed ? 'border-left: 4px solid #46b450;' : ''; ?>">
                        <div style="padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <div style="display: flex; gap: 15px; align-items: center;">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #0073aa, #005177); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <span class="<?php echo esc_attr($extension['icon']); ?>" style="color: #fff; font-size: 24px;"></span>
                                    </div>
                                    <div>
                                        <h3 style="margin: 0; font-size: 16px;"><?php echo esc_html($extension['name']); ?></h3>
                                        <span style="color: #666; font-size: 12px;"><?php echo esc_html($extension['author']); ?></span>
                                    </div>
                                </div>
                                <?php if ($extension['price'] === 'premium') : ?>
                                    <span style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold;">
                                        PRO
                                    </span>
                                <?php else : ?>
                                    <span style="background: #46b450; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold;">
                                        FREE
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p style="color: #555; font-size: 13px; line-height: 1.5; margin-bottom: 15px;">
                                <?php echo esc_html($extension['description']); ?>
                            </p>

                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #666;">
                                <div style="display: flex; gap: 15px;">
                                    <span title="<?php esc_attr_e('Downloads', 'formflow-pro'); ?>">
                                        <span class="dashicons dashicons-download" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                        <?php echo number_format_i18n($extension['downloads']); ?>
                                    </span>
                                    <span title="<?php esc_attr_e('Rating', 'formflow-pro'); ?>">
                                        <span class="dashicons dashicons-star-filled" style="font-size: 14px; width: 14px; height: 14px; color: #f0ad4e;"></span>
                                        <?php echo esc_html($extension['rating']); ?>
                                    </span>
                                </div>
                                <span>v<?php echo esc_html($extension['version']); ?></span>
                            </div>
                        </div>

                        <div style="padding: 15px 20px; background: #f8f9fa; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <?php if ($is_installed) : ?>
                                <span style="color: #46b450; font-weight: 500;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 16px;"></span>
                                    <?php esc_html_e('Installed', 'formflow-pro'); ?>
                                </span>
                                <button type="button" class="button" disabled>
                                    <?php esc_html_e('Active', 'formflow-pro'); ?>
                                </button>
                            <?php else : ?>
                                <a href="#" class="button button-secondary">
                                    <?php esc_html_e('Learn More', 'formflow-pro'); ?>
                                </a>
                                <button type="button" class="button button-primary install-extension" data-extension="<?php echo esc_attr($extension['id']); ?>">
                                    <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                                    <?php esc_html_e('Install', 'formflow-pro'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php elseif ($current_view === 'installed') : ?>
        <!-- Installed Extensions View -->
        <div class="installed-extensions" style="margin-top: 20px;">
            <?php if (empty($installed_extensions)) : ?>
                <div class="card" style="padding: 40px; text-align: center;">
                    <span class="dashicons dashicons-admin-plugins" style="font-size: 48px; width: 48px; height: 48px; color: #ccc;"></span>
                    <h3><?php esc_html_e('No extensions installed yet', 'formflow-pro'); ?></h3>
                    <p style="color: #666;"><?php esc_html_e('Browse our marketplace to find extensions that enhance your forms.', 'formflow-pro'); ?></p>
                    <a href="?page=formflow-marketplace&view=browse" class="button button-primary">
                        <?php esc_html_e('Browse Extensions', 'formflow-pro'); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Extension', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Version', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($installed_extensions as $ext_id) :
                            $ext = array_filter($available_extensions, fn($e) => $e['id'] === $ext_id);
                            $ext = reset($ext);
                            if (!$ext) continue;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($ext['name']); ?></strong>
                                    <br><span style="color: #666; font-size: 12px;"><?php echo esc_html($ext['description']); ?></span>
                                </td>
                                <td><?php echo esc_html($ext['version']); ?></td>
                                <td>
                                    <span style="color: #46b450;">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e('Active', 'formflow-pro'); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small"><?php esc_html_e('Settings', 'formflow-pro'); ?></button>
                                    <button type="button" class="button button-small" style="color: #dc3545;"><?php esc_html_e('Deactivate', 'formflow-pro'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($current_view === 'developer') : ?>
        <!-- Developer SDK View -->
        <div class="developer-sdk" style="margin-top: 20px;">
            <div class="card" style="padding: 30px;">
                <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-editor-code" style="color: #0073aa;"></span>
                    <?php esc_html_e('Developer SDK', 'formflow-pro'); ?>
                </h2>

                <p style="font-size: 15px; color: #555; max-width: 800px;">
                    <?php esc_html_e('Build custom extensions for FormFlow Pro using our powerful SDK. Create integrations, custom field types, automation actions, and more.', 'formflow-pro'); ?>
                </p>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px;">
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <span class="dashicons dashicons-book" style="font-size: 32px; color: #0073aa;"></span>
                        <h3><?php esc_html_e('Documentation', 'formflow-pro'); ?></h3>
                        <p style="color: #666; font-size: 13px;"><?php esc_html_e('Comprehensive guides and API reference for extension development.', 'formflow-pro'); ?></p>
                        <a href="#" class="button"><?php esc_html_e('View Docs', 'formflow-pro'); ?></a>
                    </div>

                    <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <span class="dashicons dashicons-editor-code" style="font-size: 32px; color: #9b59b6;"></span>
                        <h3><?php esc_html_e('Boilerplate', 'formflow-pro'); ?></h3>
                        <p style="color: #666; font-size: 13px;"><?php esc_html_e('Download a starter template to begin developing your extension.', 'formflow-pro'); ?></p>
                        <a href="#" class="button"><?php esc_html_e('Download', 'formflow-pro'); ?></a>
                    </div>

                    <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <span class="dashicons dashicons-groups" style="font-size: 32px; color: #27ae60;"></span>
                        <h3><?php esc_html_e('Community', 'formflow-pro'); ?></h3>
                        <p style="color: #666; font-size: 13px;"><?php esc_html_e('Join our developer community for support and collaboration.', 'formflow-pro'); ?></p>
                        <a href="#" class="button"><?php esc_html_e('Join', 'formflow-pro'); ?></a>
                    </div>
                </div>

                <div style="margin-top: 30px; padding: 20px; background: #23282d; border-radius: 8px; color: #fff;">
                    <h4 style="margin-top: 0; color: #0073aa;"><?php esc_html_e('Quick Start Example', 'formflow-pro'); ?></h4>
                    <pre style="margin: 0; font-family: monospace; font-size: 13px; line-height: 1.6; overflow-x: auto;"><code>&lt;?php
/**
 * Plugin Name: My FormFlow Extension
 * Description: Custom extension for FormFlow Pro
 * Version: 1.0.0
 */

use FormFlowPro\Marketplace\ExtensionManager;

// Register extension
add_action('formflow_extensions_loaded', function() {
    ExtensionManager::getInstance()->register([
        'id'          => 'my-extension',
        'name'        => 'My Extension',
        'version'     => '1.0.0',
        'description' => 'My custom extension',
        'init'        => function() {
            // Your extension code here
        }
    ]);
});
</code></pre>
                </div>

                <div style="margin-top: 30px;">
                    <h3><?php esc_html_e('Available Hooks', 'formflow-pro'); ?></h3>
                    <table class="widefat" style="max-width: 800px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Hook', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>formflow_form_submitted</code></td>
                                <td><?php esc_html_e('Triggered after form submission', 'formflow-pro'); ?></td>
                            </tr>
                            <tr>
                                <td><code>formflow_before_render_form</code></td>
                                <td><?php esc_html_e('Before form is rendered', 'formflow-pro'); ?></td>
                            </tr>
                            <tr>
                                <td><code>formflow_after_render_form</code></td>
                                <td><?php esc_html_e('After form is rendered', 'formflow-pro'); ?></td>
                            </tr>
                            <tr>
                                <td><code>formflow_validate_submission</code></td>
                                <td><?php esc_html_e('Custom validation logic', 'formflow-pro'); ?></td>
                            </tr>
                            <tr>
                                <td><code>formflow_signature_status_updated</code></td>
                                <td><?php esc_html_e('Signature status changed', 'formflow-pro'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Extension search
    $('#extension-search').on('keyup', function() {
        var search = $(this).val().toLowerCase();
        $('.extension-card').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(search) > -1);
        });
    });

    // Install extension
    $('.install-extension').on('click', function() {
        var $btn = $(this);
        var extensionId = $btn.data('extension');

        $btn.prop('disabled', true).text('<?php esc_html_e('Installing...', 'formflow-pro'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'formflow_install_extension',
                nonce: '<?php echo wp_create_nonce('formflow_marketplace'); ?>',
                extension_id: extensionId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data?.message || '<?php esc_html_e('Installation failed', 'formflow-pro'); ?>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 4px;"></span> <?php esc_html_e('Install', 'formflow-pro'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Installation failed', 'formflow-pro'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 4px;"></span> <?php esc_html_e('Install', 'formflow-pro'); ?>');
            }
        });
    });
});
</script>
