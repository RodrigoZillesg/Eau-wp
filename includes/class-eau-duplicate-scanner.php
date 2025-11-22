<?php
namespace EauSystem;

/**
 * Scanner inteligente para detectar membros duplicados
 */
class Eau_Duplicate_Scanner {

    /**
     * Configuração de campos e pesos para análise
     */
    private static $field_weights = array(
        'display_name' => 25,
        'user_email' => 20,
        'mem_phone' => 15,
        'mem_membercompanyname' => 15,
        'mem_postcode' => 10,
        'mem_address' => 10,
        'mem_city' => 5,
    );

    /**
     * Threshold mínimo para considerar duplicata
     */
    const MIN_SIMILARITY_THRESHOLD = 50.0;

    /**
     * Registra hooks do WordPress
     */
    public static function register_hooks() {
        add_action('eau_execute_duplicate_scan', array(__CLASS__, 'execute_scan_hook'));
    }

    /**
     * Hook callback para executar scan em background
     */
    public static function execute_scan_hook($scan_id) {

        try {
            self::execute_scan($scan_id);
        } catch (\Exception $e) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'eau_duplicate_scans',
                array('scan_status' => 'failed'),
                array('scan_id' => $scan_id),
                array('%s'),
                array('%d')
            );
        }
    }

    /**
     * Inicia um novo scan de duplicatas
     */
    public static function start_scan($user_id) {
        global $wpdb;

        // ========== LIMPEZA E CANCELAMENTO DE SCANS ANTERIORES ==========

        // Cancela qualquer scan em andamento
        $cancelled = $wpdb->update(
            $wpdb->prefix . 'eau_duplicate_scans',
            array('scan_status' => 'cancelled'),
            array('scan_status' => 'in_progress'),
            array('%s'),
            array('%s')
        );

        // Limpa todos os locks de scan para evitar execuções travadas
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_eau_scan_lock_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_eau_scan_lock_%'");

        // Limpa duplicatas pendentes antigas antes de começar novo scan
        // Mantém apenas as que foram merged, dismissed ou ignored (histórico)
        $deleted_pairs = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}eau_duplicate_pairs
            WHERE pair_status = 'pending'"
        );

        // Limpa scans antigos, mantendo apenas os últimos 10 para histórico
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}eau_duplicate_scans
            WHERE scan_id NOT IN (
                SELECT scan_id FROM (
                    SELECT scan_id
                    FROM {$wpdb->prefix}eau_duplicate_scans
                    ORDER BY scan_date DESC
                    LIMIT 10
                ) AS recent_scans
            )"
        );

        // Cria registro de scan
        $result = $wpdb->insert(
            $wpdb->prefix . 'eau_duplicate_scans',
            array(
                'scan_date' => current_time('mysql'),
                'scan_status' => 'in_progress',
                'scan_by_user_id' => $user_id,
            ),
            array('%s', '%s', '%d')
        );

        if ($result === false) {
            return false;
        }

        $scan_id = $wpdb->insert_id;


        // Agenda execução do scan em background usando WP Cron
        // Isso permite que o AJAX retorne imediatamente
        wp_schedule_single_event(time(), 'eau_execute_duplicate_scan', array($scan_id));

        // Força o cron a executar imediatamente (sem esperar o próximo ciclo)
        spawn_cron();


        return $scan_id;
    }

    /**
     * Executa o scan completo
     */
    private static function execute_scan($scan_id) {
        global $wpdb;

        try {

            // ========== PROTEÇÃO CONTRA EXECUÇÃO DUPLICADA ==========
            $lock_key = 'eau_scan_lock_' . $scan_id;
            $lock_value = get_transient($lock_key);

            if ($lock_value !== false) {
                return;
            }

            // Cria lock que expira em 1 hora
            set_transient($lock_key, time(), HOUR_IN_SECONDS);

            // Aumenta limites de memória e tempo
            @ini_set('memory_limit', '512M');
            @set_time_limit(300);

            // Busca apenas IDs dos usuários (mais leve)
            $user_ids = get_users(array(
                'fields' => 'ID',
                'number' => -1,
            ));

            $total_users = count($user_ids);

            // Salva o total de usuários no início

            $update_result = $wpdb->update(
                $wpdb->prefix . 'eau_duplicate_scans',
                array('total_users' => $total_users),
                array('scan_id' => $scan_id),
                array('%d'),
                array('%d')
            );

            if ($update_result === false) {
            } else {
            }

            if ($total_users === 0) {
                $wpdb->update(
                    $wpdb->prefix . 'eau_duplicate_scans',
                    array(
                        'scan_status' => 'completed',
                        'total_users_analyzed' => 0,
                        'duplicates_found' => 0,
                    ),
                    array('scan_id' => $scan_id),
                    array('%s', '%d', '%d'),
                    array('%d')
                );
                return;
            }

            $duplicates_found = 0;

            // Busca exclusões existentes
            $exclusions = self::get_exclusions();

            // === OTIMIZAÇÃO: BLOCKING ===
            // Agrupa usuários por "blocos" (último sobrenome ou empresa)
            // Isso reduz dramaticamente o número de comparações
            $blocks = array();

            foreach ($user_ids as $user_id) {
                $user = get_user_by('id', $user_id);
                if (!$user) continue;

                $last_name = get_user_meta($user_id, 'last_name', true);
                $company = get_user_meta($user_id, 'mem_membercompanyname', true);

                // Cria chaves de bloqueio
                $block_keys = array();

                // Bloco por sobrenome (primeiras 3 letras)
                if (!empty($last_name)) {
                    $block_keys[] = 'ln_' . strtolower(substr($last_name, 0, 3));
                }

                // Bloco por empresa
                if (!empty($company)) {
                    $block_keys[] = 'co_' . strtolower(substr($company, 0, 5));
                }

                // Adiciona usuário a todos os blocos relevantes
                foreach ($block_keys as $key) {
                    if (!isset($blocks[$key])) {
                        $blocks[$key] = array();
                    }
                    $blocks[$key][] = $user_id;
                }
            }


            // Estima total de comparações baseado nos blocos
            $estimated_comparisons = 0;
            foreach ($blocks as $block) {
                $block_size = count($block);
                $estimated_comparisons += ($block_size * ($block_size - 1)) / 2;
            }


            $total_comparisons = max(1, $estimated_comparisons); // Evita divisão por zero
            $comparisons_done = 0;
            $pairs_compared = array(); // Evita comparar o mesmo par 2x

            // Compara apenas usuários dentro dos mesmos blocos
            foreach ($blocks as $block_key => $block_user_ids) {
                $block_size = count($block_user_ids);

                // Pula blocos muito pequenos ou muito grandes
                if ($block_size < 2 || $block_size > 500) {
                    continue;
                }


                for ($i = 0; $i < $block_size; $i++) {
                    for ($j = $i + 1; $j < $block_size; $j++) {
                        $user_id_1 = $block_user_ids[$i];
                        $user_id_2 = $block_user_ids[$j];

                        // Evita comparar o mesmo par duas vezes
                        $pair_key = min($user_id_1, $user_id_2) . '_' . max($user_id_1, $user_id_2);
                        if (isset($pairs_compared[$pair_key])) {
                            continue;
                        }
                        $pairs_compared[$pair_key] = true;

                        $comparisons_done++;

                        // Verifica se esse par está na lista de exclusões
                        if (self::is_excluded($user_id_1, $user_id_2, $exclusions)) {
                            continue;
                        }

                        // Carrega usuários individualmente para economizar memória
                        $user1 = get_user_by('id', $user_id_1);
                        $user2 = get_user_by('id', $user_id_2);

                        if (!$user1 || !$user2) {
                            continue;
                        }

                        // Compara os usuários
                        $comparison = self::compare_users($user1, $user2);

                        // Se similaridade >= threshold, salva como duplicata
                        if ($comparison['score'] >= self::MIN_SIMILARITY_THRESHOLD) {
                            self::save_duplicate_pair($scan_id, $user_id_1, $user_id_2, $comparison);
                            $duplicates_found++;
                        }

                        // Atualiza progresso a cada 100 comparações (era 50)
                        if ($comparisons_done % 100 === 0) {
                            $progress_percent = round(($comparisons_done / $total_comparisons) * 100, 1);

                            $wpdb->update(
                                $wpdb->prefix . 'eau_duplicate_scans',
                                array(
                                    'total_users_analyzed' => $comparisons_done,
                                    'duplicates_found' => $duplicates_found,
                                ),
                                array('scan_id' => $scan_id),
                                array('%d', '%d'),
                                array('%d')
                            );


                            // Limpa cache para evitar estouro de memória
                            wp_cache_flush();
                        }
                    }
                }
            }


            // Atualiza scan como concluído
            $wpdb->update(
                $wpdb->prefix . 'eau_duplicate_scans',
                array(
                    'scan_status' => 'completed',
                    'total_users_analyzed' => $comparisons_done, // Usa comparações reais, não total de usuários
                    'duplicates_found' => $duplicates_found,
                ),
                array('scan_id' => $scan_id),
                array('%s', '%d', '%d'),
                array('%d')
            );


            // Libera lock
            delete_transient('eau_scan_lock_' . $scan_id);

        } catch (\Exception $e) {

            // Marca scan como falho
            $wpdb->update(
                $wpdb->prefix . 'eau_duplicate_scans',
                array('scan_status' => 'failed'),
                array('scan_id' => $scan_id),
                array('%s'),
                array('%d')
            );

            // Libera lock mesmo em caso de erro
            delete_transient('eau_scan_lock_' . $scan_id);
        }
    }

    /**
     * Compara dois usuários e retorna score de similaridade
     */
    public static function compare_users($user1, $user2) {
        $total_score = 0;
        $total_weight = 0;
        $matches = array();

        // Carrega meta fields de ambos usuários
        $meta1 = self::get_user_meta_fields($user1->ID);
        $meta2 = self::get_user_meta_fields($user2->ID);

        // Compara cada campo
        foreach (self::$field_weights as $field => $weight) {
            $value1 = self::get_field_value($user1, $meta1, $field);
            $value2 = self::get_field_value($user2, $meta2, $field);

            // Pula campos vazios
            if (empty($value1) || empty($value2)) {
                continue;
            }

            $field_result = self::calculate_field_similarity($field, $value1, $value2);

            // Compatibilidade: se retornar número, usa direto. Se array, pega score e tags
            $field_score = is_array($field_result) ? $field_result['score'] : $field_result;
            $field_tags = is_array($field_result) && isset($field_result['tags']) ? $field_result['tags'] : array();

            if ($field_score > 0) {
                $total_score += $field_score * $weight;
                $total_weight += $weight;

                // Adiciona tag se similaridade >= 70%
                if ($field_score >= 0.7) {
                    $matches[] = self::get_field_label($field);

                    // Adiciona tags específicas (ex: sobrenome, inicial)
                    foreach ($field_tags as $tag) {
                        $matches[] = $tag;
                    }
                }
            }
        }

        // Calcula score final (porcentagem)
        $final_score = $total_weight > 0 ? ($total_score / $total_weight) * 100 : 0;

        return array(
            'score' => round($final_score, 2),
            'matches' => $matches,
            'details' => array(
                'user1' => array(
                    'id' => $user1->ID,
                    'display_name' => $user1->display_name,
                    'email' => $user1->user_email,
                    'meta' => $meta1,
                ),
                'user2' => array(
                    'id' => $user2->ID,
                    'display_name' => $user2->display_name,
                    'email' => $user2->user_email,
                    'meta' => $meta2,
                ),
            ),
        );
    }

    /**
     * Calcula similaridade de um campo específico
     */
    private static function calculate_field_similarity($field, $value1, $value2) {
        $value1 = strtolower(trim($value1));
        $value2 = strtolower(trim($value2));

        // Match exato
        if ($value1 === $value2) {
            return 1.0;
        }

        switch ($field) {
            case 'display_name':
                return self::compare_names($value1, $value2);

            case 'user_email':
                return self::compare_emails($value1, $value2);

            case 'mem_phone':
                return self::compare_phones($value1, $value2);

            case 'mem_postcode':
                return self::compare_postcodes($value1, $value2);

            case 'mem_address':
                return self::compare_addresses($value1, $value2);

            case 'mem_city':
            case 'mem_membercompanyname':
                return self::compare_text($value1, $value2);

            default:
                return 0;
        }
    }

    /**
     * Compara nomes (análise de primeiro nome, sobrenome, iniciais)
     */
    private static function compare_names($name1, $name2) {
        // Separa em partes (primeiro nome, sobrenomes, etc)
        $parts1 = preg_split('/\s+/', $name1);
        $parts2 = preg_split('/\s+/', $name2);

        if (empty($parts1) || empty($parts2)) {
            return 0;
        }

        $scores = array();

        // 1. Compara primeiro nome (mais importante)
        $first1 = $parts1[0];
        $first2 = $parts2[0];

        $max_length = max(strlen($first1), strlen($first2));
        if ($max_length > 0) {
            $distance = levenshtein($first1, $first2);
            $first_name_score = 1 - ($distance / $max_length);

            // Soundex para nomes parecidos foneticamente (João/Joao)
            if (soundex($first1) === soundex($first2)) {
                $first_name_score = max($first_name_score, 0.9);
            }

            $scores[] = $first_name_score * 1.5; // Peso maior para primeiro nome
        }

        // 2. Compara sobrenomes
        if (count($parts1) > 1 && count($parts2) > 1) {
            $last1 = $parts1[count($parts1) - 1];
            $last2 = $parts2[count($parts2) - 1];

            // Sobrenome completo
            if ($last1 === $last2) {
                $scores[] = 1.0 * 1.5; // Sobrenome idêntico é muito importante!
            } else {
                // Verifica se é abreviação (ex: "Silva" vs "S" ou "S.")
                $is_abbrev1 = strlen($last1) <= 2 && strpos($last1, '.') !== false || strlen($last1) === 1;
                $is_abbrev2 = strlen($last2) <= 2 && strpos($last2, '.') !== false || strlen($last2) === 1;

                if ($is_abbrev1 || $is_abbrev2) {
                    $full = $is_abbrev1 ? $last2 : $last1;
                    $abbrev = $is_abbrev1 ? $last1 : $last2;
                    $abbrev = str_replace('.', '', $abbrev);

                    // Verifica se a abreviação corresponde à primeira letra
                    if (strtolower($abbrev[0]) === strtolower($full[0])) {
                        $scores[] = 0.7; // Boa indicação mas não certeza
                    }
                } else {
                    // Compara sobrenomes normalmente
                    $max_length = max(strlen($last1), strlen($last2));
                    $distance = levenshtein($last1, $last2);
                    $last_name_score = 1 - ($distance / $max_length);

                    // Soundex para sobrenomes
                    if (soundex($last1) === soundex($last2)) {
                        $last_name_score = max($last_name_score, 0.85);
                    }

                    $scores[] = $last_name_score * 1.5;
                }
            }
        }

        // 3. Compara nomes do meio (se existirem)
        $middle_parts1 = array_slice($parts1, 1, -1);
        $middle_parts2 = array_slice($parts2, 1, -1);

        if (!empty($middle_parts1) && !empty($middle_parts2)) {
            $middle_matches = 0;
            $middle_total = max(count($middle_parts1), count($middle_parts2));

            foreach ($middle_parts1 as $m1) {
                foreach ($middle_parts2 as $m2) {
                    if ($m1 === $m2 || (strlen($m1) === 1 && strlen($m2) === 1 && $m1 === $m2)) {
                        $middle_matches++;
                    }
                }
            }

            if ($middle_total > 0) {
                $scores[] = ($middle_matches / $middle_total) * 0.5; // Peso menor para nomes do meio
            }
        }

        // 4. Fallback: comparação do nome completo
        $max_length = max(strlen($name1), strlen($name2));
        $distance = levenshtein(substr($name1, 0, 255), substr($name2, 0, 255));
        $levenshtein_score = 1 - ($distance / $max_length);

        $soundex_score = (soundex($name1) === soundex($name2)) ? 0.8 : 0;
        $scores[] = ($levenshtein_score * 0.7) + ($soundex_score * 0.3);

        // Calcula score final
        $final_score = !empty($scores) ? array_sum($scores) / count($scores) : 0;

        // Gera tags específicas
        $tags = array();

        if (count($parts1) > 1 && count($parts2) > 1) {
            $last1 = $parts1[count($parts1) - 1];
            $last2 = $parts2[count($parts2) - 1];

            // Tag para sobrenome idêntico
            if ($last1 === $last2) {
                $tags[] = array(
                    'label' => 'Same Last Name',
                    'icon' => 'users',
                    'type' => 'lastname'
                );
            }
            // Tag para sobrenome abreviado
            else {
                $is_abbrev1 = strlen($last1) <= 2;
                $is_abbrev2 = strlen($last2) <= 2;

                if (($is_abbrev1 || $is_abbrev2) && $final_score >= 0.6) {
                    $tags[] = array(
                        'label' => 'Similar Last Initial',
                        'icon' => 'user-check',
                        'type' => 'initial'
                    );
                }
            }
        }

        // Retorna score e tags
        return array(
            'score' => $final_score,
            'tags' => $tags
        );
    }

    /**
     * Compara emails
     */
    private static function compare_emails($email1, $email2) {
        // Separa username e domínio
        $parts1 = explode('@', $email1);
        $parts2 = explode('@', $email2);

        if (count($parts1) !== 2 || count($parts2) !== 2) {
            return 0;
        }

        $user1 = $parts1[0];
        $domain1 = $parts1[1];
        $user2 = $parts2[0];
        $domain2 = $parts2[1];

        // Se domínios diferentes, score baixo
        if ($domain1 !== $domain2) {
            return 0.2;
        }

        // Se domínios iguais, compara usernames
        $max_length = max(strlen($user1), strlen($user2));
        $distance = levenshtein($user1, $user2);
        $similarity = 1 - ($distance / $max_length);

        // Score baseado na similaridade do username
        return $similarity * 0.8 + 0.2; // +0.2 por ter mesmo domínio
    }

    /**
     * Compara telefones (normaliza e compara)
     */
    private static function compare_phones($phone1, $phone2) {
        // Remove tudo que não é número
        $normalized1 = preg_replace('/[^0-9]/', '', $phone1);
        $normalized2 = preg_replace('/[^0-9]/', '', $phone2);

        if ($normalized1 === $normalized2) {
            return 1.0;
        }

        // Verifica se um contém o outro (ex: +55 11 vs 11)
        if (strlen($normalized1) > strlen($normalized2)) {
            if (strpos($normalized1, $normalized2) !== false) {
                return 0.9;
            }
        } elseif (strlen($normalized2) > strlen($normalized1)) {
            if (strpos($normalized2, $normalized1) !== false) {
                return 0.9;
            }
        }

        return 0;
    }

    /**
     * Compara códigos postais
     */
    private static function compare_postcodes($postcode1, $postcode2) {
        // Remove formatação
        $normalized1 = preg_replace('/[^0-9]/', '', $postcode1);
        $normalized2 = preg_replace('/[^0-9]/', '', $postcode2);

        if ($normalized1 === $normalized2) {
            return 1.0;
        }

        // Verifica primeiros 5 dígitos (CEP brasileiro)
        if (strlen($normalized1) >= 5 && strlen($normalized2) >= 5) {
            if (substr($normalized1, 0, 5) === substr($normalized2, 0, 5)) {
                return 0.7; // Mesma região
            }
        }

        return 0;
    }

    /**
     * Compara endereços
     */
    private static function compare_addresses($addr1, $addr2) {
        return self::compare_text($addr1, $addr2);
    }

    /**
     * Comparação genérica de texto
     */
    private static function compare_text($text1, $text2) {
        $max_length = max(strlen($text1), strlen($text2));
        if ($max_length === 0) {
            return 0;
        }

        $distance = levenshtein(substr($text1, 0, 255), substr($text2, 0, 255));
        return 1 - ($distance / $max_length);
    }

    /**
     * Busca meta fields do usuário
     */
    private static function get_user_meta_fields($user_id) {
        return array(
            'mem_phone' => get_user_meta($user_id, 'mem_phone', true),
            'mem_membercompanyname' => get_user_meta($user_id, 'mem_membercompanyname', true),
            'mem_address' => get_user_meta($user_id, 'mem_address', true),
            'mem_city' => get_user_meta($user_id, 'mem_city', true),
            'mem_postcode' => get_user_meta($user_id, 'mem_postcode', true),
        );
    }

    /**
     * Pega valor do campo (core ou meta)
     */
    private static function get_field_value($user, $meta, $field) {
        if ($field === 'display_name') {
            return $user->display_name;
        } elseif ($field === 'user_email') {
            return $user->user_email;
        } else {
            return isset($meta[$field]) ? $meta[$field] : '';
        }
    }

    /**
     * Retorna label amigável do campo com ícone e tipo
     */
    private static function get_field_label($field) {
        $labels = array(
            'display_name' => array(
                'label' => 'Similar Name',
                'icon' => 'user',
                'type' => 'name'
            ),
            'user_email' => array(
                'label' => 'Similar Email',
                'icon' => 'mail',
                'type' => 'email'
            ),
            'mem_phone' => array(
                'label' => 'Similar Phone',
                'icon' => 'phone',
                'type' => 'phone'
            ),
            'mem_membercompanyname' => array(
                'label' => 'Same Company',
                'icon' => 'building-2',
                'type' => 'company'
            ),
            'mem_address' => array(
                'label' => 'Similar Address',
                'icon' => 'map-pin',
                'type' => 'address'
            ),
            'mem_city' => array(
                'label' => 'Same City',
                'icon' => 'map',
                'type' => 'city'
            ),
            'mem_postcode' => array(
                'label' => 'Same Postcode',
                'icon' => 'hash',
                'type' => 'postcode'
            ),
        );

        if (isset($labels[$field])) {
            return $labels[$field];
        }

        return array(
            'label' => $field,
            'icon' => 'check',
            'type' => 'other'
        );
    }

    /**
     * Salva par duplicado no banco
     */
    private static function save_duplicate_pair($scan_id, $user_id_1, $user_id_2, $comparison) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'eau_duplicate_pairs',
            array(
                'scan_id' => $scan_id,
                'user_id_1' => $user_id_1,
                'user_id_2' => $user_id_2,
                'similarity_score' => $comparison['score'],
                'match_details' => json_encode($comparison),
                'pair_status' => 'pending',
            ),
            array('%d', '%d', '%d', '%f', '%s', '%s')
        );
    }

    /**
     * Busca todas as exclusões
     */
    private static function get_exclusions() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT user_id_1, user_id_2 FROM {$wpdb->prefix}eau_duplicate_exclusions",
            ARRAY_A
        );

        $exclusions = array();
        foreach ($results as $row) {
            $key = self::make_exclusion_key($row['user_id_1'], $row['user_id_2']);
            $exclusions[$key] = true;
        }

        return $exclusions;
    }

    /**
     * Verifica se par está excluído
     */
    private static function is_excluded($user_id_1, $user_id_2, $exclusions) {
        $key = self::make_exclusion_key($user_id_1, $user_id_2);
        return isset($exclusions[$key]);
    }

    /**
     * Cria chave única para par (sempre menor ID primeiro)
     */
    private static function make_exclusion_key($user_id_1, $user_id_2) {
        $ids = array($user_id_1, $user_id_2);
        sort($ids);
        return $ids[0] . '_' . $ids[1];
    }

    /**
     * Retorna informações do último scan
     */
    public static function get_last_scan() {
        global $wpdb;

        return $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}eau_duplicate_scans
            ORDER BY scan_date DESC
            LIMIT 1"
        );
    }

    /**
     * Retorna progresso do scan atual
     */
    public static function get_scan_progress($scan_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eau_duplicate_scans WHERE scan_id = %d",
            $scan_id
        ));
    }
}
