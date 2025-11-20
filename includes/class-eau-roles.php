<?php
namespace EauSystem;

/**
 * Classe para gerenciar roles customizados do sistema
 */
class Eau_Roles {

    /**
     * Registra roles customizados
     */
    public static function register_custom_roles() {
        // Admin do sistema (não admin do WP)
        add_role('eau_admin', 'Administrador Eau', array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
        ));

        // Administrador de instituição
        add_role('eau_institution_admin', 'Administrador de Instituição', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ));

        // Membro
        add_role('eau_member', 'Membro', array(
            'read' => true,
        ));
    }

    /**
     * Remove roles customizados (usado na desinstalação)
     */
    public static function remove_custom_roles() {
        remove_role('eau_admin');
        remove_role('eau_institution_admin');
        remove_role('eau_member');
    }

    /**
     * Retorna lista de roles disponíveis para importação
     */
    public static function get_available_roles() {
        return array(
            'administrator' => 'Super Admin (Administrador WP)',
            'eau_admin' => 'Admin (Administrador Eau)',
            'eau_institution_admin' => 'Administrador de Instituição',
            'eau_member' => 'Membro',
            'subscriber' => 'Assinante'
        );
    }
}
