<?php
/**
 * Fat Zebra Payment Gateway
 *
 * Wrapper para a API do Fat Zebra.
 * Implementa Hosted Payment Page para evitar PCI compliance.
 *
 * @package EauSystem
 * @subpackage FatZebra
 * @since 1.70.0
 */

namespace EauSystem\FatZebra;

// Se este arquivo foi chamado diretamente, aborta.
if (!defined('WPINC')) {
    die;
}

class FatZebra_Gateway {

    /**
     * API URLs
     */
    const SANDBOX_API_URL = 'https://gateway.pmnts-sandbox.io/v1.0/';
    const PRODUCTION_API_URL = 'https://gateway.pmnts.io/v1.0/';

    /**
     * Hosted Payment Page URLs
     */
    const SANDBOX_HOSTED_URL = 'https://paynow.pmnts-sandbox.io/';
    const PRODUCTION_HOSTED_URL = 'https://paynow.pmnts.io/';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        // Constructor is private for singleton pattern
    }

    /**
     * Create a hosted payment session
     *
     * Cria uma sessão de pagamento hosted e retorna a URL de redirecionamento.
     * O usuário será redirecionado para a página de pagamento da Fat Zebra.
     *
     * @param array $data Dados do pagamento:
     *   - reference: string único (ex: "event_123" ou "course_456")
     *   - amount: int em centavos (ex: 15000 = $150.00)
     *   - currency: string (default: "AUD")
     *   - customer_email: string
     *   - customer_name: string
     *   - description: string (descrição do item)
     *   - return_url: URL de retorno após pagamento
     *   - cancel_url: URL se usuário cancelar
     *
     * @return array|WP_Error
     *   - redirect_url: URL para redirecionar o usuário
     *   - session_id: ID da sessão de pagamento
     */
    public function create_hosted_payment($data) {
        // Valida dados obrigatórios
        $required = array('reference', 'amount', 'customer_email', 'return_url');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Campo obrigatório ausente: %s', 'eau-system'), $field)
                );
            }
        }

        // Prepara payload
        $payload = array(
            'reference'      => sanitize_text_field($data['reference']),
            'amount'         => absint($data['amount']), // Em centavos
            'currency'       => isset($data['currency']) ? strtoupper($data['currency']) : 'AUD',
            'customer_email' => sanitize_email($data['customer_email']),
            'customer_ip'    => $this->get_client_ip(),
            'description'    => isset($data['description']) ? sanitize_text_field($data['description']) : '',
            'return_path'    => esc_url_raw($data['return_url']),
            'cancel_path'    => isset($data['cancel_url']) ? esc_url_raw($data['cancel_url']) : $data['return_url'],
        );

        // Adiciona nome do cliente se fornecido
        if (!empty($data['customer_name'])) {
            $payload['customer_first_name'] = sanitize_text_field($data['customer_name']);
        }

        // Log da requisição (sem dados sensíveis)
        FatZebra_Logger::log('info', 'Creating hosted payment session', array(
            'reference' => $payload['reference'],
            'amount'    => $payload['amount'],
            'email'     => $payload['customer_email'],
        ));

        // Gera a URL do Hosted Payment Page diretamente
        // A Fat Zebra usa URL signing com HMAC para segurança
        $redirect_url = $this->build_hosted_payment_url($payload);

        if (is_wp_error($redirect_url)) {
            FatZebra_Logger::log('error', 'Failed to create hosted payment URL', array(
                'reference' => $payload['reference'],
                'error'     => $redirect_url->get_error_message(),
            ));
            return $redirect_url;
        }

        FatZebra_Logger::log('info', 'Hosted payment URL created', array(
            'reference' => $payload['reference'],
        ));

        return array(
            'redirect_url' => $redirect_url,
            'session_id'   => $payload['reference'],
            'raw_response' => array('reference' => $payload['reference']),
        );
    }

    /**
     * Get transaction by ID
     *
     * @param string $transaction_id ID da transação
     * @return array|WP_Error
     */
    public function get_transaction($transaction_id) {
        if (empty($transaction_id)) {
            return new \WP_Error('invalid_id', __('ID da transação inválido', 'eau-system'));
        }

        FatZebra_Logger::log('info', 'Fetching transaction', array(
            'transaction_id' => $transaction_id,
        ));

        $response = $this->api_request('purchases/' . $transaction_id, 'GET');

        if (is_wp_error($response)) {
            FatZebra_Logger::log('error', 'Failed to fetch transaction', array(
                'transaction_id' => $transaction_id,
                'error'          => $response->get_error_message(),
            ));
        }

        return $response;
    }

    /**
     * Get transaction by reference
     *
     * @param string $reference Reference string
     * @return array|WP_Error
     */
    public function get_transaction_by_reference($reference) {
        if (empty($reference)) {
            return new \WP_Error('invalid_reference', __('Referência inválida', 'eau-system'));
        }

        FatZebra_Logger::log('info', 'Fetching transaction by reference', array(
            'reference' => $reference,
        ));

        $response = $this->api_request('purchases?reference=' . urlencode($reference), 'GET');

        if (is_wp_error($response)) {
            FatZebra_Logger::log('error', 'Failed to fetch transaction by reference', array(
                'reference' => $reference,
                'error'     => $response->get_error_message(),
            ));
            return $response;
        }

        // Retorna a primeira transação encontrada
        if (!empty($response['response']) && is_array($response['response'])) {
            return array(
                'response'   => $response['response'][0] ?? null,
                'successful' => $response['successful'] ?? false,
            );
        }

        return $response;
    }

    /**
     * Create a refund
     *
     * @param string $transaction_id ID da transação original
     * @param int    $amount         Valor em centavos (opcional, total se não informado)
     * @param string $reason         Motivo do reembolso
     * @return array|WP_Error
     */
    public function refund($transaction_id, $amount = null, $reason = '') {
        if (empty($transaction_id)) {
            return new \WP_Error('invalid_id', __('ID da transação inválido', 'eau-system'));
        }

        $payload = array(
            'transaction_id' => $transaction_id,
            'reference'      => 'refund_' . time() . '_' . wp_rand(1000, 9999),
        );

        if ($amount !== null) {
            $payload['amount'] = absint($amount);
        }

        FatZebra_Logger::log('info', 'Creating refund', array(
            'transaction_id' => $transaction_id,
            'amount'         => $amount ?? 'full',
            'reason'         => $reason,
        ));

        $response = $this->api_request('refunds', 'POST', $payload);

        if (is_wp_error($response)) {
            FatZebra_Logger::log('error', 'Failed to create refund', array(
                'transaction_id' => $transaction_id,
                'error'          => $response->get_error_message(),
            ));
        } else {
            FatZebra_Logger::log('info', 'Refund created successfully', array(
                'transaction_id' => $transaction_id,
                'refund_id'      => $response['response']['id'] ?? 'N/A',
            ));
        }

        return $response;
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload   Raw payload body
     * @param string $signature Signature from header
     * @return bool
     */
    public function validate_webhook($payload, $signature) {
        $secret = FatZebra_Settings::get_webhook_secret();

        if (empty($secret)) {
            FatZebra_Logger::log('warning', 'Webhook secret not configured');
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        $is_valid = hash_equals($expected, $signature);

        FatZebra_Logger::log('info', 'Webhook signature validation', array(
            'valid' => $is_valid,
        ));

        return $is_valid;
    }

    /**
     * Test connection to Fat Zebra API
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        FatZebra_Logger::log('info', 'Testing API connection');

        // Try to get ping endpoint or a simple transaction list
        $response = $this->api_request('ping', 'GET');

        if (is_wp_error($response)) {
            // If ping doesn't work, try purchases endpoint
            $response = $this->api_request('purchases?limit=1', 'GET');
        }

        if (is_wp_error($response)) {
            FatZebra_Logger::log('error', 'API connection test failed', array(
                'error' => $response->get_error_message(),
            ));
            return $response;
        }

        FatZebra_Logger::log('info', 'API connection test successful');

        return array(
            'success' => true,
            'message' => __('Conexão com Fat Zebra estabelecida com sucesso!', 'eau-system'),
            'mode'    => FatZebra_Settings::is_sandbox_mode() ? 'sandbox' : 'production',
        );
    }

    /**
     * Make API request to Fat Zebra
     *
     * @param string $endpoint API endpoint
     * @param string $method   HTTP method (GET, POST, PUT, DELETE)
     * @param array  $data     Request data
     * @return array|WP_Error
     */
    private function api_request($endpoint, $method = 'GET', $data = array()) {
        $url = $this->get_api_url() . ltrim($endpoint, '/');

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => $this->get_auth_header(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        // Log response (sem dados sensíveis)
        FatZebra_Logger::log('debug', 'API response', array(
            'endpoint'    => $endpoint,
            'method'      => $method,
            'status_code' => $status_code,
            'successful'  => $decoded['successful'] ?? false,
        ));

        // Verifica erros HTTP
        if ($status_code >= 400) {
            $error_message = $decoded['errors'][0] ?? $decoded['message'] ?? __('Erro na API do Fat Zebra', 'eau-system');
            return new \WP_Error(
                'api_error',
                $error_message,
                array('status_code' => $status_code, 'body' => $decoded)
            );
        }

        return $decoded;
    }

    /**
     * Get API base URL
     *
     * @return string
     */
    private function get_api_url() {
        if (FatZebra_Settings::is_sandbox_mode()) {
            return self::SANDBOX_API_URL;
        }
        return self::PRODUCTION_API_URL;
    }

    /**
     * Get hosted payment page URL
     *
     * @return string
     */
    public function get_hosted_url() {
        if (FatZebra_Settings::is_sandbox_mode()) {
            return self::SANDBOX_HOSTED_URL;
        }
        return self::PRODUCTION_HOSTED_URL;
    }

    /**
     * Build Hosted Payment Page URL
     *
     * Constrói a URL para redirecionar o usuário para a página de pagamento da Fat Zebra.
     * A URL é assinada com HMAC para garantir integridade.
     *
     * @param array $payload Dados do pagamento
     * @return string|WP_Error URL de redirect ou erro
     */
    private function build_hosted_payment_url($payload) {
        $username = FatZebra_Settings::get_username();
        $token = FatZebra_Settings::get_token();

        if (empty($username) || empty($token)) {
            return new \WP_Error(
                'missing_credentials',
                __('Credenciais do Fat Zebra não configuradas', 'eau-system')
            );
        }

        // Parâmetros para a URL
        $params = array(
            'u'          => $username, // Username
            'v'          => $payload['reference'], // Reference/Invoice number
            'a'          => $payload['amount'], // Amount in cents
            'c'          => $payload['currency'] ?? 'AUD', // Currency
            'r'          => $payload['return_path'], // Return URL
            'e'          => $payload['customer_email'], // Email
        );

        // Adiciona cancel_path se fornecido
        if (!empty($payload['cancel_path'])) {
            $params['cancel_url'] = $payload['cancel_path'];
        }

        // Gera a verificação HMAC
        // Formato: reference:amount:currency
        $verification_string = sprintf(
            '%s:%d:%s',
            $payload['reference'],
            $payload['amount'],
            $payload['currency'] ?? 'AUD'
        );

        $verification = hash_hmac('md5', $verification_string, $token);
        $params['verification'] = $verification;

        // Constrói a URL
        $base_url = $this->get_hosted_url();
        $url = $base_url . '?' . http_build_query($params);

        return $url;
    }

    /**
     * Get Authorization header
     *
     * @return string
     */
    private function get_auth_header() {
        $username = FatZebra_Settings::get_username();
        $token = FatZebra_Settings::get_token();

        return 'Basic ' . base64_encode($username . ':' . $token);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        // Check for forwarded IP (proxy/load balancer)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Valida IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '127.0.0.1';
        }

        return $ip;
    }

    /**
     * Format amount for display (from cents to dollars)
     *
     * @param int    $amount_cents Amount in cents
     * @param string $currency     Currency code
     * @return string
     */
    public static function format_amount($amount_cents, $currency = 'AUD') {
        $amount = $amount_cents / 100;
        return '$' . number_format($amount, 2);
    }

    /**
     * Convert dollars to cents
     *
     * @param float $amount Amount in dollars
     * @return int Amount in cents
     */
    public static function dollars_to_cents($amount) {
        return (int) round($amount * 100);
    }

    /**
     * Convert cents to dollars
     *
     * @param int $cents Amount in cents
     * @return float Amount in dollars
     */
    public static function cents_to_dollars($cents) {
        return $cents / 100;
    }

    /**
     * Generate unique reference for a payment
     *
     * @param string $type    Type of payment (event, course)
     * @param int    $item_id ID of the item
     * @return string
     */
    public static function generate_reference($type, $item_id) {
        return sprintf('%s_%d_%d', $type, $item_id, time());
    }

    /**
     * Parse reference to get type and item ID
     *
     * @param string $reference Reference string
     * @return array|null Array with 'type' and 'item_id', or null if invalid
     */
    public static function parse_reference($reference) {
        // Formato: type_itemid_timestamp
        if (preg_match('/^(event|course)_(\d+)_\d+$/', $reference, $matches)) {
            return array(
                'type'    => $matches[1],
                'item_id' => (int) $matches[2],
            );
        }

        // Formato legado: type_itemid
        if (preg_match('/^(event|course)_(\d+)$/', $reference, $matches)) {
            return array(
                'type'    => $matches[1],
                'item_id' => (int) $matches[2],
            );
        }

        return null;
    }
}
