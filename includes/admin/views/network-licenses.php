<?php
/**
 * Network License Management View
 *
 * @package FormFlowPro\Admin\Views
 * @since 2.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap formflow-network-licenses">
    <h1><?php esc_html_e('FormFlow Pro - License Management', 'formflow-pro'); ?></h1>

    <div class="license-status-card">
        <div class="license-icon">
            <?php if ($licenses['status'] === 'active'): ?>
                <span class="dashicons dashicons-yes-alt status-active"></span>
            <?php else: ?>
                <span class="dashicons dashicons-warning status-inactive"></span>
            <?php endif; ?>
        </div>
        <div class="license-info">
            <h2>
                <?php if ($licenses['status'] === 'active'): ?>
                    <?php esc_html_e('License Active', 'formflow-pro'); ?>
                <?php else: ?>
                    <?php esc_html_e('No License', 'formflow-pro'); ?>
                <?php endif; ?>
            </h2>
            <p class="license-type">
                <?php
                printf(
                    /* translators: %s: license type */
                    esc_html__('Type: %s', 'formflow-pro'),
                    esc_html(ucfirst($licenses['type']))
                );
                ?>
            </p>
        </div>
    </div>

    <div class="license-details">
        <div class="detail-card">
            <span class="detail-label"><?php esc_html_e('Active Sites', 'formflow-pro'); ?></span>
            <span class="detail-value">
                <?php echo esc_html($licenses['active_sites']); ?>
                <?php if ($licenses['max_sites'] > 0): ?>
                    / <?php echo esc_html($licenses['max_sites']); ?>
                <?php else: ?>
                    <small>(<?php esc_html_e('Unlimited', 'formflow-pro'); ?>)</small>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <form method="post" action="" id="license-form" class="license-form">
        <?php wp_nonce_field('formflow_license', 'formflow_license_nonce'); ?>

        <h2><?php esc_html_e('License Key', 'formflow-pro'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="license_key"><?php esc_html_e('Network License Key', 'formflow-pro'); ?></label>
                </th>
                <td>
                    <input type="text" name="license_key" id="license_key"
                           value="<?php echo esc_attr($licenses['key'] ? '••••••••' . substr($licenses['key'], -4) : ''); ?>"
                           class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX">
                    <p class="description">
                        <?php esc_html_e('Enter your FormFlow Pro network license key.', 'formflow-pro'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="max_sites"><?php esc_html_e('Maximum Sites', 'formflow-pro'); ?></label>
                </th>
                <td>
                    <input type="number" name="max_sites" id="max_sites"
                           value="<?php echo esc_attr($licenses['max_sites']); ?>"
                           class="small-text" min="0">
                    <p class="description">
                        <?php esc_html_e('0 = Unlimited sites', 'formflow-pro'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="activate_license" class="button button-primary">
                <?php esc_html_e('Save License', 'formflow-pro'); ?>
            </button>
            <?php if ($licenses['status'] === 'active'): ?>
                <button type="submit" name="deactivate_license" class="button">
                    <?php esc_html_e('Deactivate License', 'formflow-pro'); ?>
                </button>
            <?php endif; ?>
        </p>
    </form>

    <div class="license-help">
        <h3><?php esc_html_e('Need a License?', 'formflow-pro'); ?></h3>
        <p>
            <?php esc_html_e('Network licenses allow you to use FormFlow Pro across your entire WordPress multisite network.', 'formflow-pro'); ?>
        </p>
        <a href="https://formflowpro.com/pricing" target="_blank" class="button">
            <?php esc_html_e('View Pricing', 'formflow-pro'); ?>
        </a>
    </div>
</div>

<style>
.formflow-network-licenses .license-status-card {
    display: flex;
    align-items: center;
    gap: 20px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 30px;
    margin: 20px 0;
}

.formflow-network-licenses .license-icon .dashicons {
    font-size: 60px;
    width: 60px;
    height: 60px;
}

.formflow-network-licenses .status-active {
    color: #46b450;
}

.formflow-network-licenses .status-inactive {
    color: #dc3232;
}

.formflow-network-licenses .license-info h2 {
    margin: 0 0 5px 0;
}

.formflow-network-licenses .license-type {
    margin: 0;
    color: #666;
}

.formflow-network-licenses .license-details {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.formflow-network-licenses .detail-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px 30px;
}

.formflow-network-licenses .detail-label {
    display: block;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.formflow-network-licenses .detail-value {
    display: block;
    font-size: 24px;
    font-weight: 600;
    margin-top: 5px;
}

.formflow-network-licenses .license-form,
.formflow-network-licenses .license-help {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    max-width: 600px;
}

.formflow-network-licenses .license-form h2,
.formflow-network-licenses .license-help h3 {
    margin-top: 0;
}
</style>
