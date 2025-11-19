<?php

/**
 * Autentique Documents Page
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle resend signature link
if (isset($_GET['action']) && $_GET['action'] === 'resend' && isset($_GET['document_id'])) {
    $document_id = sanitize_text_field($_GET['document_id']);
    if (check_admin_referer('resend_document_' . $document_id)) {
        try {
            require_once FORMFLOW_PATH . 'includes/autentique/class-autentique-service.php';
            $autentique = new \FormFlowPro\Autentique\Autentique_Service();

            // Get document details
            $doc = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}formflow_autentique_documents WHERE document_id = %s",
                $document_id
            ));

            if ($doc) {
                // Resend via Autentique webhook or notification
                $result = $autentique->resend_signature_link($document_id);

                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                         esc_html__('Signature link resent successfully.', 'formflow-pro') .
                         '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' .
                         esc_html__('Failed to resend signature link.', 'formflow-pro') .
                         '</p></div>';
                }
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible"><p>' .
                 esc_html($e->getMessage()) .
                 '</p></div>';
        }
    }
}

// Handle delete document
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['document_id'])) {
    $document_id = sanitize_text_field($_GET['document_id']);
    if (check_admin_referer('delete_document_' . $document_id)) {
        $wpdb->delete(
            $wpdb->prefix . 'formflow_autentique_documents',
            ['document_id' => $document_id],
            ['%s']
        );
        echo '<div class="notice notice-success is-dismissible"><p>' .
             esc_html__('Document deleted successfully.', 'formflow-pro') .
             '</p></div>';
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Check if API is configured
$api_key = get_option('autentique_api_key', '');
$is_configured = !empty($api_key);

?>

<div class="wrap formflow-admin formflow-autentique">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Autentique Documents', 'formflow-pro'); ?>
    </h1>

    <?php if (!$is_configured) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Autentique not configured!', 'formflow-pro'); ?></strong>
                <?php esc_html_e('Please configure your Autentique API key in settings to start using digital signatures.', 'formflow-pro'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-settings')); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php esc_html_e('Go to Settings', 'formflow-pro'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Stats Cards -->
    <div class="formflow-stats-cards">
        <?php
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents");
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents WHERE status = 'pending'");
        $signed = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents WHERE status = 'signed'");
        $refused = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents WHERE status = 'refused'");
        ?>

        <div class="formflow-stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-media-document"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo esc_html(number_format_i18n($total)); ?></div>
                <div class="stat-label"><?php esc_html_e('Total Documents', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="formflow-stat-card status-pending">
            <div class="stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo esc_html(number_format_i18n($pending)); ?></div>
                <div class="stat-label"><?php esc_html_e('Pending Signature', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="formflow-stat-card status-signed">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo esc_html(number_format_i18n($signed)); ?></div>
                <div class="stat-label"><?php esc_html_e('Signed', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="formflow-stat-card status-refused">
            <div class="stat-icon">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo esc_html(number_format_i18n($refused)); ?></div>
                <div class="stat-label"><?php esc_html_e('Refused', 'formflow-pro'); ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="status_filter" id="status-filter" class="formflow-filter">
                <option value=""><?php esc_html_e('All Statuses', 'formflow-pro'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Pending', 'formflow-pro'); ?></option>
                <option value="signed" <?php selected($status_filter, 'signed'); ?>><?php esc_html_e('Signed', 'formflow-pro'); ?></option>
                <option value="refused" <?php selected($status_filter, 'refused'); ?>><?php esc_html_e('Refused', 'formflow-pro'); ?></option>
            </select>

            <input type="text"
                   id="date-from"
                   class="formflow-datepicker"
                   placeholder="<?php esc_attr_e('From date', 'formflow-pro'); ?>">

            <input type="text"
                   id="date-to"
                   class="formflow-datepicker"
                   placeholder="<?php esc_attr_e('To date', 'formflow-pro'); ?>">

            <button type="button" id="apply-filters" class="button">
                <?php esc_html_e('Apply Filters', 'formflow-pro'); ?>
            </button>

            <button type="button" id="reset-filters" class="button">
                <?php esc_html_e('Reset', 'formflow-pro'); ?>
            </button>
        </div>
    </div>

    <!-- Documents Table -->
    <div class="card" style="margin-top: 20px;">
        <table id="autentique-table" class="display wp-list-table widefat fixed striped" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e('Document ID', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Document Name', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Submission', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Signer', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Created', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Signed At', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- DataTables will populate this -->
            </tbody>
            <tfoot>
                <tr>
                    <th><?php esc_html_e('Document ID', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Document Name', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Submission', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Signer', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Created', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Signed At', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- View Document Modal -->
<div id="view-document-modal" class="formflow-modal" style="display: none;">
    <div class="formflow-modal-overlay"></div>
    <div class="formflow-modal-content">
        <div class="formflow-modal-header">
            <h2><?php esc_html_e('Document Details', 'formflow-pro'); ?></h2>
            <button type="button" class="formflow-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>

        <div class="formflow-modal-body" id="document-details-container">
            <div class="loading-spinner">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading...', 'formflow-pro'); ?>
            </div>
        </div>

        <div class="formflow-modal-footer">
            <button type="button" class="button" id="close-document-modal">
                <?php esc_html_e('Close', 'formflow-pro'); ?>
            </button>
            <a href="#" target="_blank" class="button button-primary" id="open-autentique">
                <span class="dashicons dashicons-external"></span>
                <?php esc_html_e('Open in Autentique', 'formflow-pro'); ?>
            </a>
        </div>
    </div>
</div>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<!-- jQuery UI for Datepicker -->
<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
