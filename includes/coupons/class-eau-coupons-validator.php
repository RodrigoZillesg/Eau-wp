<?php
namespace EauSystem\Coupons;

/**
 * Validador de cupons de desconto
 *
 * @since 1.69.0
 */
class Eau_Coupons_Validator {

    /**
     * Códigos de erro
     */
    const ERROR_INVALID_CODE = 'invalid_code';
    const ERROR_COUPON_INACTIVE = 'coupon_inactive';
    const ERROR_COUPON_EXPIRED = 'coupon_expired';
    const ERROR_NOT_STARTED = 'not_started';
    const ERROR_USAGE_LIMIT_REACHED = 'usage_limit_reached';
    const ERROR_USER_LIMIT_REACHED = 'user_limit_reached';
    const ERROR_MIN_ORDER_NOT_MET = 'min_order_not_met';
    const ERROR_EVENT_NOT_ALLOWED = 'event_not_allowed';
    const ERROR_NOT_LOGGED_IN = 'not_logged_in';

    /**
     * Valida um cupom para aplicação
     *
     * @param string $code Código do cupom
     * @param int|null $event_id ID do evento (opcional)
     * @param float|null $order_total Total do pedido (opcional)
     * @param int|null $user_id ID do usuário (opcional, usa current user se não fornecido)
     * @return array Array com 'valid' => bool, 'error' => string (se inválido), 'coupon' => array (se válido)
     */
    public static function validate($code, $event_id = null, $order_total = null, $user_id = null) {
        // Verifica se usuário está logado
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return self::error(self::ERROR_NOT_LOGGED_IN, 'You must be logged in to use a coupon.');
        }

        // Busca o cupom
        $coupon = Eau_Coupons_Model::get_coupon_by_code($code);

        if (!$coupon) {
            return self::error(self::ERROR_INVALID_CODE, 'Invalid coupon code.');
        }

        // Verifica status
        if ($coupon['status'] !== 'active') {
            if ($coupon['status'] === 'expired') {
                return self::error(self::ERROR_COUPON_EXPIRED, 'This coupon has expired.');
            }
            return self::error(self::ERROR_COUPON_INACTIVE, 'This coupon is not active.');
        }

        // Verifica data de início
        if (!empty($coupon['valid_from'])) {
            $valid_from = strtotime($coupon['valid_from']);
            if ($valid_from > time()) {
                $date = date_i18n('F j, Y', $valid_from);
                return self::error(self::ERROR_NOT_STARTED, "This coupon is not valid yet. It starts on {$date}.");
            }
        }

        // Verifica data de expiração
        if (!empty($coupon['valid_until'])) {
            $valid_until = strtotime($coupon['valid_until']);
            if ($valid_until < time()) {
                return self::error(self::ERROR_COUPON_EXPIRED, 'This coupon has expired.');
            }
        }

        // Verifica limite total de uso
        if (!empty($coupon['usage_limit'])) {
            if ($coupon['usage_count'] >= $coupon['usage_limit']) {
                return self::error(self::ERROR_USAGE_LIMIT_REACHED, 'This coupon has reached its usage limit.');
            }
        }

        // Verifica limite por usuário
        if (!empty($coupon['usage_limit_per_user'])) {
            $user_usage = Eau_Coupons_Model::get_user_usage_count($coupon['id'], $user_id);
            if ($user_usage >= $coupon['usage_limit_per_user']) {
                return self::error(self::ERROR_USER_LIMIT_REACHED, 'You have already used this coupon the maximum number of times.');
            }
        }

        // Verifica valor mínimo do pedido
        if (!empty($coupon['min_order_value']) && $order_total !== null) {
            if ($order_total < $coupon['min_order_value']) {
                $min = number_format($coupon['min_order_value'], 2);
                return self::error(self::ERROR_MIN_ORDER_NOT_MET, "Minimum order value of \${$min} is required for this coupon.");
            }
        }

        // Verifica se o cupom é válido para o evento
        if ($event_id !== null && $coupon['event_scope'] === 'specific') {
            $allowed_events = $coupon['events'];
            if (!in_array($event_id, $allowed_events)) {
                return self::error(self::ERROR_EVENT_NOT_ALLOWED, 'This coupon is not valid for this event.');
            }
        }

        // Cupom válido
        return array(
            'valid' => true,
            'coupon' => $coupon,
            'discount' => self::calculate_discount($coupon, $order_total),
        );
    }

    /**
     * Calcula o desconto de um cupom
     *
     * @param array $coupon Dados do cupom
     * @param float $order_total Total do pedido
     * @return array Array com 'amount' => valor do desconto, 'final_total' => total final
     */
    public static function calculate_discount($coupon, $order_total) {
        if ($order_total === null || $order_total <= 0) {
            return array(
                'amount' => 0,
                'final_total' => 0,
            );
        }

        $discount = 0;

        if ($coupon['discount_type'] === 'percentage') {
            // Desconto percentual
            $discount = ($order_total * $coupon['discount_value']) / 100;

            // Aplica desconto máximo se configurado
            if (!empty($coupon['max_discount']) && $discount > $coupon['max_discount']) {
                $discount = $coupon['max_discount'];
            }
        } else {
            // Desconto fixo
            $discount = $coupon['discount_value'];
        }

        // Garante que o desconto não excede o total
        if ($discount > $order_total) {
            $discount = $order_total;
        }

        // Arredonda para 2 casas decimais
        $discount = round($discount, 2);
        $final_total = round($order_total - $discount, 2);

        return array(
            'amount' => $discount,
            'final_total' => $final_total,
            'original_total' => $order_total,
        );
    }

    /**
     * Valida e calcula desconto para múltiplos membros
     *
     * @param string $code Código do cupom
     * @param int $event_id ID do evento
     * @param float $price_per_person Preço por pessoa
     * @param int $member_count Número de membros
     * @param int|null $user_id ID do usuário que está aplicando
     * @return array
     */
    public static function validate_for_multiple($code, $event_id, $price_per_person, $member_count, $user_id = null) {
        // Calcula total
        $order_total = $price_per_person * $member_count;

        // Valida cupom
        $validation = self::validate($code, $event_id, $order_total, $user_id);

        if (!$validation['valid']) {
            return $validation;
        }

        // Adiciona informações adicionais para registro múltiplo
        $validation['member_count'] = $member_count;
        $validation['price_per_person'] = $price_per_person;
        $validation['total_before_discount'] = $order_total;

        return $validation;
    }

    /**
     * Retorna array de erro formatado
     *
     * @param string $code Código do erro
     * @param string $message Mensagem de erro
     * @return array
     */
    private static function error($code, $message) {
        return array(
            'valid' => false,
            'error_code' => $code,
            'error' => $message,
        );
    }

    /**
     * Formata o desconto para exibição
     *
     * @param array $coupon Dados do cupom
     * @return string
     */
    public static function format_discount($coupon) {
        if ($coupon['discount_type'] === 'percentage') {
            $formatted = $coupon['discount_value'] . '%';
            if (!empty($coupon['max_discount'])) {
                $formatted .= ' (max $' . number_format($coupon['max_discount'], 2) . ')';
            }
            return $formatted;
        }

        return '$' . number_format($coupon['discount_value'], 2);
    }

    /**
     * Retorna descrição legível da validade do cupom
     *
     * @param array $coupon Dados do cupom
     * @return string
     */
    public static function get_validity_description($coupon) {
        $parts = array();

        if (!empty($coupon['valid_from'])) {
            $parts[] = 'from ' . date_i18n('M j, Y', strtotime($coupon['valid_from']));
        }

        if (!empty($coupon['valid_until'])) {
            $parts[] = 'until ' . date_i18n('M j, Y', strtotime($coupon['valid_until']));
        }

        if (empty($parts)) {
            return 'No expiration';
        }

        return 'Valid ' . implode(' ', $parts);
    }

    /**
     * Retorna descrição legível do uso do cupom
     *
     * @param array $coupon Dados do cupom
     * @return string
     */
    public static function get_usage_description($coupon) {
        $parts = array();

        if (!empty($coupon['usage_limit'])) {
            $remaining = $coupon['usage_limit'] - $coupon['usage_count'];
            $parts[] = $remaining . ' of ' . $coupon['usage_limit'] . ' uses remaining';
        }

        if (!empty($coupon['usage_limit_per_user'])) {
            $parts[] = $coupon['usage_limit_per_user'] . ' use(s) per user';
        }

        if (empty($parts)) {
            return 'Unlimited usage';
        }

        return implode(' | ', $parts);
    }
}
