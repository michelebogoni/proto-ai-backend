<?php
/**
 * Role Mapper
 *
 * @package CreatorCore
 */

namespace CreatorCore\Permission;

defined( 'ABSPATH' ) || exit;

/**
 * Class RoleMapper
 *
 * Maps WordPress roles to Creator capabilities
 */
class RoleMapper {

    /**
     * Default role capabilities for Creator
     *
     * @var array
     */
    private array $default_role_caps = [
        'administrator' => [
            'use_creator'            => true,
            'manage_creator_chats'   => true,
            'view_creator_audit'     => true,
            'manage_creator_backups' => true,
            'manage_creator_settings' => true,
        ],
        'creator_admin' => [
            'use_creator'            => true,
            'manage_creator_chats'   => true,
            'view_creator_audit'     => true,
            'manage_creator_backups' => true,
            'manage_creator_settings' => false,
        ],
        'editor' => [
            'use_creator'            => true,
            'manage_creator_chats'   => true,
            'view_creator_audit'     => false,
            'manage_creator_backups' => false,
            'manage_creator_settings' => false,
        ],
        'author' => [
            'use_creator'            => false,
            'manage_creator_chats'   => false,
            'view_creator_audit'     => false,
            'manage_creator_backups' => false,
            'manage_creator_settings' => false,
        ],
    ];

    /**
     * Get available roles
     *
     * @return array
     */
    public function get_available_roles(): array {
        global $wp_roles;

        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new \WP_Roles();
        }

        $roles = [];

        foreach ( $wp_roles->roles as $role_slug => $role_data ) {
            $roles[ $role_slug ] = [
                'name'         => translate_user_role( $role_data['name'] ),
                'capabilities' => $role_data['capabilities'],
            ];
        }

        return $roles;
    }

    /**
     * Get Creator capabilities for a role
     *
     * @param string $role_slug Role slug.
     * @return array
     */
    public function get_role_creator_caps( string $role_slug ): array {
        $role = get_role( $role_slug );

        if ( ! $role ) {
            return [];
        }

        $creator_caps = [
            'use_creator',
            'manage_creator_chats',
            'view_creator_audit',
            'manage_creator_backups',
            'manage_creator_settings',
        ];

        $result = [];

        foreach ( $creator_caps as $cap ) {
            $result[ $cap ] = $role->has_cap( $cap );
        }

        return $result;
    }

    /**
     * Set Creator capabilities for a role
     *
     * @param string $role_slug    Role slug.
     * @param array  $capabilities Capabilities to set.
     * @return bool
     */
    public function set_role_creator_caps( string $role_slug, array $capabilities ): bool {
        $role = get_role( $role_slug );

        if ( ! $role ) {
            return false;
        }

        $valid_caps = [
            'use_creator',
            'manage_creator_chats',
            'view_creator_audit',
            'manage_creator_backups',
            'manage_creator_settings',
        ];

        foreach ( $valid_caps as $cap ) {
            if ( isset( $capabilities[ $cap ] ) ) {
                if ( $capabilities[ $cap ] ) {
                    $role->add_cap( $cap );
                } else {
                    $role->remove_cap( $cap );
                }
            }
        }

        return true;
    }

    /**
     * Reset role capabilities to defaults
     *
     * @param string|null $role_slug Role slug (null for all roles).
     * @return bool
     */
    public function reset_to_defaults( ?string $role_slug = null ): bool {
        if ( $role_slug !== null ) {
            if ( isset( $this->default_role_caps[ $role_slug ] ) ) {
                return $this->set_role_creator_caps( $role_slug, $this->default_role_caps[ $role_slug ] );
            }
            return false;
        }

        // Reset all roles
        foreach ( $this->default_role_caps as $role => $caps ) {
            $this->set_role_creator_caps( $role, $caps );
        }

        return true;
    }

    /**
     * Get users by Creator capability
     *
     * @param string $capability Capability to check.
     * @return array
     */
    public function get_users_with_capability( string $capability ): array {
        $users = get_users( [
            'capability' => $capability,
            'fields'     => [ 'ID', 'user_login', 'user_email', 'display_name' ],
        ] );

        return $users;
    }

    /**
     * Get all roles that can use Creator
     *
     * @return array
     */
    public function get_creator_enabled_roles(): array {
        $all_roles    = $this->get_available_roles();
        $enabled_roles = [];

        foreach ( $all_roles as $role_slug => $role_data ) {
            $caps = $this->get_role_creator_caps( $role_slug );
            if ( ! empty( $caps['use_creator'] ) ) {
                $enabled_roles[ $role_slug ] = $role_data['name'];
            }
        }

        return $enabled_roles;
    }

    /**
     * Get capability description
     *
     * @param string $capability Capability slug.
     * @return string
     */
    public function get_capability_description( string $capability ): string {
        $descriptions = [
            'use_creator'             => __( 'Use the Creator chat interface', 'creator-core' ),
            'manage_creator_chats'    => __( 'Manage and view all Creator chats', 'creator-core' ),
            'view_creator_audit'      => __( 'View Creator audit logs', 'creator-core' ),
            'manage_creator_backups'  => __( 'Manage Creator backups and snapshots', 'creator-core' ),
            'manage_creator_settings' => __( 'Manage Creator plugin settings', 'creator-core' ),
        ];

        return $descriptions[ $capability ] ?? $capability;
    }

    /**
     * Get all Creator capabilities with descriptions
     *
     * @return array
     */
    public function get_all_creator_capabilities(): array {
        $caps = [
            'use_creator',
            'manage_creator_chats',
            'view_creator_audit',
            'manage_creator_backups',
            'manage_creator_settings',
        ];

        $result = [];

        foreach ( $caps as $cap ) {
            $result[ $cap ] = [
                'slug'        => $cap,
                'description' => $this->get_capability_description( $cap ),
            ];
        }

        return $result;
    }
}
