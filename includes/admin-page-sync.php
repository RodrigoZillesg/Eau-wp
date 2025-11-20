<?php
/**
 * Seção de Sincronização de User Types
 */

// Previne acesso direto
if (!defined('WPINC')) {
    die;
}

// Busca estatísticas atuais
$current_stats = array(
    'superAdmin' => 0,
    'Admin' => 0,
    'institutionAdmin' => 0,
    'Member' => 0,
    'undefined' => 0,
    'total' => 0
);

if (class_exists('\EauSystem\Eau_User_Type_Sync')) {
    $current_stats = \EauSystem\Eau_User_Type_Sync::get_current_stats();
}
?>

<div class="eau-section">
    <h2>
        <span class="dashicons dashicons-update"></span>
        Sincronização de User Types
    </h2>

    <div class="eau-card">
        <h3>Estatísticas Atuais</h3>
        <p class="description">Distribuição atual dos tipos de usuários (mem_type)</p>

        <table class="widefat">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Super Admin</strong></td>
                    <td><?php echo $current_stats['superAdmin']; ?></td>
                </tr>
                <tr>
                    <td><strong>Admin</strong></td>
                    <td><?php echo $current_stats['Admin']; ?></td>
                </tr>
                <tr>
                    <td><strong>Institution Admin</strong></td>
                    <td><?php echo $current_stats['institutionAdmin']; ?></td>
                </tr>
                <tr>
                    <td><strong>Member</strong></td>
                    <td><?php echo $current_stats['Member']; ?></td>
                </tr>
                <tr class="eau-warning">
                    <td><strong>Não Definido</strong></td>
                    <td><?php echo $current_stats['undefined']; ?></td>
                </tr>
                <tr class="eau-total">
                    <td><strong>Total de Usuários</strong></td>
                    <td><strong><?php echo $current_stats['total']; ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="eau-card">
        <h3>Como Funciona a Sincronização</h3>
        <div class="eau-info-box">
            <p><strong>Regra de Sincronização:</strong></p>
            <ol>
                <li>O sistema busca todos os posts do tipo <code>membership</code></li>
                <li>Para cada post, lê o campo <code>primary_contacts_email</code></li>
                <li>Usuários com email encontrado nos posts recebem: <strong>mem_type = institutionAdmin</strong></li>
                <li>Todos os outros usuários recebem: <strong>mem_type = Member</strong></li>
            </ol>
            <p class="description">
                <strong>Importante:</strong> Esta sincronização sobrescreve o mem_type atual de TODOS os usuários,
                exceto aqueles que já são superAdmin ou Admin (esses permanecem inalterados se você quiser - podemos ajustar).
            </p>
        </div>
    </div>

    <div class="eau-card">
        <h3>Executar Sincronização</h3>
        <p>Clique no botão abaixo para sincronizar os tipos de todos os usuários baseado nos posts de membership.</p>

        <button type="button" id="eau-sync-user-types" class="button button-primary button-large">
            <span class="dashicons dashicons-update"></span>
            Sincronizar Todos os User Types
        </button>

        <div id="eau-sync-progress" style="display: none; margin-top: 20px;">
            <div class="eau-spinner"></div>
            <p>Sincronizando usuários...</p>
        </div>

        <div id="eau-sync-result" style="display: none; margin-top: 20px;"></div>
    </div>
</div>

<style>
.eau-warning td {
    background: #fff3cd;
    color: #856404;
}
.eau-total td {
    background: #e8f4f8;
    font-weight: bold;
}
.widefat th,
.widefat td {
    padding: 12px;
}
</style>
