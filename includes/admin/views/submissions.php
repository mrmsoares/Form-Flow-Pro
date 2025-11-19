<?php

/**
 * Provide a admin area view for submissions
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap formflow-submissions">
    <h1 class="ff-heading-1">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <div class="ff-container">
        <div class="ff-card">
            <div class="ff-card-header">
                <h3 class="ff-card-title"><?php esc_html_e('All Submissions', 'formflow-pro'); ?></h3>
                <p class="ff-card-description"><?php esc_html_e('View and manage form submissions', 'formflow-pro'); ?></p>
            </div>
            <div class="ff-card-body">
                <p class="ff-body">
                    <?php esc_html_e('Submissions list will appear here once database migrations are complete.', 'formflow-pro'); ?>
                </p>
                <p class="ff-body-small" style="color: var(--ff-text-secondary);">
                    <?php esc_html_e('Phase 2.2: Database Migrations - In Progress', 'formflow-pro'); ?>
                </p>
            </div>
        </div>
    </div>
</div>
