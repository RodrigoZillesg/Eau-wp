<?php
/**
 * Script temporário para encontrar admins com múltiplas instituições
 * Acesse via: /wp-content/plugins/eau-system/find-admins.php
 * DELETAR após usar!
 */

// Carrega o WordPress
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

// Verifica se é admin
if (!current_user_can('manage_options')) {
    wp_die('Acesso negado. Apenas administradores podem acessar este script.');
}

global $wpdb;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admins com Múltiplas Instituições</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        h1 {
            color: #1d2327;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 10px;
        }
        .admin-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .admin-card h2 {
            margin-top: 0;
            color: #2271b1;
        }
        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        .info-label {
            font-weight: 600;
            color: #50575e;
        }
        .info-value {
            color: #1d2327;
        }
        .institutions-list {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }
        .institutions-list li {
            padding: 8px 12px;
            margin: 5px 0;
            background: #f6f7f7;
            border-left: 4px solid #2271b1;
            border-radius: 2px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            background: #2271b1;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .notice {
            background: #fff8e5;
            border-left: 4px solid #dba617;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background: #edfaef;
            border-left: 4px solid #00a32a;
        }
        .error {
            background: #fcf0f1;
            border-left: 4px solid #d63638;
        }
    </style>
</head>
<body>
    <h1>🔍 Admins que Gerenciam Múltiplas Instituições</h1>

    <?php
    $results = $wpdb->get_results("
        SELECT
            u.ID,
            u.user_login,
            u.user_email,
            u.display_name,
            um_userid.meta_value as mem_userid,
            COUNT(DISTINCT p.ID) as total_institutions,
            GROUP_CONCAT(p.post_title ORDER BY p.post_title SEPARATOR '|||') as institution_names,
            GROUP_CONCAT(p.ID ORDER BY p.post_title SEPARATOR ',') as institution_ids,
            GROUP_CONCAT(pm_company.meta_value ORDER BY p.post_title SEPARATOR ', ') as company_ids
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} um_userid ON u.ID = um_userid.user_id AND um_userid.meta_key = 'mem_userid'
        INNER JOIN {$wpdb->postmeta} pm_contact ON pm_contact.meta_key = 'ins_company_primary_contact' AND pm_contact.meta_value = um_userid.meta_value
        INNER JOIN {$wpdb->posts} p ON pm_contact.post_id = p.ID AND p.post_type = 'institutions' AND p.post_status = 'publish'
        LEFT JOIN {$wpdb->postmeta} pm_company ON p.ID = pm_company.post_id AND pm_company.meta_key = 'ins_company_id'
        GROUP BY u.ID, u.user_login, u.user_email, u.display_name, um_userid.meta_value
        HAVING total_institutions > 1
        ORDER BY total_institutions DESC
    ");

    if (empty($results)) {
        echo '<div class="notice error">';
        echo '<p><strong>❌ Nenhum admin com múltiplas instituições encontrado!</strong></p>';
        echo '<p>Vou mostrar TODOS os admins e quantas instituições cada um gerencia:</p>';
        echo '</div>';

        // Mostra todos os admins
        $all_admins = $wpdb->get_results("
            SELECT
                u.ID,
                u.user_login,
                u.user_email,
                u.display_name,
                um_userid.meta_value as mem_userid,
                COUNT(DISTINCT p.ID) as total_institutions,
                GROUP_CONCAT(p.post_title ORDER BY p.post_title SEPARATOR '|||') as institution_names,
                GROUP_CONCAT(pm_company.meta_value ORDER BY p.post_title SEPARATOR ', ') as company_ids
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um_userid ON u.ID = um_userid.user_id AND um_userid.meta_key = 'mem_userid'
            INNER JOIN {$wpdb->postmeta} pm_contact ON pm_contact.meta_key = 'ins_company_primary_contact' AND pm_contact.meta_value = um_userid.meta_value
            INNER JOIN {$wpdb->posts} p ON pm_contact.post_id = p.ID AND p.post_type = 'institutions' AND p.post_status = 'publish'
            LEFT JOIN {$wpdb->postmeta} pm_company ON p.ID = pm_company.post_id AND pm_company.meta_key = 'ins_company_id'
            GROUP BY u.ID, u.user_login, u.user_email, u.display_name, um_userid.meta_value
            ORDER BY total_institutions DESC
        ");

        if (empty($all_admins)) {
            echo '<div class="notice error">';
            echo '<p><strong>⚠️ NENHUM admin encontrado via ins_company_primary_contact!</strong></p>';
            echo '<p>Isso significa que o relacionamento correto ainda não está sendo usado no banco de dados.</p>';
            echo '</div>';
        } else {
            foreach ($all_admins as $admin) {
                $institutions = explode('|||', $admin->institution_names);
                ?>
                <div class="admin-card">
                    <h2>
                        <?php echo esc_html($admin->display_name); ?>
                        <span class="badge"><?php echo $admin->total_institutions; ?> Instituição(ões)</span>
                    </h2>

                    <div class="info-row">
                        <div class="info-label">ID:</div>
                        <div class="info-value"><?php echo $admin->ID; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Login:</div>
                        <div class="info-value"><?php echo esc_html($admin->user_login); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><?php echo esc_html($admin->user_email); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">mem_userid:</div>
                        <div class="info-value"><code><?php echo esc_html($admin->mem_userid); ?></code></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Company IDs:</div>
                        <div class="info-value"><code><?php echo esc_html($admin->company_ids); ?></code></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Instituições:</div>
                        <div class="info-value">
                            <ul class="institutions-list">
                                <?php foreach ($institutions as $name): ?>
                                    <li><?php echo esc_html($name); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
    } else {
        echo '<div class="notice success">';
        echo '<p><strong>✅ Encontrados ' . count($results) . ' admin(s) com múltiplas instituições!</strong></p>';
        echo '</div>';

        foreach ($results as $admin) {
            $institutions = explode('|||', $admin->institution_names);
            ?>
            <div class="admin-card">
                <h2>
                    👤 <?php echo esc_html($admin->display_name); ?>
                    <span class="badge"><?php echo $admin->total_institutions; ?> Instituições</span>
                </h2>

                <div class="info-row">
                    <div class="info-label">ID:</div>
                    <div class="info-value"><?php echo $admin->ID; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Login:</div>
                    <div class="info-value"><strong><?php echo esc_html($admin->user_login); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo esc_html($admin->user_email); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">mem_userid:</div>
                    <div class="info-value"><code><?php echo esc_html($admin->mem_userid); ?></code></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Company IDs:</div>
                    <div class="info-value"><code><?php echo esc_html($admin->company_ids); ?></code></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Instituições:</div>
                    <div class="info-value">
                        <ul class="institutions-list">
                            <?php foreach ($institutions as $name): ?>
                                <li>📍 <?php echo esc_html($name); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php
        }

        echo '<div class="notice success">';
        echo '<p><strong>🎯 Para testar: Faça login com um dos usuários acima e acesse o Dashboard!</strong></p>';
        echo '</div>';
    }
    ?>

    <div class="notice">
        <p><strong>⚠️ IMPORTANTE:</strong> Delete este arquivo após usar!</p>
        <p>Arquivo: <code>/wp-content/plugins/eau-system/find-admins.php</code></p>
    </div>
</body>
</html>
