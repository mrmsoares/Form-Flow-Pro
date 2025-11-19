<?php

/**
 * Submissions List Page
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle bulk actions
if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['submission_ids'])) {
    if (check_admin_referer('bulk_delete_submissions')) {
        $submission_ids = array_map('intval', $_POST['submission_ids']);
        $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}formflow_submissions WHERE id IN ($placeholders)",
            $submission_ids
        ));

        echo '<div class="notice notice-success is-dismissible"><p>' .
             sprintf(esc_html__('%d submissions deleted successfully.', 'formflow-pro'), count($submission_ids)) .
             '</p></div>';
    }
}

// Handle single delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['submission_id'])) {
    $submission_id = intval($_GET['submission_id']);
    if (check_admin_referer('delete_submission_' . $submission_id)) {
        $wpdb->delete(
            $wpdb->prefix . 'formflow_submissions',
            ['id' => $submission_id],
            ['%d']
        );
        echo '<div class="notice notice-success is-dismissible"><p>' .
             esc_html__('Submission deleted successfully.', 'formflow-pro') .
             '</p></div>';
    }
}

// Get filter parameters
$form_filter = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Get all forms for filter dropdown
$forms = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}formflow_forms ORDER BY name ASC");

// Get all unique statuses
$statuses = $wpdb->get_col("SELECT DISTINCT status FROM {$wpdb->prefix}formflow_submissions ORDER BY status ASC");

?>

<div class="wrap formflow-admin formflow-submissions">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Submissions', 'formflow-pro'); ?>
    </h1>

    <a href="#" class="page-title-action" id="export-submissions">
        <span class="dashicons dashicons-download"></span>
        <?php esc_html_e('Export', 'formflow-pro'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="form_filter" id="form-filter" class="formflow-filter">
                <option value=""><?php esc_html_e('All Forms', 'formflow-pro'); ?></option>
                <?php foreach ($forms as $form) : ?>
                    <option value="<?php echo esc_attr($form->id); ?>" <?php selected($form_filter, $form->id); ?>>
                        <?php echo esc_html($form->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status_filter" id="status-filter" class="formflow-filter">
                <option value=""><?php esc_html_e('All Statuses', 'formflow-pro'); ?></option>
                <?php foreach ($statuses as $status) : ?>
                    <option value="<?php echo esc_attr($status); ?>" <?php selected($status_filter, $status); ?>>
                        <?php echo esc_html(ucfirst($status)); ?>
                    </option>
                <?php endforeach; ?>
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

    <!-- Submissions Table -->
    <div class="card" style="margin-top: 20px;">
        <table id="submissions-table" class="display wp-list-table widefat fixed striped" style="width:100%">
            <thead>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" id="cb-select-all-submissions">
                    </th>
                    <th><?php esc_html_e('ID', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Form', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Signature Status', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('IP Address', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Created', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- DataTables will populate this -->
            </tbody>
            <tfoot>
                <tr>
                    <th></th>
                    <th><?php esc_html_e('ID', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Form', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Signature Status', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('IP Address', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Created', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Bulk Actions -->
    <div class="tablenav bottom">
        <div class="alignleft actions bulkactions">
            <select name="bulk_action" id="bulk-action-selector">
                <option value="-1"><?php esc_html_e('Bulk Actions', 'formflow-pro'); ?></option>
                <option value="delete"><?php esc_html_e('Delete', 'formflow-pro'); ?></option>
                <option value="export"><?php esc_html_e('Export Selected', 'formflow-pro'); ?></option>
            </select>
            <button type="button" id="apply-bulk-action" class="button">
                <?php esc_html_e('Apply', 'formflow-pro'); ?>
            </button>
        </div>
    </div>
</div>

<!-- View Submission Modal -->
<div id="view-submission-modal" class="formflow-modal" style="display: none;">
    <div class="formflow-modal-overlay"></div>
    <div class="formflow-modal-content">
        <div class="formflow-modal-header">
            <h2><?php esc_html_e('Submission Details', 'formflow-pro'); ?></h2>
            <button type="button" class="formflow-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>

        <div class="formflow-modal-body" id="submission-details-container">
            <div class="loading-spinner">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading...', 'formflow-pro'); ?>
            </div>
        </div>

        <div class="formflow-modal-footer">
            <button type="button" class="button" id="close-submission-modal">
                <?php esc_html_e('Close', 'formflow-pro'); ?>
            </button>
            <button type="button" class="button button-primary" id="export-submission">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export PDF', 'formflow-pro'); ?>
            </button>
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
