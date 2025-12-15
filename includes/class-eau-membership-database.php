<?php
namespace EauSystem;

/**
 * Database helper for Membership System
 *
 * Creates and manages tables for:
 * - wp_eau_membership_applications (membership applications)
 * - wp_eau_membership_types (membership type definitions)
 * - wp_eau_newsletters (newsletter definitions)
 * - wp_eau_membership_reminders (expiration reminder tracking)
 *
 * @since 1.49.0
 */
class Eau_Membership_Database {

    /**
     * Table names (without prefix)
     */
    const TABLE_APPLICATIONS = 'eau_membership_applications';
    const TABLE_TYPES = 'eau_membership_types';
    const TABLE_NEWSLETTERS = 'eau_newsletters';
    const TABLE_REMINDERS = 'eau_membership_reminders';

    /**
     * Application statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get full table name with prefix
     *
     * @param string $table Table constant
     * @return string
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . $table;
    }

    /**
     * Check if all tables exist
     *
     * @return bool
     */
    public static function tables_exist() {
        global $wpdb;

        $tables = array(
            self::TABLE_APPLICATIONS,
            self::TABLE_TYPES,
            self::TABLE_NEWSLETTERS,
            self::TABLE_REMINDERS,
        );

        foreach ($tables as $table) {
            $table_name = self::get_table_name($table);
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create all membership tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create each table
        self::create_applications_table($charset_collate);
        self::create_types_table($charset_collate);
        self::create_newsletters_table($charset_collate);
        self::create_reminders_table($charset_collate);

        // Seed initial data if tables are empty
        self::seed_initial_data();

        // Run migrations for existing tables
        self::run_migrations();
    }

    /**
     * Run migrations for existing tables
     *
     * @since 1.51.34
     */
    private static function run_migrations() {
        global $wpdb;

        $table_name = self::get_table_name(self::TABLE_TYPES);

        // Migration 1: Add is_institution_type and show_cricos_fields columns
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'is_institution_type'");
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_institution_type TINYINT(1) DEFAULT 1 AFTER requires_email_submission");
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN show_cricos_fields TINYINT(1) DEFAULT 0 AFTER is_institution_type");

            // Update existing institution types
            $wpdb->update($table_name, array('is_institution_type' => 1, 'show_cricos_fields' => 1), array('type_key' => 'full_provider'));
            $wpdb->update($table_name, array('is_institution_type' => 1, 'show_cricos_fields' => 1), array('type_key' => 'associate_access'));
            $wpdb->update($table_name, array('is_institution_type' => 1, 'show_cricos_fields' => 0), array('type_key' => 'corporate_affiliate'));
            $wpdb->update($table_name, array('is_institution_type' => 1, 'show_cricos_fields' => 0), array('type_key' => 'professional_affiliate'));
        }

    }

    /**
     * Create membership applications table
     *
     * @param string $charset_collate Database charset
     */
    private static function create_applications_table($charset_collate) {
        global $wpdb;

        $table_name = self::get_table_name(self::TABLE_APPLICATIONS);

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            application_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            -- Reference to existing user (if logged in when applying)
            user_id BIGINT UNSIGNED NULL,

            -- Applicant data
            email VARCHAR(255) NOT NULL,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            position VARCHAR(100),
            company_name VARCHAR(255),

            -- Location
            state VARCHAR(50),
            country VARCHAR(100) DEFAULT 'Australia',

            -- Membership type requested
            membership_type VARCHAR(50) NOT NULL,

            -- Type-specific data (JSON)
            membership_data LONGTEXT,

            -- Attached documents (JSON with attachment IDs)
            documents LONGTEXT,

            -- Selected newsletters (JSON)
            newsletter_subscriptions LONGTEXT,

            -- Workflow status
            status VARCHAR(20) DEFAULT 'pending',

            -- Timestamps
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            reviewed_by BIGINT UNSIGNED NULL,

            -- Admin notes
            admin_notes TEXT NULL,
            rejection_reason TEXT NULL,

            -- Reference to created user (if approved)
            created_user_id BIGINT UNSIGNED NULL,
            created_institution_id BIGINT UNSIGNED NULL,

            -- Indexes
            INDEX idx_user_id (user_id),
            INDEX idx_email (email),
            INDEX idx_status (status),
            INDEX idx_membership_type (membership_type),
            INDEX idx_submitted_at (submitted_at),
            INDEX idx_created_user_id (created_user_id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create membership types table
     *
     * @param string $charset_collate Database charset
     */
    private static function create_types_table($charset_collate) {
        global $wpdb;

        $table_name = self::get_table_name(self::TABLE_TYPES);

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            type_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            type_key VARCHAR(50) NOT NULL UNIQUE,
            type_label VARCHAR(255) NOT NULL,
            type_description TEXT,

            -- Pricing
            fee_amount DECIMAL(10,2) DEFAULT 0,
            fee_member_college DECIMAL(10,2) NULL,
            fee_currency VARCHAR(3) DEFAULT 'AUD',
            fee_includes_gst TINYINT(1) DEFAULT 1,
            fee_is_variable TINYINT(1) DEFAULT 0,

            -- Requirements (JSON array)
            requirements TEXT,

            -- Required documents (JSON array)
            required_documents TEXT,

            -- Maximum duration in months
            max_duration_months INT DEFAULT 12,

            -- Benefits (JSON array)
            benefits TEXT,

            -- Newsletter access (JSON array)
            newsletter_access TEXT,

            -- Form field definitions (JSON)
            form_fields TEXT,

            -- Workflow settings
            requires_approval TINYINT(1) DEFAULT 1,
            requires_email_submission VARCHAR(255) NULL,

            -- Type classification
            is_institution_type TINYINT(1) DEFAULT 1,
            show_cricos_fields TINYINT(1) DEFAULT 0,

            -- Status and ordering
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_type_key (type_key),
            INDEX idx_is_active (is_active),
            INDEX idx_display_order (display_order)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create newsletters table
     *
     * @param string $charset_collate Database charset
     */
    private static function create_newsletters_table($charset_collate) {
        global $wpdb;

        $table_name = self::get_table_name(self::TABLE_NEWSLETTERS);

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            newsletter_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            newsletter_key VARCHAR(50) NOT NULL UNIQUE,
            newsletter_name VARCHAR(255) NOT NULL,
            newsletter_description TEXT,

            -- Access restrictions
            is_public TINYINT(1) DEFAULT 0,

            -- Allowed membership types (JSON array, or null for all)
            allowed_membership_types TEXT,

            -- Status and ordering
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_newsletter_key (newsletter_key),
            INDEX idx_is_public (is_public),
            INDEX idx_is_active (is_active),
            INDEX idx_display_order (display_order)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create membership reminders table
     *
     * @param string $charset_collate Database charset
     */
    private static function create_reminders_table($charset_collate) {
        global $wpdb;

        $table_name = self::get_table_name(self::TABLE_REMINDERS);

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            reminder_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            user_id BIGINT UNSIGNED NOT NULL,
            membership_type VARCHAR(50),

            -- Reminder type: 60_days, 30_days, 7_days, expired
            reminder_type VARCHAR(20) NOT NULL,

            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_user_id (user_id),
            INDEX idx_reminder_type (reminder_type),
            INDEX idx_user_reminder (user_id, reminder_type),
            INDEX idx_sent_at (sent_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Seed initial data for membership types and newsletters
     */
    public static function seed_initial_data() {
        self::seed_membership_types();
        self::seed_newsletters();
    }

    /**
     * Seed membership types from PRD
     */
    private static function seed_membership_types() {
        global $wpdb;

        $table_name = self::get_table_name(self::TABLE_TYPES);

        // Check if already seeded
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($count > 0) {
            return;
        }

        $membership_types = array(
            array(
                'type_key' => 'full_provider',
                'type_label' => 'Full Provider Membership',
                'type_description' => 'For established CRICOS-registered providers with 12+ months of registration.',
                'fee_amount' => 0,
                'fee_is_variable' => 1,
                'requirements' => json_encode(array('CRICOS registration 12+ months')),
                'required_documents' => json_encode(array('Full Membership Information Sheet')),
                'newsletter_access' => json_encode(array('all')),
                'benefits' => json_encode(array(
                    'Full voting rights at AGM',
                    'Access to all SIG newsletters',
                    'Professional development opportunities',
                    'Industry representation',
                    'Networking events access',
                    'Member directory listing',
                    'Use of English Australia logo'
                )),
                'display_order' => 1,
            ),
            array(
                'type_key' => 'associate_access',
                'type_label' => 'Associate Provider Membership - Access',
                'type_description' => 'For new CRICOS-registered providers with less than 12 months of registration.',
                'fee_amount' => 2500.00,
                'fee_includes_gst' => 1,
                'max_duration_months' => 12,
                'requirements' => json_encode(array('CRICOS registration < 12 months')),
                'newsletter_access' => json_encode(array('all')),
                'benefits' => json_encode(array(
                    'Access to all SIG newsletters',
                    'Professional development opportunities',
                    'Networking events access',
                    'Member directory listing',
                    'Pathway to Full Provider membership',
                    'Industry updates and news',
                    'Mentoring support'
                )),
                'display_order' => 2,
            ),
            array(
                'type_key' => 'corporate_affiliate',
                'type_label' => 'Corporate Affiliate Membership',
                'type_description' => 'For businesses and organizations supporting the English language sector.',
                'fee_amount' => 2500.00,
                'fee_member_college' => 1225.00,
                'fee_includes_gst' => 1,
                'requires_email_submission' => 'sophieokeefe@englishaustralia.com.au',
                'newsletter_access' => json_encode(array('pd_updates', 'industry_news')),
                'benefits' => json_encode(array(
                    'Professional Development Updates newsletter',
                    'Industry News newsletter',
                    'Networking events access',
                    'Exhibitor opportunities at conferences',
                    'Advertising opportunities',
                    'Member directory listing',
                    'Business development support'
                )),
                'display_order' => 3,
            ),
            array(
                'type_key' => 'professional_affiliate',
                'type_label' => 'Professional Affiliate (Institution) Membership',
                'type_description' => 'For educational institutions and professional organizations.',
                'fee_amount' => 2000.00,
                'fee_includes_gst' => 1,
                'required_documents' => json_encode(array('Professional Affiliate Information Sheet')),
                'newsletter_access' => json_encode(array('pd_updates', 'industry_news')),
                'benefits' => json_encode(array(
                    'Professional Development Updates newsletter',
                    'Industry News newsletter',
                    'Professional development opportunities',
                    'Networking events access',
                    'Member directory listing',
                    'Resource library access',
                    'Conference attendance discounts'
                )),
                'display_order' => 4,
            ),
        );

        foreach ($membership_types as $type) {
            $wpdb->insert($table_name, $type);
        }
    }

    /**
     * Seed newsletters from PRD
     */
    private static function seed_newsletters() {
        global $wpdb;

        $table_name = self::get_table_name(self::TABLE_NEWSLETTERS);

        // Check if already seeded
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($count > 0) {
            return;
        }

        $newsletters = array(
            // Public newsletters
            array(
                'newsletter_key' => 'pd_updates',
                'newsletter_name' => 'Professional Development Updates',
                'newsletter_description' => 'Updates on professional development opportunities, workshops, and training.',
                'is_public' => 1,
                'display_order' => 1,
            ),
            array(
                'newsletter_key' => 'industry_news',
                'newsletter_name' => 'English Australia and Industry News',
                'newsletter_description' => 'General news and updates from English Australia and the industry.',
                'is_public' => 1,
                'display_order' => 2,
            ),
            // Members only newsletters
            array(
                'newsletter_key' => 'sig_direct_entry',
                'newsletter_name' => 'Direct Entry Programs SIG',
                'newsletter_description' => 'Special Interest Group for Direct Entry Programs.',
                'is_public' => 0,
                'allowed_membership_types' => json_encode(array('full_provider', 'associate_access')),
                'display_order' => 3,
            ),
            array(
                'newsletter_key' => 'sig_wellbeing',
                'newsletter_name' => 'Wellbeing SIG',
                'newsletter_description' => 'Special Interest Group focused on student and staff wellbeing.',
                'is_public' => 0,
                'allowed_membership_types' => json_encode(array('full_provider', 'associate_access')),
                'display_order' => 4,
            ),
            array(
                'newsletter_key' => 'sig_assessment',
                'newsletter_name' => 'Assessment SIG',
                'newsletter_description' => 'Special Interest Group for assessment practices and standards.',
                'is_public' => 0,
                'allowed_membership_types' => json_encode(array('full_provider', 'associate_access')),
                'display_order' => 5,
            ),
            array(
                'newsletter_key' => 'sig_ed_tech',
                'newsletter_name' => 'Ed-Tech SIG',
                'newsletter_description' => 'Special Interest Group for educational technology.',
                'is_public' => 0,
                'allowed_membership_types' => json_encode(array('full_provider', 'associate_access')),
                'display_order' => 6,
            ),
            array(
                'newsletter_key' => 'sig_post_entry',
                'newsletter_name' => 'Post-entry English and Academic Language SIG',
                'newsletter_description' => 'Special Interest Group for post-entry English and academic language programs.',
                'is_public' => 0,
                'allowed_membership_types' => json_encode(array('full_provider', 'associate_access')),
                'display_order' => 7,
            ),
            array(
                'newsletter_key' => 'sig_online_learning',
                'newsletter_name' => 'Online Learning',
                'newsletter_description' => 'Updates and best practices for online learning.',
                'is_public' => 0,
                'allowed_membership_types' => json_encode(array('full_provider', 'associate_access')),
                'display_order' => 8,
            ),
            array(
                'newsletter_key' => 'sig_academic_managers',
                'newsletter_name' => "Academic Managers' SIG",
                'newsletter_description' => 'Special Interest Group for Academic Managers.',
                'is_public' => 0,
                'allowed_membership_types' => json_encode(array('full_provider', 'associate_access')),
                'display_order' => 9,
            ),
        );

        foreach ($newsletters as $newsletter) {
            $wpdb->insert($table_name, $newsletter);
        }
    }

    /**
     * Drop all membership tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            self::TABLE_APPLICATIONS,
            self::TABLE_TYPES,
            self::TABLE_NEWSLETTERS,
            self::TABLE_REMINDERS,
        );

        foreach ($tables as $table) {
            $table_name = self::get_table_name($table);
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
    }

    /**
     * Upgrade existing tables to add missing columns
     * Called during plugin update
     */
    public static function maybe_upgrade_tables() {
        global $wpdb;

        $table_name = self::get_table_name(self::TABLE_APPLICATIONS);

        // Check if user_id column exists in applications table
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'user_id'");

        if (empty($columns)) {
            // Add user_id column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER application_id");
            $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_user_id (user_id)");
        }
    }

    /**
     * Get table status (for debugging)
     *
     * @return array
     */
    public static function get_table_status() {
        global $wpdb;

        $status = array();
        $tables = array(
            self::TABLE_APPLICATIONS,
            self::TABLE_TYPES,
            self::TABLE_NEWSLETTERS,
            self::TABLE_REMINDERS,
        );

        foreach ($tables as $table) {
            $table_name = self::get_table_name($table);
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;

            $status[$table] = array(
                'exists' => $exists,
                'count' => (int) $count,
            );
        }

        return $status;
    }
}
