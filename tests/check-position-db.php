<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

// Buscar alguns usuarios e seus valores de mem_memberposition
$users = $wpdb->get_results("
    SELECT u.ID, u.display_name, um.meta_value as position
    FROM {$wpdb->users} u
    LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'mem_memberposition'
    WHERE um.meta_value IS NOT NULL AND um.meta_value != ''
    LIMIT 20
");

echo "Usuarios com mem_memberposition preenchido:\n";
foreach ($users as $user) {
    echo "  ID: {$user->ID} | Name: {$user->display_name} | Position: {$user->position}\n";
}

// Contar quantos tem o campo preenchido
$count = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'mem_memberposition' AND meta_value != ''
");
echo "\nTotal de usuarios com position preenchido: $count\n";

// Verificar valores distintos
echo "\nValores distintos de position:\n";
$values = $wpdb->get_results("
    SELECT meta_value, COUNT(*) as qty FROM {$wpdb->usermeta} WHERE meta_key = 'mem_memberposition' AND meta_value != '' GROUP BY meta_value
");
foreach ($values as $v) {
    echo "  {$v->meta_value}: {$v->qty} usuarios\n";
}

// Verificar se existe mem_position ao inves de mem_memberposition
echo "\n\nVerificando se existe 'mem_position' (outro nome):\n";
$mem_position_count = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'mem_position' AND meta_value != ''
");
echo "Total com mem_position: $mem_position_count\n";

if ($mem_position_count > 0) {
    $values2 = $wpdb->get_results("
        SELECT meta_value, COUNT(*) as qty FROM {$wpdb->usermeta} WHERE meta_key = 'mem_position' AND meta_value != '' GROUP BY meta_value LIMIT 10
    ");
    echo "Valores de mem_position:\n";
    foreach ($values2 as $v) {
        echo "  {$v->meta_value}: {$v->qty} usuarios\n";
    }
}
