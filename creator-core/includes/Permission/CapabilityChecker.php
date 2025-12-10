<?php
/**
 * Capability Checker
 *
 * @package CreatorCore
 */

namespace CreatorCore\Permission;

defined( 'ABSPATH' ) || exit;

/**
 * Class CapabilityChecker
 *
 * Checks user capabilities for Creator operations
 */
class CapabilityChecker {

    /**
     * Operation capability requirements
     *
     * @var array
     */
    private array $operation_requirements = [
        // Post operations
        'create_post'          => [ 'edit_posts', 'publish_posts' ],
        'edit_post'            => [ 'edit_posts' ],
        'delete_post'          => [ 'delete_posts' ],
        'publish_post'         => [ 'publish_posts' ],
        'update_post'          => [ 'edit_posts' ],

        // Page operations
        'create_page'          => [ 'edit_pages', 'publish_pages' ],
        'edit_page'            => [ 'edit_pages' ],
        'delete_page'          => [ 'delete_pages' ],
        'publish_page'         => [ 'publish_pages' ],
        'update_page'          => [ 'edit_pages' ],

        // Custom post type operations
        'create_cpt'           => [ 'edit_posts', 'publish_posts' ],
        'edit_cpt'             => [ 'edit_posts' ],

        // Media operations
        'upload_media'         => [ 'upload_files' ],
        'delete_media'         => [ 'delete_posts' ],

        // Elementor operations
        'add_elementor_widget' => [ 'edit_posts' ],
        'edit_elementor_page'  => [ 'edit_posts' ],
        'create_elementor_template' => [ 'edit_posts', 'publish_posts' ],

        // ACF operations
        'add_acf_field'        => [ 'manage_options' ],
        'edit_acf_field'       => [ 'manage_options' ],
        'delete_acf_field'     => [ 'manage_options' ],
        'create_acf_group'     => [ 'manage_options' ],

        // Rank Math operations
        'toggle_rank_math'     => [ 'manage_options' ],
        'edit_seo_settings'    => [ 'manage_options' ],

        // WooCommerce operations
        'create_product'       => [ 'edit_products', 'publish_products' ],
        'edit_product'         => [ 'edit_products' ],
        'manage_orders'        => [ 'edit_shop_orders' ],

        // WP Code operations
        'create_snippet'       => [ 'manage_options' ],
        'edit_snippet'         => [ 'manage_options' ],
        'delete_snippet'       => [ 'manage_options' ],

        // Theme operations
        'edit_theme'           => [ 'edit_themes' ],
        'customize_theme'      => [ 'edit_theme_options' ],

        // Plugin operations
        'manage_plugins'       => [ 'activate_plugins', 'install_plugins' ],

        // Options operations
        'edit_options'         => [ 'manage_options' ],
        'update_option'        => [ 'manage_options' ],
        'update_meta'          => [ 'edit_posts' ],

        // File system operations (Development)
        'read_file'            => [ 'manage_options' ],
        'write_file'           => [ 'manage_options' ],
        'delete_file'          => [ 'manage_options' ],
        'list_directory'       => [ 'manage_options' ],
        'search_files'         => [ 'manage_options' ],

        // Plugin development operations
        'create_plugin'        => [ 'manage_options', 'install_plugins' ],
        'activate_plugin'      => [ 'activate_plugins' ],
        'deactivate_plugin'    => [ 'activate_plugins' ],
        'delete_plugin'        => [ 'delete_plugins' ],
        'add_plugin_file'      => [ 'manage_options', 'install_plugins' ],

        // Code analysis operations
        'analyze_code'         => [ 'manage_options' ],
        'analyze_plugin'       => [ 'manage_options' ],
        'analyze_theme'        => [ 'manage_options' ],
        'debug_error'          => [ 'manage_options' ],
        'get_debug_log'        => [ 'manage_options' ],

        // Database operations
        'db_query'             => [ 'manage_options' ],
        'db_get_rows'          => [ 'manage_options' ],
        'db_insert'            => [ 'manage_options' ],
        'db_update'            => [ 'manage_options' ],
        'db_delete'            => [ 'manage_options' ],
        'db_create_table'      => [ 'manage_options' ],
        'db_info'              => [ 'manage_options' ],

        // Creator-specific operations
        'use_creator'          => [ 'use_creator' ],
        'manage_creator_chats' => [ 'manage_creator_chats' ],
        'view_creator_audit'   => [ 'view_creator_audit' ],
        'manage_creator_backups' => [ 'manage_creator_backups' ],
        'manage_creator_settings' => [ 'manage_creator_settings' ],
    ];

    /**
     * Role capability mapping
     *
     * @var array
     */
    private array $role_suggestions = [
        'manage_options'       => 'Administrator',
        'edit_themes'          => 'Administrator',
        'activate_plugins'     => 'Administrator',
        'edit_pages'           => 'Editor',
        'edit_posts'           => 'Author',
        'edit_products'        => 'Shop Manager',
        'use_creator'          => 'Creator Admin',
    ];

    /**
     * Register Creator capabilities
     *
     * @return void
     */
    public function register_capabilities(): void {
        // Capabilities are registered during activation
        // This method can be used to verify they exist
    }

    /**
     * Check if user can perform an operation
     *
     * @param string   $operation_type Operation type.
     * @param int|null $user_id        User ID (current user if null).
     * @return array Result with 'allowed', 'missing', 'required_role' keys.
     */
    public function check_operation_requirements( string $operation_type, ?int $user_id = null ): array {
        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return [
                'allowed'       => false,
                'reason'        => __( 'User not logged in', 'creator-core' ),
                'missing'       => [],
                'required_role' => 'Any authenticated user',
            ];
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [
                'allowed'       => false,
                'reason'        => __( 'User not found', 'creator-core' ),
                'missing'       => [],
                'required_role' => 'Any authenticated user',
            ];
        }

        // Super admins can do everything
        if ( is_super_admin( $user_id ) ) {
            return [
                'allowed'       => true,
                'missing'       => [],
                'required_role' => 'Super Admin',
            ];
        }

        // Get required capabilities for operation
        $required_caps = $this->get_operation_capabilities( $operation_type );

        if ( empty( $required_caps ) ) {
            // Unknown operation - default to manage_options for safety
            $required_caps = [ 'manage_options' ];
        }

        // Check each required capability
        $missing = [];
        foreach ( $required_caps as $cap ) {
            if ( ! user_can( $user_id, $cap ) ) {
                $missing[] = $cap;
            }
        }

        if ( ! empty( $missing ) ) {
            return [
                'allowed'       => false,
                'reason'        => sprintf(
                    /* translators: %s: List of missing capabilities */
                    __( 'Missing capabilities: %s', 'creator-core' ),
                    implode( ', ', $missing )
                ),
                'missing'       => $missing,
                'required_role' => $this->suggest_role( $missing ),
            ];
        }

        return [
            'allowed'       => true,
            'missing'       => [],
            'required_role' => implode( ', ', $user->roles ),
        ];
    }

    /**
     * Check if user can use Creator
     *
     * @param int|null $user_id User ID.
     * @return bool
     */
    public function can_use_creator( ?int $user_id = null ): bool {
        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        // Check if user has the use_creator capability
        if ( user_can( $user_id, 'use_creator' ) ) {
            return true;
        }

        // Check if user role is in allowed roles
        $allowed_roles = get_option( 'creator_allowed_roles', [ 'administrator', 'creator_admin' ] );
        $user          = get_userdata( $user_id );

        if ( $user && ! empty( $user->roles ) ) {
            foreach ( $user->roles as $role ) {
                if ( in_array( $role, $allowed_roles, true ) ) {
                    return true;
                }
            }
        }

        // Fallback: allow anyone who can edit posts (editors, authors, contributors with edit_posts)
        // This makes Creator accessible while still respecting operation-level restrictions
        if ( user_can( $user_id, 'edit_posts' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can manage Creator settings
     *
     * @param int|null $user_id User ID.
     * @return bool
     */
    public function can_manage_settings( ?int $user_id = null ): bool {
        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        return user_can( $user_id, 'manage_creator_settings' ) || user_can( $user_id, 'manage_options' );
    }

    /**
     * Check if user can view audit logs
     *
     * @param int|null $user_id User ID.
     * @return bool
     */
    public function can_view_audit( ?int $user_id = null ): bool {
        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        return user_can( $user_id, 'view_creator_audit' ) || user_can( $user_id, 'manage_options' );
    }

    /**
     * Check if user can manage backups
     *
     * @param int|null $user_id User ID.
     * @return bool
     */
    public function can_manage_backups( ?int $user_id = null ): bool {
        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        return user_can( $user_id, 'manage_creator_backups' ) || user_can( $user_id, 'manage_options' );
    }

    /**
     * Get capabilities required for an operation
     *
     * @param string $operation_type Operation type.
     * @return array
     */
    public function get_operation_capabilities( string $operation_type ): array {
        return $this->operation_requirements[ $operation_type ] ?? [];
    }

    /**
     * Get all registered operations
     *
     * @return array
     */
    public function get_all_operations(): array {
        return array_keys( $this->operation_requirements );
    }

    /**
     * Get operations available to a user
     *
     * @param int|null $user_id User ID.
     * @return array
     */
    public function get_user_operations( ?int $user_id = null ): array {
        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        $available = [];

        foreach ( $this->operation_requirements as $operation => $caps ) {
            $check = $this->check_operation_requirements( $operation, $user_id );
            if ( $check['allowed'] ) {
                $available[] = $operation;
            }
        }

        return $available;
    }

    /**
     * Add custom operation requirement
     *
     * @param string $operation_type Operation type.
     * @param array  $capabilities   Required capabilities.
     * @return void
     */
    public function add_operation( string $operation_type, array $capabilities ): void {
        $this->operation_requirements[ $operation_type ] = $capabilities;
    }

    /**
     * Suggest a role based on missing capabilities
     *
     * @param array $missing_caps Missing capabilities.
     * @return string
     */
    private function suggest_role( array $missing_caps ): string {
        foreach ( $missing_caps as $cap ) {
            if ( isset( $this->role_suggestions[ $cap ] ) ) {
                return $this->role_suggestions[ $cap ];
            }
        }

        return 'Administrator';
    }

    /**
     * Verify nonce for an action
     *
     * @param string $action      Nonce action.
     * @param string $nonce_field Nonce field name.
     * @return bool
     */
    public function verify_nonce( string $action, string $nonce_field = '_wpnonce' ): bool {
        $nonce = '';

        if ( isset( $_REQUEST[ $nonce_field ] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_field ] ) );
        } elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
        }

        return wp_verify_nonce( $nonce, $action );
    }

    /**
     * Check permission and return WP_Error on failure
     *
     * @param string   $operation_type Operation type.
     * @param int|null $user_id        User ID.
     * @return true|\WP_Error
     */
    public function check_permission( string $operation_type, ?int $user_id = null ) {
        $result = $this->check_operation_requirements( $operation_type, $user_id );

        if ( ! $result['allowed'] ) {
            return new \WP_Error(
                'creator_permission_denied',
                $result['reason'],
                [
                    'status'        => 403,
                    'missing'       => $result['missing'],
                    'required_role' => $result['required_role'],
                ]
            );
        }

        return true;
    }
}
