<?php
/**
 * Membership Import Modal
 *
 * Modal for importing membership data from legacy CSV.
 *
 * @package    EauSystem
 * @subpackage Admin
 * @since      1.55.0
 */

if (!defined('WPINC')) {
    die;
}
?>

<!-- Membership Import Modal -->
<div id="eau-import-membership-modal" class="eau-modal">
    <div class="eau-modal-overlay"></div>
    <div class="eau-modal-container eau-modal-lg">
        <div class="eau-modal-header">
            <h2 class="eau-modal-title">
                <i data-lucide="upload"></i>
                <?php esc_html_e('Import Membership Data', 'eau-system'); ?>
            </h2>
            <button type="button" class="eau-modal-close eau-modal-close-membership">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="eau-modal-body">
            <!-- Step 1: Upload -->
            <div id="eau-import-membership-step-1" class="eau-import-step">
                <h3><?php esc_html_e('Step 1: Upload CSV File', 'eau-system'); ?></h3>
                <p class="eau-description">
                    <?php esc_html_e('Upload the membership CSV file exported from the legacy system. This will update existing members with their membership data, or create new users if the email is not found.', 'eau-system'); ?>
                </p>

                <div class="eau-warning-box" style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                    <strong><?php esc_html_e('Important:', 'eau-system'); ?></strong>
                    <?php esc_html_e('Please backup your database before proceeding with the import.', 'eau-system'); ?>
                </div>

                <form id="eau-import-membership-upload-form" enctype="multipart/form-data">
                    <div class="eau-form-group">
                        <label for="membership_csv_file"><?php esc_html_e('CSV File', 'eau-system'); ?></label>
                        <input type="file" name="membership_csv_file" id="membership_csv_file" accept=".csv" required>
                        <p class="description"><?php esc_html_e('Maximum file size: 10MB', 'eau-system'); ?></p>
                    </div>
                    <div class="eau-form-actions">
                        <button type="submit" class="eau-btn eau-btn-primary">
                            <i data-lucide="search"></i>
                            <?php esc_html_e('Analyze CSV', 'eau-system'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 2: Preview -->
            <div id="eau-import-membership-step-2" class="eau-import-step" style="display: none;">
                <h3><?php esc_html_e('Step 2: Preview', 'eau-system'); ?></h3>

                <div id="eau-membership-preview-stats" class="eau-preview-stats">
                    <!-- Stats will be loaded here -->
                </div>

                <div id="eau-membership-preview-table-container">
                    <h4><?php esc_html_e('Preview (first 10 rows):', 'eau-system'); ?></h4>
                    <div class="eau-table-wrapper">
                        <table class="widefat striped" id="eau-membership-preview-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Email', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('Name', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('Type', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('Status', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('Expiry', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('Action', 'eau-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Preview rows will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="eau-form-actions">
                    <button type="button" class="eau-btn eau-btn-secondary" id="eau-membership-back-to-step1">
                        <i data-lucide="arrow-left"></i>
                        <?php esc_html_e('Back', 'eau-system'); ?>
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-membership-start-import">
                        <i data-lucide="upload"></i>
                        <?php esc_html_e('Start Import', 'eau-system'); ?>
                    </button>
                </div>
            </div>

            <!-- Step 3: Progress -->
            <div id="eau-import-membership-step-3" class="eau-import-step" style="display: none;">
                <h3><?php esc_html_e('Step 3: Importing...', 'eau-system'); ?></h3>

                <div class="eau-progress-container">
                    <div class="eau-progress-bar">
                        <div class="eau-progress-fill" id="eau-membership-progress-fill" style="width: 0%;"></div>
                    </div>
                    <p class="eau-progress-text" id="eau-membership-progress-text">
                        <?php esc_html_e('Preparing...', 'eau-system'); ?>
                    </p>
                </div>

                <div id="eau-membership-import-log" class="eau-import-log">
                    <!-- Log entries will be appended here -->
                </div>
            </div>

            <!-- Step 4: Summary -->
            <div id="eau-import-membership-step-4" class="eau-import-step" style="display: none;">
                <h3><?php esc_html_e('Import Complete!', 'eau-system'); ?></h3>

                <div id="eau-membership-import-summary" class="eau-import-summary">
                    <!-- Summary will be loaded here -->
                </div>

                <div class="eau-form-actions">
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-membership-close-modal">
                        <i data-lucide="check"></i>
                        <?php esc_html_e('Close', 'eau-system'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Membership Import Modal Styles */
#eau-import-membership-modal .eau-modal-container {
    max-width: 800px;
}

#eau-import-membership-modal .eau-modal-container.eau-modal-lg {
    width: 90%;
    max-width: 900px;
}

#eau-import-membership-modal .eau-description {
    color: #666;
    margin-bottom: 15px;
}

#eau-import-membership-modal .eau-form-group {
    margin-bottom: 20px;
}

#eau-import-membership-modal .eau-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

#eau-import-membership-modal .eau-form-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Preview Stats */
#eau-import-membership-modal .eau-preview-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

#eau-import-membership-modal .eau-stat-box {
    flex: 1;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}

#eau-import-membership-modal .eau-stat-box.total {
    background: #f0f6fc;
    border: 1px solid #0073aa;
}

#eau-import-membership-modal .eau-stat-box.update {
    background: #f0fff4;
    border: 1px solid #00a32a;
}

#eau-import-membership-modal .eau-stat-box.create {
    background: #fff8e5;
    border: 1px solid #dba617;
}

#eau-import-membership-modal .eau-stat-number {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}

#eau-import-membership-modal .eau-stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

/* Preview Table */
#eau-import-membership-modal .eau-table-wrapper {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
}

#eau-import-membership-modal .eau-table-wrapper table {
    margin: 0;
}

#eau-import-membership-modal .eau-action-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

#eau-import-membership-modal .eau-action-badge.update {
    background: #d4edda;
    color: #155724;
}

#eau-import-membership-modal .eau-action-badge.create {
    background: #fff3cd;
    color: #856404;
}

/* Progress */
#eau-import-membership-modal .eau-progress-container {
    margin-bottom: 20px;
}

#eau-import-membership-modal .eau-progress-bar {
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}

#eau-import-membership-modal .eau-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #00a0d2);
    transition: width 0.3s ease;
}

#eau-import-membership-modal .eau-progress-text {
    text-align: center;
    margin-top: 10px;
    font-size: 14px;
    color: #666;
}

/* Import Log */
#eau-import-membership-modal .eau-import-log {
    max-height: 250px;
    overflow-y: auto;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    font-family: monospace;
    font-size: 12px;
}

#eau-import-membership-modal .eau-import-log p {
    margin: 5px 0;
    padding: 3px 0;
    border-bottom: 1px solid #eee;
}

#eau-import-membership-modal .eau-import-log p:last-child {
    border-bottom: none;
}

#eau-import-membership-modal .eau-log-success {
    color: #155724;
}

#eau-import-membership-modal .eau-log-warning {
    color: #856404;
}

#eau-import-membership-modal .eau-log-error {
    color: #721c24;
}

#eau-import-membership-modal .eau-log-info {
    color: #0c5460;
}

/* Summary */
#eau-import-membership-modal .eau-import-summary {
    background: #f0fff4;
    border: 1px solid #00a32a;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

#eau-import-membership-modal .eau-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

#eau-import-membership-modal .eau-summary-item {
    text-align: center;
}

#eau-import-membership-modal .eau-summary-number {
    font-size: 24px;
    font-weight: 700;
}

#eau-import-membership-modal .eau-summary-label {
    font-size: 12px;
    color: #666;
}

#eau-import-membership-modal .eau-summary-number.updated {
    color: #00a32a;
}

#eau-import-membership-modal .eau-summary-number.created {
    color: #dba617;
}

#eau-import-membership-modal .eau-summary-number.skipped {
    color: #dc3545;
}
</style>
