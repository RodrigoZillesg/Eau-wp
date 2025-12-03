<?php
/**
 * Event Registrations Statistics
 *
 * @package    EauSystem
 * @subpackage EventRegistrations\Dashboard
 * @since      1.30.0
 */

namespace EauSystem\EventRegistrations\Dashboard;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Registrations_Stats
 *
 * Calcula estatísticas das registrations de um evento.
 *
 * @since 1.30.0
 */
class Registrations_Stats {

    /**
     * Obtém estatísticas completas de um evento
     *
     * @since  1.30.0
     * @param  int   $event_id ID do evento
     * @param  float $price    Preço do evento
     * @return array Estatísticas
     */
    public static function get($event_id, $price = 0) {
        $stats = self::get_default_stats();
        $registration_ids = self::get_registration_ids($event_id);

        $stats['total'] = count($registration_ids);
        $total_revenue = 0;

        foreach ($registration_ids as $reg_id) {
            $payment_status = get_post_meta($reg_id, 'reg_status', true) ?: 'pending';
            if (isset($stats[$payment_status])) {
                $stats[$payment_status]++;
            }

            // Soma os pagamentos reais desta registration
            $total_revenue += \EauSystem\Payments\Payments_Post_Type::get_total_paid($reg_id);
        }

        $stats['revenue'] = $total_revenue;

        return $stats;
    }

    /**
     * Retorna array com valores padrão das estatísticas
     *
     * @since  1.30.0
     * @return array
     */
    private static function get_default_stats() {
        return array(
            'total'    => 0,
            'paid'     => 0,
            'partial'  => 0,
            'free'     => 0,
            'pending'  => 0,
            'failed'   => 0,
            'refunded' => 0,
            'revenue'  => 0,
        );
    }

    /**
     * Obtém IDs das registrations de um evento
     *
     * @since  1.30.0
     * @param  int $event_id ID do evento
     * @return array IDs das registrations
     */
    private static function get_registration_ids($event_id) {
        return get_posts(array(
            'post_type'      => 'eau_event_reg',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'reg_event_id',
                    'value'   => $event_id,
                    'compare' => '=',
                ),
            ),
        ));
    }
}
