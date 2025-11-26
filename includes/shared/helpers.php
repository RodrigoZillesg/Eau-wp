<?php
/**
 * Shared Helpers
 *
 * Funções auxiliares compartilhadas entre módulos.
 *
 * @package    EauSystem
 * @subpackage Shared
 * @since      1.30.0
 */

namespace EauSystem\Shared;

if (!defined('WPINC')) {
    die;
}

/**
 * Converte array associativo para formato JetEngine
 *
 * @since  1.30.0
 * @param  array $array Array associativo ['key' => 'value']
 * @return array Array no formato [['key' => '', 'value' => ''], ...]
 */
function to_jet_format($array) {
    $options = array();
    foreach ($array as $key => $value) {
        $options[] = array('key' => $key, 'value' => $value);
    }
    return $options;
}

/**
 * Obtém categorias CPD da tabela eau_activity_categories
 *
 * @since  1.30.0
 * @return array Lista de categorias com id, category_serial, category_name, points_per_hour
 */
function get_cpd_categories() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'eau_activity_categories';

    // Verifica se a tabela existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        return array();
    }

    $results = $wpdb->get_results(
        "SELECT id, category_serial, category_name, points_per_hour
         FROM $table_name
         ORDER BY category_name ASC",
        ARRAY_A
    );

    return $results ? $results : array();
}
