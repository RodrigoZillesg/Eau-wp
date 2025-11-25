<?php
/**
 * Event Registrations - Meta Fields Definition
 *
 * Define os meta fields do CPT Event Registration.
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
 * Retorna definição dos meta fields
 *
 * @since  1.29.0
 * @return array field_name => type
 */
function get_meta_fields() {
    return array(
        'attendee_name'     => 'string',
        'attendee_email'    => 'string',
        'event_id'          => 'integer',
        'registration_date' => 'string',
        'member_type'       => 'string',
        'status'            => 'string',
        'user_id'           => 'integer',
    );
}

/**
 * Retorna callback de sanitização por tipo
 *
 * @since  1.29.0
 * @param  string $type Tipo do campo
 * @return callable Callback de sanitização
 */
function get_sanitize_callback($type) {
    switch ($type) {
        case 'integer':
            return 'absint';
        case 'number':
            return 'floatval';
        case 'boolean':
            return 'rest_sanitize_boolean';
        case 'email':
            return 'sanitize_email';
        default:
            return 'sanitize_text_field';
    }
}
