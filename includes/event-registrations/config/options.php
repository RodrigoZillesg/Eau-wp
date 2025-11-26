<?php
/**
 * Event Registrations - Options
 *
 * Define opções para selects e radios.
 *
 * @package    EauSystem
 * @subpackage Events\Registrations\Config
 * @since      1.29.0
 */

namespace EauSystem\EventRegistrations\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Retorna opções de status
 *
 * @since  1.29.0
 * @return array
 */
function get_status_options() {
    return array(
        'confirmed' => __('Confirmed', 'eau-system'),
        'pending'   => __('Pending', 'eau-system'),
        'cancelled' => __('Cancelled', 'eau-system'),
    );
}

/**
 * Retorna opções de tipo de membro
 *
 * @since  1.29.0
 * @return array
 */
function get_member_type_options() {
    return array(
        'member'     => __('Member', 'eau-system'),
        'non_member' => __('Non-Member', 'eau-system'),
    );
}

/**
 * Retorna opções de status de pagamento
 *
 * @since  1.30.0
 * @return array
 */
function get_payment_status_options() {
    return array(
        'pending'  => __('Pending', 'eau-system'),
        'paid'     => __('Paid', 'eau-system'),
        'failed'   => __('Failed', 'eau-system'),
        'refunded' => __('Refunded', 'eau-system'),
    );
}

/**
 * Converte array de opções para formato JetEngine
 *
 * @since  1.29.0
 * @param  array $options Opções no formato key => label
 * @return array Opções no formato JetEngine
 */
function to_jet_format($options) {
    return \EauSystem\Shared\to_jet_format($options);
}
