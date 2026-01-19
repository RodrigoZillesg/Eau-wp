<?php
/**
 * Institution Import Modal
 *
 * Modal for importing institution data from legacy CSV.
 *
 * @package    EauSystem
 * @subpackage Admin
 * @since      1.54.0
 */

if (!defined('WPINC')) {
    die;
}
?>

<!-- Institution Import Modal -->
<div id="eau-import-institution-modal" class="eau-modal">
    <div class="eau-modal-overlay"></div>
    <div class="eau-modal-container eau-modal-lg">
        <div class="eau-modal-header">
            <h2 class="eau-modal-title">
                <i data-lucide="building-2"></i>
                <?php esc_html_e('Import Institution Data', 'eau-system'); ?>
            </h2>
            <button type="button" class="eau-modal-close eau-modal-close-institution">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="eau-modal-body">
            <!-- Step 1: Upload -->
            <div id="eau-import-institution-step-1" class="eau-import-step">
                <h3><?php esc_html_e('Step 1: Upload CSV File', 'eau-system'); ?></h3>
                <p class="eau-description">
                    <?php esc_html_e('Upload the CSV file containing institution data. The system will extract unique institutions by Company ID, updating existing institutions or creating new ones.', 'eau-system'); ?>
                </p>

                <div class="eau-warning-box" style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                    <strong><?php esc_html_e('Important:', 'eau-system'); ?></strong>
                    <?php esc_html_e('Please backup your database before proceeding with the import.', 'eau-system'); ?>
                </div>

                <form id="eau-import-institution-upload-form" enctype="multipart/form-data">
                    <div class="eau-form-group">
                        <label for="institution_csv_file"><?php esc_html_e('CSV File', 'eau-system'); ?></label>
                        <input type="file" name="institution_csv_file" id="institution_csv_file" accept=".csv" required>
                        <p class="description"><?php esc_html_e('Use the same CSV file from membership import (contains Company data)', 'eau-system'); ?></p>
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
            <div id="eau-import-institution-step-2" class="eau-import-step" style="display: none;">
                <h3><?php esc_html_e('Step 2: Preview', 'eau-system'); ?></h3>

                <!-- Format Detection Badge -->
                <div id="eau-institution-format-info" class="eau-format-info" style="margin-bottom: 15px;">
                    <!-- Format info will be loaded here -->
                </div>

                <div id="eau-institution-preview-stats" class="eau-preview-stats">
                    <!-- Stats will be loaded here -->
                </div>

                <!-- Sync Delete Option (only for membership format) -->
                <div id="eau-institution-sync-option" class="eau-sync-option" style="display: none; margin-bottom: 15px; padding: 12px; background: #fff8e5; border: 1px solid #dba617; border-radius: 4px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="eau-institution-sync-delete" value="1">
                        <span>
                            <strong><?php esc_html_e('Full Sync:', 'eau-system'); ?></strong>
                            <?php esc_html_e('Delete institutions from database that are not in the CSV', 'eau-system'); ?>
                            <span id="eau-institution-delete-count" style="color: #dc3545; font-weight: 600;"></span>
                        </span>
                    </label>
                    <p style="margin: 8px 0 0 26px; font-size: 12px; color: #666;">
                        <?php esc_html_e('Enable this to keep your database in sync with the CSV. Institutions not present in the CSV will be permanently deleted.', 'eau-system'); ?>
                    </p>
                </div>

                <div id="eau-institution-preview-table-container">
                    <h4><?php esc_html_e('Preview (first 10 institutions):', 'eau-system'); ?></h4>
                    <div class="eau-table-wrapper">
                        <!-- Table will be dynamically generated based on format -->
                        <table class="widefat striped" id="eau-institution-preview-table">
                            <thead id="eau-institution-preview-thead">
                                <tr>
                                    <th><?php esc_html_e('Company ID', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('Company Name', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('Email', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('Type', 'eau-system'); ?></th>
                                    <th><?php esc_html_e('State', 'eau-system'); ?></th>
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
                    <button type="button" class="eau-btn eau-btn-secondary" id="eau-institution-back-to-step1">
                        <i data-lucide="arrow-left"></i>
                        <?php esc_html_e('Back', 'eau-system'); ?>
                    </button>
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-institution-start-import">
                        <i data-lucide="upload"></i>
                        <?php esc_html_e('Start Import', 'eau-system'); ?>
                    </button>
                </div>
            </div>

            <!-- Step 3: Progress -->
            <div id="eau-import-institution-step-3" class="eau-import-step" style="display: none;">
                <h3><?php esc_html_e('Step 3: Importing...', 'eau-system'); ?></h3>

                <div class="eau-progress-container">
                    <div class="eau-progress-bar">
                        <div class="eau-progress-fill" id="eau-institution-progress-fill" style="width: 0%;"></div>
                    </div>
                    <p class="eau-progress-text" id="eau-institution-progress-text">
                        <?php esc_html_e('Preparing...', 'eau-system'); ?>
                    </p>
                </div>

                <div id="eau-institution-import-log" class="eau-import-log">
                    <!-- Log entries will be appended here -->
                </div>
            </div>

            <!-- Step 4: Summary -->
            <div id="eau-import-institution-step-4" class="eau-import-step" style="display: none;">
                <h3><?php esc_html_e('Import Complete!', 'eau-system'); ?></h3>

                <div id="eau-institution-import-summary" class="eau-import-summary">
                    <!-- Summary will be loaded here -->
                </div>

                <div class="eau-form-actions">
                    <button type="button" class="eau-btn eau-btn-primary" id="eau-institution-close-modal">
                        <i data-lucide="check"></i>
                        <?php esc_html_e('Close', 'eau-system'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Institution Import Modal Styles */
#eau-import-institution-modal .eau-modal-container {
    max-width: 800px;
}

#eau-import-institution-modal .eau-modal-container.eau-modal-lg {
    width: 90%;
    max-width: 900px;
}

#eau-import-institution-modal .eau-description {
    color: #666;
    margin-bottom: 15px;
}

#eau-import-institution-modal .eau-form-group {
    margin-bottom: 20px;
}

#eau-import-institution-modal .eau-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

#eau-import-institution-modal .eau-form-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Preview Stats */
#eau-import-institution-modal .eau-preview-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

#eau-import-institution-modal .eau-stat-box {
    flex: 1;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}

#eau-import-institution-modal .eau-stat-box.total {
    background: #f0f6fc;
    border: 1px solid #0073aa;
}

#eau-import-institution-modal .eau-stat-box.update {
    background: #f0fff4;
    border: 1px solid #00a32a;
}

#eau-import-institution-modal .eau-stat-box.create {
    background: #fff8e5;
    border: 1px solid #dba617;
}

#eau-import-institution-modal .eau-stat-box.delete {
    background: #fef2f2;
    border: 1px solid #dc3545;
}

/* Format Info Badge */
#eau-import-institution-modal .eau-format-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

#eau-import-institution-modal .eau-format-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
}

#eau-import-institution-modal .eau-format-badge.legacy {
    background: #e8f4fd;
    color: #0073aa;
    border: 1px solid #0073aa;
}

#eau-import-institution-modal .eau-format-badge.membership {
    background: #f0fff4;
    color: #00a32a;
    border: 1px solid #00a32a;
}

#eau-import-institution-modal .eau-stat-number {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}

#eau-import-institution-modal .eau-stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

/* Preview Table */
#eau-import-institution-modal .eau-table-wrapper {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
}

#eau-import-institution-modal .eau-table-wrapper table {
    margin: 0;
}

#eau-import-institution-modal .eau-action-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

#eau-import-institution-modal .eau-action-badge.update {
    background: #d4edda;
    color: #155724;
}

#eau-import-institution-modal .eau-action-badge.create {
    background: #fff3cd;
    color: #856404;
}

/* Progress */
#eau-import-institution-modal .eau-progress-container {
    margin-bottom: 20px;
}

#eau-import-institution-modal .eau-progress-bar {
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}

#eau-import-institution-modal .eau-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #00a0d2);
    transition: width 0.3s ease;
}

#eau-import-institution-modal .eau-progress-text {
    text-align: center;
    margin-top: 10px;
    font-size: 14px;
    color: #666;
}

/* Import Log */
#eau-import-institution-modal .eau-import-log {
    max-height: 250px;
    overflow-y: auto;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    font-family: monospace;
    font-size: 12px;
}

#eau-import-institution-modal .eau-import-log p {
    margin: 5px 0;
    padding: 3px 0;
    border-bottom: 1px solid #eee;
}

#eau-import-institution-modal .eau-import-log p:last-child {
    border-bottom: none;
}

#eau-import-institution-modal .eau-log-success {
    color: #155724;
}

#eau-import-institution-modal .eau-log-warning {
    color: #856404;
}

#eau-import-institution-modal .eau-log-error {
    color: #721c24;
}

#eau-import-institution-modal .eau-log-info {
    color: #0c5460;
}

/* Summary */
#eau-import-institution-modal .eau-import-summary {
    background: #f0fff4;
    border: 1px solid #00a32a;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

#eau-import-institution-modal .eau-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

#eau-import-institution-modal .eau-summary-grid.eau-summary-grid-4 {
    grid-template-columns: repeat(4, 1fr);
}

#eau-import-institution-modal .eau-summary-item {
    text-align: center;
}

#eau-import-institution-modal .eau-summary-number {
    font-size: 24px;
    font-weight: 700;
}

#eau-import-institution-modal .eau-summary-label {
    font-size: 12px;
    color: #666;
}

#eau-import-institution-modal .eau-summary-number.updated {
    color: #00a32a;
}

#eau-import-institution-modal .eau-summary-number.created {
    color: #dba617;
}

#eau-import-institution-modal .eau-summary-number.skipped {
    color: #dc3545;
}

#eau-import-institution-modal .eau-summary-number.deleted {
    color: #6366f1;
}
</style>
