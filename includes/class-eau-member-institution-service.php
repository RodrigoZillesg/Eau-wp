<?php
/**
 * Service class for Member-Institution relationship management
 *
 * Centralizes all operations related to linking/unlinking members to institutions.
 * This ensures consistent behavior across all entry points (membership applications,
 * my institution, member creation, etc.)
 *
 * @package EauSystem
 * @since 1.53.0
 */

namespace EauSystem;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Eau_Member_Institution_Service
 *
 * Handles all member-institution relationship operations
 */
class Eau_Member_Institution_Service {

    /**
     * Link a member to an institution
     *
     * @param int $user_id WordPress user ID
     * @param int $institution_id Institution post ID
     * @param array $options Options array
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function link_member($user_id, $institution_id, $options = array()) {
        $defaults = array(
            'member_type' => 'member', // 'member' or 'institutionAdmin'
            'set_as_primary_contact' => false,
            'performed_by' => get_current_user_id(),
            'reason' => '',
            'skip_history' => false,
        );

        $options = wp_parse_args($options, $defaults);

        // Validate user exists
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new \WP_Error('invalid_user', __('User not found.', 'eau-system'));
        }

        // Validate institution exists
        $institution = get_post($institution_id);
        if (!$institution || $institution->post_type !== 'institutions') {
            return new \WP_Error('invalid_institution', __('Institution not found.', 'eau-system'));
        }

        // Get ins_company_id
        $ins_company_id = get_post_meta($institution_id, 'ins_company_id', true);
        if (empty($ins_company_id)) {
            return new \WP_Error('no_company_id', __('Institution does not have a company ID.', 'eau-system'));
        }

        // Check if already linked to another institution
        $current_company_id = get_user_meta($user_id, 'mem_membercompanyname', true);
        if (!empty($current_company_id) && $current_company_id !== $ins_company_id) {
            // This is a transfer, not a simple link
            return self::transfer_member($user_id, $institution_id, $options);
        }

        // Get previous member type for history
        $previous_member_type = get_user_meta($user_id, 'mem_type', true);

        // Update user metadata
        update_user_meta($user_id, 'mem_membercompanyname', $ins_company_id);

        // Novo sistema (v1.66.0): Separação de papéis do sistema vs administração de instituição
        if ($options['member_type'] === 'institutionAdmin') {
            // Não altera mem_type - preserva papel original (superAdmin, Admin, member, etc.)
            // Usa o novo sistema de managed institutions
            Eau_User_Institution_Helper::add_managed_institution($user_id, $institution_id);
        } else {
            // Para outros tipos (member, non-member), só altera mem_type se:
            // - Não for superAdmin ou Admin (preserva papéis administrativos)
            // - Ou se o mem_type atual estiver vazio/indefinido
            $roles_to_preserve = array('superAdmin', 'Admin');
            if (!in_array($previous_member_type, $roles_to_preserve)) {
                update_user_meta($user_id, 'mem_type', $options['member_type']);
            }
        }

        // v1.67.1 - Define membership status como 'active' quando vinculado a uma instituição
        // Isso garante que o usuário tenha acesso às funcionalidades de membro
        $current_membership_status = get_user_meta($user_id, 'mem_membership_status', true);
        if (empty($current_membership_status) || $current_membership_status === 'inactive') {
            update_user_meta($user_id, 'mem_membership_status', 'active');
        }

        // If setting as primary contact, update institution
        if ($options['set_as_primary_contact']) {
            // Usa o mem_userid do usuário como primary contact
            $mem_userid = get_user_meta($user_id, 'mem_userid', true);
            if (!empty($mem_userid)) {
                update_post_meta($institution_id, 'ins_company_primary_contact', $mem_userid);
            }
        }

        // Log to history
        if (!$options['skip_history']) {
            self::log_action($user_id, $institution_id, 'linked', array(
                'ins_company_id' => $ins_company_id,
                'new_member_type' => $options['member_type'],
                'previous_member_type' => $previous_member_type,
                'performed_by' => $options['performed_by'],
                'reason' => $options['reason'],
            ));
        }

        /**
         * Fires after a member is linked to an institution
         *
         * @param int $user_id User ID
         * @param int $institution_id Institution post ID
         * @param string $ins_company_id Institution company ID
         * @param array $options Options used for linking
         */
        do_action('eau_member_linked', $user_id, $institution_id, $ins_company_id, $options);

        return true;
    }

    /**
     * Check if a member can be unlinked from their institution
     *
     * @param int $user_id WordPress user ID
     * @return bool|WP_Error True if can unlink, WP_Error with reason if cannot
     */
    public static function can_unlink_member($user_id) {
        $member_type = get_user_meta($user_id, 'mem_type', true);

        // If not an institutionAdmin, can always unlink
        if ($member_type !== 'institutionAdmin') {
            return true;
        }

        // Check if linked to an institution
        $company_id = get_user_meta($user_id, 'mem_membercompanyname', true);
        if (empty($company_id)) {
            return true; // Not linked, nothing to unlink
        }

        // Get institution
        $institution = self::get_institution_by_company_id($company_id);
        if (!$institution) {
            return true; // Institution doesn't exist, safe to unlink
        }

        // Count admins for this institution
        $admin_count = self::count_institution_admins($institution->ID);

        if ($admin_count <= 1) {
            return new \WP_Error(
                'only_admin',
                __('This member is the only administrator of the institution. Please assign another administrator before unlinking.', 'eau-system')
            );
        }

        return true;
    }

    /**
     * Unlink a member from their institution
     *
     * @param int $user_id WordPress user ID
     * @param array $options Options array
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function unlink_member($user_id, $options = array()) {
        $defaults = array(
            'performed_by' => get_current_user_id(),
            'reason' => '',
            'force' => false, // Bypass only-admin validation
            'skip_history' => false,
            'update_membership_status' => false, // Whether to update mem_membership_status
            'new_membership_status' => 'cancelled',
        );

        $options = wp_parse_args($options, $defaults);

        // Validate user exists
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new \WP_Error('invalid_user', __('User not found.', 'eau-system'));
        }

        // Validate can unlink (unless forced)
        if (!$options['force']) {
            $can_unlink = self::can_unlink_member($user_id);
            if (is_wp_error($can_unlink)) {
                return $can_unlink;
            }
        }

        // Get current data for history
        $current_company_id = get_user_meta($user_id, 'mem_membercompanyname', true);
        $current_member_type = get_user_meta($user_id, 'mem_type', true);

        if (empty($current_company_id)) {
            return new \WP_Error('not_linked', __('Member is not linked to any institution.', 'eau-system'));
        }

        $current_institution = self::get_institution_by_company_id($current_company_id);
        $current_institution_id = $current_institution ? $current_institution->ID : null;

        // Clear institution link
        delete_user_meta($user_id, 'mem_membercompanyname');

        // Remove da lista de managed institutions (novo sistema v1.66.0)
        if ($current_institution_id) {
            Eau_User_Institution_Helper::remove_managed_institution($user_id, $current_institution_id, true);
        }

        // Downgrade from institutionAdmin to member (legado - mantido para compatibilidade)
        if ($current_member_type === 'institutionAdmin') {
            // Verifica se ainda administra outras instituições
            $still_manages = Eau_User_Institution_Helper::get_user_managed_institutions($user_id);
            if (empty($still_manages)) {
                update_user_meta($user_id, 'mem_type', 'member');
            }
        }

        // Update membership status if requested
        if ($options['update_membership_status']) {
            update_user_meta($user_id, 'mem_membership_status', $options['new_membership_status']);
        }

        // If was primary contact, clear it from institution
        if ($current_institution_id) {
            $primary_contact = get_post_meta($current_institution_id, 'ins_company_primary_contact', true);
            if ($primary_contact == $user_id) {
                delete_post_meta($current_institution_id, 'ins_company_primary_contact');
            }
        }

        // Log to history
        if (!$options['skip_history']) {
            self::log_action($user_id, $current_institution_id, 'unlinked', array(
                'previous_ins_company_id' => $current_company_id,
                'previous_member_type' => $current_member_type,
                'new_member_type' => $current_member_type === 'institutionAdmin' ? 'member' : $current_member_type,
                'performed_by' => $options['performed_by'],
                'reason' => $options['reason'],
            ));
        }

        /**
         * Fires after a member is unlinked from an institution
         *
         * @param int $user_id User ID
         * @param int|null $institution_id Previous institution post ID
         * @param string $company_id Previous institution company ID
         * @param array $options Options used for unlinking
         */
        do_action('eau_member_unlinked', $user_id, $current_institution_id, $current_company_id, $options);

        return true;
    }

    /**
     * Transfer a member from one institution to another
     *
     * @param int $user_id WordPress user ID
     * @param int $new_institution_id New institution post ID
     * @param array $options Options array
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function transfer_member($user_id, $new_institution_id, $options = array()) {
        $defaults = array(
            'member_type' => 'member',
            'set_as_primary_contact' => false,
            'performed_by' => get_current_user_id(),
            'reason' => '',
            'force' => false,
        );

        $options = wp_parse_args($options, $defaults);

        // Validate can unlink from current institution
        if (!$options['force']) {
            $can_unlink = self::can_unlink_member($user_id);
            if (is_wp_error($can_unlink)) {
                return $can_unlink;
            }
        }

        // Get current institution data for history
        $previous_company_id = get_user_meta($user_id, 'mem_membercompanyname', true);
        $previous_institution = self::get_institution_by_company_id($previous_company_id);
        $previous_institution_id = $previous_institution ? $previous_institution->ID : null;
        $previous_member_type = get_user_meta($user_id, 'mem_type', true);

        // Validate new institution
        $new_institution = get_post($new_institution_id);
        if (!$new_institution || $new_institution->post_type !== 'institutions') {
            return new \WP_Error('invalid_institution', __('New institution not found.', 'eau-system'));
        }

        $new_company_id = get_post_meta($new_institution_id, 'ins_company_id', true);
        if (empty($new_company_id)) {
            return new \WP_Error('no_company_id', __('New institution does not have a company ID.', 'eau-system'));
        }

        // Clear primary contact from old institution if applicable
        if ($previous_institution_id) {
            $mem_userid = get_user_meta($user_id, 'mem_userid', true);
            $primary_contact = get_post_meta($previous_institution_id, 'ins_company_primary_contact', true);
            if ($primary_contact == $user_id || $primary_contact == $mem_userid) {
                delete_post_meta($previous_institution_id, 'ins_company_primary_contact');
            }

            // Remove da lista de managed institutions da instituição anterior (v1.66.0)
            Eau_User_Institution_Helper::remove_managed_institution($user_id, $previous_institution_id, false);
        }

        // Update user metadata
        update_user_meta($user_id, 'mem_membercompanyname', $new_company_id);

        // Novo sistema (v1.66.0): Separação de papéis do sistema vs administração de instituição
        if ($options['member_type'] === 'institutionAdmin') {
            // Não altera mem_type - preserva papel original (superAdmin, Admin, member, etc.)
            // Usa o novo sistema de managed institutions
            Eau_User_Institution_Helper::add_managed_institution($user_id, $new_institution_id);
        } else {
            // Para outros tipos (member, non-member), só altera mem_type se:
            // - Não for superAdmin ou Admin (preserva papéis administrativos)
            $roles_to_preserve = array('superAdmin', 'Admin');
            if (!in_array($previous_member_type, $roles_to_preserve)) {
                update_user_meta($user_id, 'mem_type', $options['member_type']);
            }
        }

        // v1.67.1 - Define membership status como 'active' quando transferido para uma instituição
        $current_membership_status = get_user_meta($user_id, 'mem_membership_status', true);
        if (empty($current_membership_status) || $current_membership_status === 'inactive') {
            update_user_meta($user_id, 'mem_membership_status', 'active');
        }

        // Set as primary contact of new institution if requested
        if ($options['set_as_primary_contact']) {
            $mem_userid = get_user_meta($user_id, 'mem_userid', true);
            if (!empty($mem_userid)) {
                update_post_meta($new_institution_id, 'ins_company_primary_contact', $mem_userid);
            }
        }

        // Log transfer to history
        Eau_Member_Institution_Database::insert(array(
            'user_id' => $user_id,
            'institution_id' => $new_institution_id,
            'ins_company_id' => $new_company_id,
            'action' => 'transferred',
            'previous_institution_id' => $previous_institution_id,
            'previous_ins_company_id' => $previous_company_id,
            'new_member_type' => $options['member_type'],
            'previous_member_type' => $previous_member_type,
            'performed_by' => $options['performed_by'],
            'reason' => $options['reason'],
        ));

        /**
         * Fires after a member is transferred to a new institution
         *
         * @param int $user_id User ID
         * @param int $new_institution_id New institution post ID
         * @param int|null $previous_institution_id Previous institution post ID
         * @param array $options Options used for transfer
         */
        do_action('eau_member_transferred', $user_id, $new_institution_id, $previous_institution_id, $options);

        return true;
    }

    /**
     * Log an action to the history table
     *
     * @param int $user_id User ID
     * @param int|null $institution_id Institution post ID
     * @param string $action Action type (linked, unlinked, transferred)
     * @param array $data Additional data
     * @return int|false Insert ID or false on failure
     */
    public static function log_action($user_id, $institution_id, $action, $data = array()) {
        $insert_data = array(
            'user_id' => $user_id,
            'institution_id' => $institution_id,
            'action' => $action,
            'ins_company_id' => isset($data['ins_company_id']) ? $data['ins_company_id'] : null,
            'previous_institution_id' => isset($data['previous_institution_id']) ? $data['previous_institution_id'] : null,
            'previous_ins_company_id' => isset($data['previous_ins_company_id']) ? $data['previous_ins_company_id'] : null,
            'new_member_type' => isset($data['new_member_type']) ? $data['new_member_type'] : null,
            'previous_member_type' => isset($data['previous_member_type']) ? $data['previous_member_type'] : null,
            'performed_by' => isset($data['performed_by']) ? $data['performed_by'] : get_current_user_id(),
            'reason' => isset($data['reason']) ? $data['reason'] : null,
        );

        // If institution_id is set but ins_company_id is not, try to get it
        if ($institution_id && empty($insert_data['ins_company_id'])) {
            $insert_data['ins_company_id'] = get_post_meta($institution_id, 'ins_company_id', true);
        }

        return Eau_Member_Institution_Database::insert($insert_data);
    }

    /**
     * Get history records for a member
     *
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array
     */
    public static function get_member_history($user_id, $args = array()) {
        return Eau_Member_Institution_Database::get_user_history($user_id, $args);
    }

    /**
     * Get institution by company ID
     *
     * @param string $company_id Company ID (ins_company_id)
     * @return WP_Post|null
     */
    public static function get_institution_by_company_id($company_id) {
        if (empty($company_id)) {
            return null;
        }

        $institutions = get_posts(array(
            'post_type' => 'institutions',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => 'ins_company_id',
                    'value' => $company_id,
                    'compare' => '=',
                ),
            ),
        ));

        return !empty($institutions) ? $institutions[0] : null;
    }

    /**
     * Get all institution admins
     *
     * @since 1.66.0 - Atualizado para usar o novo sistema de separação de papéis
     * @param int $institution_id Institution post ID
     * @return array Array of user objects
     */
    public static function get_institution_admins($institution_id) {
        // Usa o helper para obter os user IDs (combina novo sistema + legado + primary contact)
        $user_ids = Eau_User_Institution_Helper::get_institution_admin_user_ids($institution_id);

        if (empty($user_ids)) {
            return array();
        }

        // Retorna os objetos de usuário
        return get_users(array(
            'include' => $user_ids,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ));
    }

    /**
     * Count institution admins
     *
     * @param int $institution_id Institution post ID
     * @return int
     */
    public static function count_institution_admins($institution_id) {
        $admins = self::get_institution_admins($institution_id);
        return count($admins);
    }

    /**
     * Get all members of an institution
     *
     * @param int $institution_id Institution post ID
     * @param array $args Query arguments
     * @return array Array of user objects
     */
    public static function get_institution_members($institution_id, $args = array()) {
        $company_id = get_post_meta($institution_id, 'ins_company_id', true);
        if (empty($company_id)) {
            return array();
        }

        $defaults = array(
            'number' => -1,
            'orderby' => 'display_name',
            'order' => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);

        return get_users(array(
            'number' => $args['number'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'meta_query' => array(
                array(
                    'key' => 'mem_membercompanyname',
                    'value' => $company_id,
                    'compare' => '=',
                ),
            ),
        ));
    }

    /**
     * Check if user is linked to an institution
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function is_member_linked($user_id) {
        $company_id = get_user_meta($user_id, 'mem_membercompanyname', true);
        return !empty($company_id);
    }

    /**
     * Get the institution a member is linked to
     *
     * @param int $user_id User ID
     * @return WP_Post|null
     */
    public static function get_member_institution($user_id) {
        $company_id = get_user_meta($user_id, 'mem_membercompanyname', true);
        if (empty($company_id)) {
            return null;
        }

        return self::get_institution_by_company_id($company_id);
    }
}
