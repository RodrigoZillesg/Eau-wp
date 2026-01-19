<?php
/**
 * Script para sincronizar dados de Membership do CSV com o banco de dados
 *
 * Execução: Acessar via browser
 * URL: http://eau-site.local/wp-content/plugins/eau-system/scripts/sync-membership-data.php?token=eau2024sync
 */

// Token de segurança simples
$valid_token = 'eau2024sync';
if (!isset($_GET['token']) || $_GET['token'] !== $valid_token) {
    die('Acesso negado. Token inválido. Use: ?token=eau2024sync');
}

// Carrega o WordPress
$wp_load_path = dirname(__FILE__) . '/../../../../wp-load.php';

if (!file_exists($wp_load_path)) {
    die('WordPress não encontrado em: ' . $wp_load_path);
}

require_once $wp_load_path;

// Configurações - CSV está em C:\Users\rrzil\Local Sites\eau-site\logs\
$csv_file = dirname(__FILE__) . '/../../../../../../logs/MembershipDetails.csv';

if (!file_exists($csv_file)) {
    die('Arquivo CSV não encontrado: ' . $csv_file);
}

// Output
header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family: monospace; font-size: 12px;'>";
echo "=======================================================\n";
echo "SINCRONIZAÇÃO DE MEMBERSHIP DATA\n";
echo "=======================================================\n\n";

// Lê o CSV
$csv_data = array();
$handle = fopen($csv_file, 'r');
$headers = fgetcsv($handle); // Primeira linha = headers

echo "Headers do CSV: " . implode(', ', $headers) . "\n\n";

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) >= 9) {
        $csv_data[] = array(
            'id' => $row[0],
            'start_date' => $row[1],
            'expiry_date' => $row[2],
            'user_id' => $row[3],
            'total_staff' => $row[4],
            'membership_type' => $row[5],
            'company_id' => $row[6],
            'member_name' => $row[7],
            'campus_name' => $row[8],
        );
    }
}
fclose($handle);

echo "Total de registros no CSV: " . count($csv_data) . "\n\n";

// Busca todas as instituições existentes no banco
$existing_institutions = array();
$query = new WP_Query(array(
    'post_type' => 'institutions',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
));

foreach ($query->posts as $post_id) {
    $company_id = get_post_meta($post_id, 'ins_company_id', true);
    if (!empty($company_id)) {
        $existing_institutions[$company_id] = $post_id;
    }
}

echo "Total de instituições existentes no banco: " . count($existing_institutions) . "\n\n";

// Contadores
$updated = 0;
$created = 0;
$errors = array();

echo "-------------------------------------------------------\n";
echo "PROCESSANDO REGISTROS...\n";
echo "-------------------------------------------------------\n\n";

foreach ($csv_data as $row) {
    $company_id = trim($row['company_id']);

    if (empty($company_id)) {
        $errors[] = "Company ID vazio para: " . $row['member_name'];
        continue;
    }

    // Determina o nome da instituição (usa Campus Name se disponível, senão Member Name)
    $institution_name = !empty($row['campus_name']) ? trim($row['campus_name']) : trim($row['member_name']);

    // Converte datas do formato DD/MM/YYYY para YYYY-MM-DD
    $start_date = '';
    if (!empty($row['start_date'])) {
        $date_parts = explode('/', $row['start_date']);
        if (count($date_parts) === 3) {
            $start_date = $date_parts[2] . '-' . str_pad($date_parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
        }
    }

    $expiry_date = '';
    if (!empty($row['expiry_date'])) {
        $date_parts = explode('/', $row['expiry_date']);
        if (count($date_parts) === 3) {
            $expiry_date = $date_parts[2] . '-' . str_pad($date_parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
        }
    }

    // Prepara os meta fields
    $meta_fields = array(
        'ins_company_id' => $company_id,
        'ins_company_name' => $institution_name,
        'ins_member_start_date' => $start_date,
        'ins_member_expire_date' => $expiry_date,
        'ins_total_staff' => intval($row['total_staff']),
        'ins_membership_type' => trim($row['membership_type']),
        'ins_company_primary_contact' => trim($row['user_id']),
        'ins_status' => 'active',
    );

    // Determina o tipo de instituição baseado no membership_type
    $membership_type = trim($row['membership_type']);
    if (strpos($membership_type, 'College') !== false) {
        $meta_fields['ins_type'] = 'College';
    } elseif (strpos($membership_type, 'Corporate') !== false || strpos($membership_type, 'Affiliate') !== false) {
        $meta_fields['ins_type'] = 'Corporate affiliate';
    }

    if (isset($existing_institutions[$company_id])) {
        // ATUALIZA instituição existente
        $post_id = $existing_institutions[$company_id];

        // Atualiza título do post
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $institution_name,
        ));

        // Atualiza meta fields
        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        echo "[UPDATED] ID: $post_id | Company: $company_id | $institution_name\n";
        $updated++;

        // Remove da lista para identificar quais não foram processadas
        unset($existing_institutions[$company_id]);

    } else {
        // CRIA nova instituição
        $post_id = wp_insert_post(array(
            'post_type' => 'institutions',
            'post_title' => $institution_name,
            'post_status' => 'publish',
            'post_author' => 1,
        ));

        if (is_wp_error($post_id)) {
            $errors[] = "Erro ao criar instituição: $institution_name - " . $post_id->get_error_message();
            continue;
        }

        // Adiciona meta fields
        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        echo "[CREATED] ID: $post_id | Company: $company_id | $institution_name\n";
        $created++;
    }
}

// Resumo será exibido após as deleções

// Instituições que estão no banco mas não no CSV - DELETAR
$deleted = 0;
if (count($existing_institutions) > 0) {
    echo "-------------------------------------------------------\n";
    echo "DELETANDO INSTITUIÇÕES QUE NÃO ESTÃO NO CSV (" . count($existing_institutions) . ")\n";
    echo "-------------------------------------------------------\n\n";

    foreach ($existing_institutions as $company_id => $post_id) {
        $post = get_post($post_id);
        $post_title = $post ? $post->post_title : 'Unknown';

        // Deleta permanentemente (não move para lixeira)
        $result = wp_delete_post($post_id, true);

        if ($result) {
            echo "[DELETED] ID: $post_id | Company: $company_id | $post_title\n";
            $deleted++;
        } else {
            echo "[DELETE FAILED] ID: $post_id | Company: $company_id | $post_title\n";
            $errors[] = "Falha ao deletar: $post_title (ID: $post_id)";
        }
    }
}

echo "\n-------------------------------------------------------\n";
echo "RESUMO FINAL\n";
echo "-------------------------------------------------------\n\n";

echo "Instituições atualizadas: $updated\n";
echo "Instituições criadas: $created\n";
echo "Instituições deletadas: $deleted\n";
echo "Total no banco após sincronização: " . ($updated + $created) . "\n\n";

if (count($errors) > 0) {
    echo "-------------------------------------------------------\n";
    echo "ERROS (" . count($errors) . ")\n";
    echo "-------------------------------------------------------\n\n";

    foreach ($errors as $error) {
        echo "[ERROR] $error\n";
    }
}

echo "\n=======================================================\n";
echo "SINCRONIZAÇÃO CONCLUÍDA\n";
echo "=======================================================\n";
echo "</pre>";
