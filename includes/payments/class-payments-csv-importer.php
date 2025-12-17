<?php
/**
 * Payments CSV Importer
 *
 * Classe responsável por importar pagamentos de CSV do sistema legado.
 *
 * @package    EauSystem
 * @subpackage EauSystem/Payments
 * @since      1.53.0
 */

namespace EauSystem\Payments;

// Se acessado diretamente, aborta
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Payments_CSV_Importer
 *
 * Importa pagamentos de arquivos CSV do sistema legado.
 * O CSV usa ponto-e-vírgula como delimitador.
 *
 * Estrutura esperada do CSV:
 * - Order No: Número da ordem (agrupa linhas)
 * - Transaction Id: ID único da transação (usado para prevenir duplicatas)
 * - Email Address: Email do pagador
 * - Full Name: Nome completo do pagador
 * - Payment Method: Método de pagamento
 * - Card Type: Tipo do cartão
 * - Date: Data do pagamento (dd/mm/yyyy)
 * - Description: Descrição do item
 * - Price: Valor unitário
 * - Tax: Valor do imposto
 *
 * @since 1.53.0
 */
class Payments_CSV_Importer {

    /**
     * Delimitador do CSV
     */
    const DELIMITER = ';';

    /**
     * Mapeamento de colunas do CSV para campos internos
     *
     * @var array
     */
    private static $column_map = array(
        'Order No'         => 'order_no',
        'Reference Number' => 'reference_number',
        'Transaction Id'   => 'transaction_id',
        'Email Address'    => 'email',
        'Full Name'        => 'full_name',
        'First Name'       => 'first_name',
        'Last Name'        => 'last_name',
        'Payment Method'   => 'payment_method',
        'Card Type'        => 'card_type',
        'Date'             => 'date',
        'Description'      => 'description',
        'Price'            => 'price',
        'Quantity'         => 'quantity',
        'Total'            => 'total',
        'Tax'              => 'tax',
        'Freight & Handling' => 'freight',
    );

    /**
     * Resultado da importação
     *
     * @var array
     */
    private $result = array(
        'total_rows'        => 0,
        'total_orders'      => 0,
        'imported'          => 0,
        'duplicates_skipped' => 0,
        'errors'            => array(),
        'matched_users'     => 0,
        'unmatched_users'   => 0,
    );

    /**
     * Faz o parse de um arquivo CSV
     *
     * @since 1.53.0
     * @param string $file_path Caminho para o arquivo CSV
     * @return array|WP_Error Array de dados ou erro
     */
    public static function parse_csv($file_path) {
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', __('CSV file not found.', 'eau-system'));
        }

        // Detecta encoding e converte para UTF-8 se necessário
        $content = file_get_contents($file_path);
        $encoding = mb_detect_encoding($content, array('UTF-8', 'ISO-8859-1', 'Windows-1252'), true);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Parse do CSV
        $lines = explode("\n", $content);
        $headers = array();
        $data = array();

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line, self::DELIMITER, '"');

            // Primeira linha é o header
            if (empty($headers)) {
                $headers = array_map('trim', $row);
                continue;
            }

            // Mapeia colunas para campos
            $mapped_row = array();
            foreach ($headers as $col_index => $header) {
                $value = isset($row[$col_index]) ? trim($row[$col_index]) : '';

                // Usa o mapeamento se existir
                $field_name = isset(self::$column_map[$header]) ? self::$column_map[$header] : $header;
                $mapped_row[$field_name] = $value;
            }

            // Guarda dados originais para referência
            $mapped_row['_raw'] = $row;
            $mapped_row['_line'] = $index + 1;

            $data[] = $mapped_row;
        }

        return array(
            'headers' => $headers,
            'data'    => $data,
            'total'   => count($data),
        );
    }

    /**
     * Agrupa linhas do CSV por Order No
     *
     * Cada ordem pode ter múltiplas linhas:
     * - Linha do item principal
     * - Linha de Freight & Handling
     * - Linha de Tax
     *
     * @since 1.53.0
     * @param array $rows Linhas do CSV
     * @return array Ordens agrupadas
     */
    public static function group_by_order($rows) {
        $orders = array();

        foreach ($rows as $row) {
            $order_no = isset($row['order_no']) ? $row['order_no'] : '';

            if (empty($order_no)) {
                continue;
            }

            if (!isset($orders[$order_no])) {
                $orders[$order_no] = array(
                    'order_no'       => $order_no,
                    'items'          => array(),
                    'transaction_id' => '',
                    'email'          => '',
                    'full_name'      => '',
                    'first_name'     => '',
                    'last_name'      => '',
                    'payment_method' => '',
                    'card_type'      => '',
                    'date'           => '',
                    'reference'      => '',
                    'subtotal'       => 0,
                    'tax'            => 0,
                    'freight'        => 0,
                    'total'          => 0,
                );
            }

            // Atualiza dados comuns da ordem
            if (!empty($row['transaction_id'])) {
                $orders[$order_no]['transaction_id'] = $row['transaction_id'];
            }
            if (!empty($row['email'])) {
                $orders[$order_no]['email'] = $row['email'];
            }
            if (!empty($row['full_name'])) {
                $orders[$order_no]['full_name'] = $row['full_name'];
            }
            if (!empty($row['first_name'])) {
                $orders[$order_no]['first_name'] = $row['first_name'];
            }
            if (!empty($row['last_name'])) {
                $orders[$order_no]['last_name'] = $row['last_name'];
            }
            if (!empty($row['payment_method'])) {
                $orders[$order_no]['payment_method'] = $row['payment_method'];
            }
            if (!empty($row['card_type'])) {
                $orders[$order_no]['card_type'] = $row['card_type'];
            }
            if (!empty($row['date'])) {
                $orders[$order_no]['date'] = $row['date'];
            }
            if (!empty($row['reference_number'])) {
                $orders[$order_no]['reference'] = $row['reference_number'];
            }

            // Identifica tipo de linha
            $description = isset($row['description']) ? strtolower($row['description']) : '';
            $price = self::parse_price($row['price'] ?? '0');

            if (strpos($description, 'freight') !== false || strpos($description, 'handling') !== false) {
                // Linha de frete
                $orders[$order_no]['freight'] += $price;
            } elseif (strpos($description, 'tax') !== false || strpos($description, 'gst') !== false) {
                // Linha de imposto
                $orders[$order_no]['tax'] += $price;
            } else {
                // Linha de item
                $orders[$order_no]['items'][] = array(
                    'description' => $row['description'] ?? '',
                    'price'       => $price,
                    'quantity'    => intval($row['quantity'] ?? 1),
                    'total'       => self::parse_price($row['total'] ?? '0'),
                );
                $orders[$order_no]['subtotal'] += $price;
            }
        }

        // Calcula total de cada ordem
        foreach ($orders as &$order) {
            $order['total'] = $order['subtotal'] + $order['tax'] + $order['freight'];
        }

        return array_values($orders);
    }

    /**
     * Converte string de preço para float
     *
     * @since 1.53.0
     * @param string $price String do preço
     * @return float
     */
    public static function parse_price($price) {
        // Remove símbolos de moeda e espaços
        $price = preg_replace('/[^\d.,\-]/', '', $price);

        // Trata formato europeu (1.234,56) vs americano (1,234.56)
        if (strpos($price, ',') !== false && strpos($price, '.') !== false) {
            // Tem ambos - determina qual é o separador decimal
            $last_comma = strrpos($price, ',');
            $last_dot = strrpos($price, '.');

            if ($last_comma > $last_dot) {
                // Formato europeu
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } else {
                // Formato americano
                $price = str_replace(',', '', $price);
            }
        } elseif (strpos($price, ',') !== false) {
            // Só vírgula - pode ser separador decimal ou milhar
            $parts = explode(',', $price);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                // É separador decimal
                $price = str_replace(',', '.', $price);
            } else {
                // É separador de milhar
                $price = str_replace(',', '', $price);
            }
        }

        return floatval($price);
    }

    /**
     * Converte data do formato dd/mm/yyyy para Y-m-d
     *
     * @since 1.53.0
     * @param string $date Data no formato dd/mm/yyyy
     * @return string Data no formato Y-m-d
     */
    public static function parse_date($date) {
        if (empty($date)) {
            return '';
        }

        // Tenta diferentes formatos
        $formats = array(
            'd/m/Y',     // 17/12/2025
            'd-m-Y',     // 17-12-2025
            'm/d/Y',     // 12/17/2025
            'Y-m-d',     // 2025-12-17
            'd/m/y',     // 17/12/25
        );

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $date);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        return '';
    }

    /**
     * Mapeia método de pagamento para o formato interno
     *
     * @since 1.53.0
     * @param string $method Método de pagamento do CSV
     * @return string Método de pagamento interno
     */
    public static function map_payment_method($method) {
        $method = strtolower(trim($method));

        $map = array(
            'credit card'    => 'credit_card',
            'credit'         => 'credit_card',
            'card'           => 'credit_card',
            'debit card'     => 'credit_card',
            'visa'           => 'credit_card',
            'mastercard'     => 'credit_card',
            'amex'           => 'credit_card',
            'bank transfer'  => 'bank_transfer',
            'transfer'       => 'bank_transfer',
            'direct deposit' => 'bank_transfer',
            'paypal'         => 'paypal',
            'cash'           => 'cash',
            'cheque'         => 'cheque',
            'check'          => 'cheque',
        );

        return isset($map[$method]) ? $map[$method] : 'other';
    }

    /**
     * Tenta encontrar um usuário pelo email
     *
     * @since 1.53.0
     * @param string $email Email do usuário
     * @return int|false ID do usuário ou false
     */
    public static function find_user_by_email($email) {
        if (empty($email)) {
            return false;
        }

        $user = get_user_by('email', $email);
        return $user ? $user->ID : false;
    }

    /**
     * Detecta o tipo de pagamento baseado na descrição
     *
     * @since 1.53.0
     * @param string $description Descrição do item
     * @return string 'event', 'membership', ou 'legacy'
     */
    public static function detect_payment_type($description) {
        $description = strtolower($description);

        // Padrões de membership
        $membership_patterns = array(
            'individual:',
            'institutional:',
            'professional affiliate',
            'full provider',
            'associate access',
            'membership',
            'annual fee',
            'subscription',
        );

        foreach ($membership_patterns as $pattern) {
            if (strpos($description, $pattern) !== false) {
                return 'membership';
            }
        }

        // Padrões de evento
        $event_patterns = array(
            'registration fee',
            'workshop',
            'conference',
            'seminar',
            'webinar',
            'event',
            'course',
        );

        foreach ($event_patterns as $pattern) {
            if (strpos($description, $pattern) !== false) {
                return 'event';
            }
        }

        // Não conseguiu identificar
        return 'legacy';
    }

    /**
     * Importa uma ordem como pagamento
     *
     * @since 1.53.0
     * @param array $order Dados da ordem agrupada
     * @return int|WP_Error ID do pagamento ou erro
     */
    public static function import_order($order) {
        // Verifica duplicata pelo transaction_id
        if (!empty($order['transaction_id'])) {
            $existing = Payments_Post_Type::get_payment_by_transaction_id($order['transaction_id']);
            if ($existing) {
                return new \WP_Error(
                    'duplicate',
                    sprintf(__('Payment with transaction ID %s already exists.', 'eau-system'), $order['transaction_id']),
                    array('existing_id' => $existing)
                );
            }
        }

        // Verifica duplicata pelo order_no
        if (!empty($order['order_no'])) {
            $existing = Payments_Post_Type::get_payment_by_legacy_order_no($order['order_no']);
            if ($existing) {
                return new \WP_Error(
                    'duplicate',
                    sprintf(__('Payment with order number %s already exists.', 'eau-system'), $order['order_no']),
                    array('existing_id' => $existing)
                );
            }
        }

        // Tenta encontrar usuário
        $user_id = self::find_user_by_email($order['email']);

        // Detecta tipo de pagamento
        $payment_type = 'legacy';
        $descriptions = array();
        foreach ($order['items'] as $item) {
            $descriptions[] = $item['description'];
            $type = self::detect_payment_type($item['description']);
            if ($type !== 'legacy') {
                $payment_type = $type;
            }
        }

        // Prepara dados do pagamento
        $payment_data = array(
            'payment_type'        => $payment_type,
            'user_id'             => $user_id ? $user_id : 0,
            'amount'              => $order['total'],
            'payment_date'        => self::parse_date($order['date']),
            'payment_method'      => self::map_payment_method($order['payment_method']),
            'transaction_id'      => $order['transaction_id'],
            'status'              => 'confirmed',
            'notes'               => implode(' | ', $descriptions),

            // Campos legacy
            'legacy_order_no'     => $order['order_no'],
            'legacy_reference'    => $order['reference'],
            'legacy_description'  => implode("\n", $descriptions),
            'legacy_raw_data'     => wp_json_encode($order),
            'legacy_import_date'  => current_time('mysql'),
            'payer_name'          => !empty($order['full_name']) ? $order['full_name'] : $order['first_name'] . ' ' . $order['last_name'],
            'payer_email'         => $order['email'],
            'card_type'           => $order['card_type'],
            'tax_amount'          => $order['tax'],
            'subtotal_amount'     => $order['subtotal'],
        );

        // Cria o pagamento
        return Payments_Post_Type::create_payment($payment_data);
    }

    /**
     * Importa todas as ordens de um CSV
     *
     * @since 1.53.0
     * @param string $file_path Caminho para o arquivo CSV
     * @return array Resultado da importação
     */
    public function import_from_csv($file_path) {
        // Reset resultado
        $this->result = array(
            'total_rows'        => 0,
            'total_orders'      => 0,
            'imported'          => 0,
            'duplicates_skipped' => 0,
            'errors'            => array(),
            'matched_users'     => 0,
            'unmatched_users'   => 0,
        );

        // Parse do CSV
        $parsed = self::parse_csv($file_path);
        if (is_wp_error($parsed)) {
            $this->result['errors'][] = array(
                'order_no' => 'N/A',
                'reason'   => $parsed->get_error_message(),
            );
            return $this->result;
        }

        $this->result['total_rows'] = $parsed['total'];

        // Agrupa por ordem
        $orders = self::group_by_order($parsed['data']);
        $this->result['total_orders'] = count($orders);

        // Importa cada ordem
        foreach ($orders as $order) {
            $result = self::import_order($order);

            if (is_wp_error($result)) {
                if ($result->get_error_code() === 'duplicate') {
                    $this->result['duplicates_skipped']++;
                } else {
                    $this->result['errors'][] = array(
                        'order_no' => $order['order_no'],
                        'reason'   => $result->get_error_message(),
                    );
                }
            } else {
                $this->result['imported']++;

                // Conta usuários matched
                $user_id = get_post_meta($result, Payments_Post_Type::META_PREFIX . 'user_id', true);
                if (!empty($user_id) && $user_id > 0) {
                    $this->result['matched_users']++;
                } else {
                    $this->result['unmatched_users']++;
                }
            }
        }

        return $this->result;
    }

    /**
     * Retorna preview das primeiras ordens do CSV
     *
     * @since 1.53.0
     * @param string $file_path Caminho para o arquivo CSV
     * @param int    $limit     Número de ordens a retornar
     * @return array|WP_Error Preview ou erro
     */
    public static function get_preview($file_path, $limit = 10) {
        $parsed = self::parse_csv($file_path);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        $orders = self::group_by_order($parsed['data']);
        $orders = array_slice($orders, 0, $limit);

        // Adiciona informações de duplicata e usuário
        foreach ($orders as &$order) {
            // Verifica duplicata
            $order['is_duplicate'] = false;
            if (!empty($order['transaction_id'])) {
                $existing = Payments_Post_Type::get_payment_by_transaction_id($order['transaction_id']);
                if ($existing) {
                    $order['is_duplicate'] = true;
                    $order['existing_id'] = $existing;
                }
            }

            // Verifica usuário
            $order['user_found'] = false;
            $order['user_id'] = 0;
            if (!empty($order['email'])) {
                $user_id = self::find_user_by_email($order['email']);
                if ($user_id) {
                    $order['user_found'] = true;
                    $order['user_id'] = $user_id;
                }
            }

            // Detecta tipo
            $descriptions = array_map(function($item) {
                return $item['description'];
            }, $order['items']);
            $order['detected_type'] = self::detect_payment_type(implode(' ', $descriptions));
        }

        return array(
            'total_rows'   => $parsed['total'],
            'total_orders' => count(self::group_by_order($parsed['data'])),
            'preview'      => $orders,
        );
    }
}
