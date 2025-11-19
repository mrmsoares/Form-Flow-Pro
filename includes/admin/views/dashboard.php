<?php
/**
 * Provide a admin area view for the plugin dashboard
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap formflow-dashboard">
    <h1 class="ff-heading-1">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <div class="ff-container">
        <!-- Welcome Card -->
        <div class="ff-card" style="margin-bottom: 2rem;">
            <div class="ff-card-header">
                <h2 class="ff-card-title">
                    <?php esc_html_e('Welcome to FormFlow Pro Enterprise', 'formflow-pro'); ?>
                </h2>
                <p class="ff-card-description">
                    <?php esc_html_e('High-performance form processing with Autentique integration', 'formflow-pro'); ?>
                </p>
            </div>
            <div class="ff-card-body">
                <p>
                    <?php esc_html_e('Thank you for installing FormFlow Pro! This plugin is currently in development (Phase 2 - Foundation).', 'formflow-pro'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Current Status:', 'formflow-pro'); ?></strong>
                    <?php esc_html_e('Plugin skeleton created, database migrations pending.', 'formflow-pro'); ?>
                </p>
            </div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="ff-grid">
            <div class="ff-col-span-12 ff-col-md-6 ff-col-lg-3">
                <div class="ff-card">
                    <div class="ff-card-body">
                        <div class="ff-stat">
                            <span class="ff-stat-label"><?php esc_html_e('Total Submissions', 'formflow-pro'); ?></span>
                            <span class="ff-stat-value">0</span>
                            <span class="ff-stat-change ff-stat-change--neutral">
                                <?php esc_html_e('Getting started', 'formflow-pro'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ff-col-span-12 ff-col-md-6 ff-col-lg-3">
                <div class="ff-card">
                    <div class="ff-card-body">
                        <div class="ff-stat">
                            <span class="ff-stat-label"><?php esc_html_e('PDFs Generated', 'formflow-pro'); ?></span>
                            <span class="ff-stat-value">0</span>
                            <span class="ff-stat-change ff-stat-change--neutral">
                                <?php esc_html_e('Ready to start', 'formflow-pro'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ff-col-span-12 ff-col-md-6 ff-col-lg-3">
                <div class="ff-card">
                    <div class="ff-card-body">
                        <div class="ff-stat">
                            <span class="ff-stat-label"><?php esc_html_e('Autentique Docs', 'formflow-pro'); ?></span>
                            <span class="ff-stat-value">0</span>
                            <span class="ff-stat-change ff-stat-change--neutral">
                                <?php esc_html_e('Configure API key', 'formflow-pro'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ff-col-span-12 ff-col-md-6 ff-col-lg-3">
                <div class="ff-card">
                    <div class="ff-card-body">
                        <div class="ff-stat">
                            <span class="ff-stat-label"><?php esc_html_e('Success Rate', 'formflow-pro'); ?></span>
                            <span class="ff-stat-value">--%</span>
                            <span class="ff-stat-change ff-stat-change--neutral">
                                <?php esc_html_e('No data yet', 'formflow-pro'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Getting Started -->
        <div class="ff-card" style="margin-top: 2rem;">
            <div class="ff-card-header">
                <h3 class="ff-card-title">
                    <?php esc_html_e('Getting Started', 'formflow-pro'); ?>
                </h3>
            </div>
            <div class="ff-card-body">
                <ol style="margin-left: 1.5rem;">
                    <li><?php esc_html_e('Configure your Autentique API key in Settings', 'formflow-pro'); ?></li>
                    <li><?php esc_html_e('Create a form in Elementor Pro', 'formflow-pro'); ?></li>
                    <li><?php esc_html_e('Configure form mapping and PDF template', 'formflow-pro'); ?></li>
                    <li><?php esc_html_e('Test with a sample submission', 'formflow-pro'); ?></li>
                    <li><?php esc_html_e('Monitor submissions in the Submissions page', 'formflow-pro'); ?></li>
                </ol>
            </div>
            <div class="ff-card-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-settings')); ?>" class="button button-primary">
                    <?php esc_html_e('Go to Settings', 'formflow-pro'); ?>
                </a>
                <a href="https://docs.formflowpro.com" class="button button-secondary" target="_blank">
                    <?php esc_html_e('View Documentation', 'formflow-pro'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
