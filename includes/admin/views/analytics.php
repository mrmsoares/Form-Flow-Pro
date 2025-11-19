<?php
/**
 * Provide a admin area view for analytics
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap formflow-analytics">
    <h1 class="ff-heading-1">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <div class="ff-container">
        <div class="ff-card">
            <div class="ff-card-header">
                <h3 class="ff-card-title"><?php esc_html_e('Analytics Dashboard', 'formflow-pro'); ?></h3>
                <p class="ff-card-description"><?php esc_html_e('Real-time metrics and insights', 'formflow-pro'); ?></p>
            </div>
            <div class="ff-card-body">
                <p class="ff-body">
                    <?php esc_html_e('Analytics dashboard coming soon in Phase 3.', 'formflow-pro'); ?>
                </p>
            </div>
        </div>
    </div>
</div>
