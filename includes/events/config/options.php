<?php
/**
 * Events - Options
 *
 * Listas de opções: timezones, países, tipos de evento.
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
 * Retorna lista de timezones
 *
 * @since  1.28.0
 * @return array ['timezone_key' => 'Label']
 */
function get_timezones() {
    return array(
        'Australia/Sydney'     => 'Australia / Sydney',
        'Australia/Melbourne'  => 'Australia / Melbourne',
        'Australia/Brisbane'   => 'Australia / Brisbane',
        'Australia/Perth'      => 'Australia / Perth',
        'Australia/Adelaide'   => 'Australia / Adelaide',
        'Australia/Darwin'     => 'Australia / Darwin',
        'Australia/Hobart'     => 'Australia / Hobart',
        'Pacific/Auckland'     => 'Pacific / Auckland',
        'Asia/Tokyo'           => 'Asia / Tokyo',
        'Asia/Singapore'       => 'Asia / Singapore',
        'Asia/Hong_Kong'       => 'Asia / Hong Kong',
        'Europe/London'        => 'Europe / London',
        'Europe/Paris'         => 'Europe / Paris',
        'America/New_York'     => 'America / New York',
        'America/Los_Angeles'  => 'America / Los Angeles',
        'America/Chicago'      => 'America / Chicago',
    );
}

/**
 * Retorna lista de países
 *
 * @since  1.28.0
 * @return array ['country_key' => 'Label']
 */
function get_countries() {
    return array(
        'Australia'      => 'Australia',
        'New Zealand'    => 'New Zealand',
        'United States'  => 'United States',
        'United Kingdom' => 'United Kingdom',
        'Canada'         => 'Canada',
        'Germany'        => 'Germany',
        'France'         => 'France',
        'Japan'          => 'Japan',
        'China'          => 'China',
        'India'          => 'India',
        'Brazil'         => 'Brazil',
        'Singapore'      => 'Singapore',
        'Hong Kong'      => 'Hong Kong',
        'Other'          => 'Other',
    );
}

/**
 * Retorna tipos de evento
 *
 * @since  1.28.0
 * @return array ['type_key' => 'Label']
 */
function get_event_types() {
    return array(
        'in-person' => __('In-Person', 'eau-system'),
        'virtual'   => __('Virtual', 'eau-system'),
        'hybrid'    => __('Hybrid', 'eau-system'),
    );
}

/**
 * Retorna opções de visibilidade
 *
 * @since  1.28.0
 * @return array ['visibility_key' => 'Label']
 */
function get_visibility_options() {
    return array(
        'public'  => __('Public - Everyone can see', 'eau-system'),
        'members' => __('Members Only', 'eau-system'),
        'private' => __('Private - By invitation only', 'eau-system'),
    );
}

/**
 * Retorna categorias CPD do banco de dados
 *
 * @since  1.30.0
 * @return array Array de categorias com id, category_name, points_per_hour
 */
function get_cpd_categories_from_db() {
    return \EauSystem\Shared\get_cpd_categories();
}

/**
 * Converte array para formato JetEngine
 *
 * @since  1.28.0
 * @param  array $array Array associativo
 * @return array Array no formato [['key' => '', 'value' => ''], ...]
 */
function to_jet_format($array) {
    return \EauSystem\Shared\to_jet_format($array);
}
