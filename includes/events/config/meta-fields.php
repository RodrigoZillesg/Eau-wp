<?php
/**
 * Events - Meta Fields Configuration
 *
 * Configuração dos meta fields e funções auxiliares.
 *
 * @package    EauSystem
 * @subpackage Events\Config
 * @since      1.28.0
 */

namespace EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/**
 * Lista de meta fields com seus tipos
 *
 * @since  1.28.0
 * @return array ['field_name' => 'type']
 */
function get_meta_fields() {
    return array(
        'short_description'   => 'string',
        'full_description'    => 'html',
        'start_datetime'      => 'string',
        'end_datetime'        => 'string',
        'timezone'            => 'string',
        'image_id'            => 'integer',
        'event_type'          => 'string',
        'venue_name'          => 'string',
        'address'             => 'string',
        'city'                => 'string',
        'state'               => 'string',
        'postal_code'         => 'string',
        'country'             => 'string',
        'virtual_url'         => 'string',
        'capacity'            => 'integer',
        'member_price'        => 'number',
        'early_bird_price'    => 'number',
        'early_bird_end_date' => 'string',
        'cpd_points'          => 'number',
        'cpd_category'        => 'integer',
        'visibility'          => 'string',
        'require_approval'    => 'boolean',
    );
}

/**
 * Valores padrão dos meta fields
 *
 * @since  1.28.0
 * @return array ['field_name' => 'default_value']
 */
function get_defaults() {
    return array(
        'short_description'   => '',
        'full_description'    => '',
        'start_datetime'      => '',
        'end_datetime'        => '',
        'timezone'            => DEFAULT_TIMEZONE,
        'image_id'            => '',
        'event_type'          => DEFAULT_EVENT_TYPE,
        'venue_name'          => '',
        'address'             => '',
        'city'                => '',
        'state'               => '',
        'postal_code'         => '',
        'country'             => DEFAULT_COUNTRY,
        'virtual_url'         => '',
        'capacity'            => '',
        'member_price'        => '0',
        'early_bird_price'    => '',
        'early_bird_end_date' => '',
        'cpd_points'          => '1',
        'cpd_category'        => '',
        'visibility'          => DEFAULT_VISIBILITY,
        'require_approval'    => '',
    );
}

/**
 * Obtém meta de um evento com valores padrão
 *
 * @since  1.28.0
 * @param  int $post_id ID do post
 * @return array Array associativo com todos os campos
 */
function get_event_meta($post_id) {
    $defaults = get_defaults();
    $meta = array();

    foreach ($defaults as $key => $default) {
        $value = get_post_meta($post_id, META_PREFIX . $key, true);
        $meta[$key] = ($value !== '') ? $value : $default;
    }

    return $meta;
}

/**
 * Retorna callback de sanitização por tipo
 *
 * Callbacks são funções que aceitam múltiplos argumentos
 * pois register_post_meta passa 4 argumentos ao sanitize_callback.
 *
 * @since  1.28.0
 * @param  string $type Tipo do campo
 * @return callable
 */
function get_sanitize_callback($type) {
    $callbacks = array(
        'integer' => __NAMESPACE__ . '\\sanitize_integer',
        'number'  => __NAMESPACE__ . '\\sanitize_number',
        'boolean' => __NAMESPACE__ . '\\sanitize_boolean',
        'string'  => __NAMESPACE__ . '\\sanitize_string',
        'html'    => __NAMESPACE__ . '\\sanitize_html',
    );
    return $callbacks[$type] ?? __NAMESPACE__ . '\\sanitize_string';
}

/**
 * Sanitiza valor como inteiro
 *
 * @since  1.28.1
 * @param  mixed $value Valor a sanitizar
 * @return int
 */
function sanitize_integer($value) {
    return absint($value);
}

/**
 * Sanitiza valor como número decimal
 *
 * @since  1.28.1
 * @param  mixed $value Valor a sanitizar
 * @return float
 */
function sanitize_number($value) {
    return floatval($value);
}

/**
 * Sanitiza valor como booleano
 *
 * @since  1.28.1
 * @param  mixed $value Valor a sanitizar
 * @return bool
 */
function sanitize_boolean($value) {
    return rest_sanitize_boolean($value);
}

/**
 * Sanitiza valor como string
 *
 * @since  1.28.1
 * @param  mixed $value Valor a sanitizar
 * @return string
 */
function sanitize_string($value) {
    return sanitize_text_field($value);
}

/**
 * Sanitiza valor como HTML (permite tags HTML seguras)
 *
 * @since  1.46.1
 * @param  mixed $value Valor a sanitizar
 * @return string
 */
function sanitize_html($value) {
    return wp_kses_post($value);
}
