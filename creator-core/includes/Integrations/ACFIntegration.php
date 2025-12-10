<?php
/**
 * ACF Integration
 *
 * @package CreatorCore
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class ACFIntegration
 *
 * Handles Advanced Custom Fields operations
 */
class ACFIntegration {

    /**
     * Check if ACF is available
     *
     * @return bool
     */
    public function is_available(): bool {
        return class_exists( 'ACF' ) || function_exists( 'acf_get_setting' );
    }

    /**
     * Check if ACF Pro is available
     *
     * @return bool
     */
    public function is_pro(): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return defined( 'ACF_PRO' ) && ACF_PRO;
    }

    /**
     * Get ACF version
     *
     * @return string|null
     */
    public function get_version(): ?string {
        if ( ! $this->is_available() ) {
            return null;
        }

        return acf_get_setting( 'version' );
    }

    /**
     * Get all field groups
     *
     * @return array
     */
    public function get_field_groups(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $groups = acf_get_field_groups();
        $result = [];

        foreach ( $groups as $group ) {
            $result[] = [
                'key'      => $group['key'],
                'title'    => $group['title'],
                'fields'   => $this->get_group_fields( $group['key'] ),
                'location' => $group['location'],
                'active'   => $group['active'],
            ];
        }

        return $result;
    }

    /**
     * Get fields for a field group
     *
     * @param string $group_key Field group key.
     * @return array
     */
    public function get_group_fields( string $group_key ): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $fields = acf_get_fields( $group_key );

        if ( ! $fields ) {
            return [];
        }

        $result = [];

        foreach ( $fields as $field ) {
            $result[] = [
                'key'          => $field['key'],
                'name'         => $field['name'],
                'label'        => $field['label'],
                'type'         => $field['type'],
                'required'     => $field['required'] ?? false,
                'instructions' => $field['instructions'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Get field value for a post
     *
     * @param string   $field_name Field name or key.
     * @param int|null $post_id    Post ID (current post if null).
     * @return mixed
     */
    public function get_field_value( string $field_name, ?int $post_id = null ) {
        if ( ! $this->is_available() ) {
            return null;
        }

        return get_field( $field_name, $post_id );
    }

    /**
     * Update field value for a post
     *
     * @param string   $field_name Field name or key.
     * @param mixed    $value      Field value.
     * @param int|null $post_id    Post ID (current post if null).
     * @return bool
     */
    public function update_field_value( string $field_name, $value, ?int $post_id = null ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return update_field( $field_name, $value, $post_id );
    }

    /**
     * Get all field values for a post
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public function get_all_fields( int $post_id ): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $fields = get_field_objects( $post_id );

        if ( ! $fields ) {
            return [];
        }

        $result = [];

        foreach ( $fields as $field ) {
            $result[ $field['name'] ] = [
                'key'   => $field['key'],
                'label' => $field['label'],
                'type'  => $field['type'],
                'value' => $field['value'],
            ];
        }

        return $result;
    }

    /**
     * Create a new field group
     *
     * @param array $group_data Field group data.
     * @return string|false Field group key or false on failure.
     */
    public function create_field_group( array $group_data ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        $defaults = [
            'key'                   => 'group_' . uniqid(),
            'title'                 => 'New Field Group',
            'fields'                => [],
            'location'              => [],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
        ];

        $group = wp_parse_args( $group_data, $defaults );

        // Save field group
        $result = acf_update_field_group( $group );

        if ( $result ) {
            return $group['key'];
        }

        return false;
    }

    /**
     * Add field to a field group
     *
     * @param string $group_key  Field group key.
     * @param array  $field_data Field data.
     * @return string|false Field key or false on failure.
     */
    public function add_field( string $group_key, array $field_data ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        $defaults = [
            'key'           => 'field_' . uniqid(),
            'label'         => 'New Field',
            'name'          => 'new_field',
            'type'          => 'text',
            'parent'        => $group_key,
            'required'      => 0,
            'instructions'  => '',
            'default_value' => '',
        ];

        $field = wp_parse_args( $field_data, $defaults );

        $result = acf_update_field( $field );

        if ( $result ) {
            return $field['key'];
        }

        return false;
    }

    /**
     * Delete a field group
     *
     * @param string $group_key Field group key.
     * @return bool
     */
    public function delete_field_group( string $group_key ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return acf_delete_field_group( $group_key );
    }

    /**
     * Get available field types
     *
     * @return array
     */
    public function get_field_types(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $types = acf_get_field_types();
        $result = [];

        foreach ( $types as $category => $fields ) {
            foreach ( $fields as $type => $label ) {
                $result[ $type ] = [
                    'type'     => $type,
                    'label'    => $label,
                    'category' => $category,
                ];
            }
        }

        return $result;
    }

    /**
     * Export field group to JSON
     *
     * @param string $group_key Field group key.
     * @return array|null
     */
    public function export_field_group( string $group_key ): ?array {
        if ( ! $this->is_available() ) {
            return null;
        }

        $group = acf_get_field_group( $group_key );

        if ( ! $group ) {
            return null;
        }

        $group['fields'] = acf_get_fields( $group_key );

        return $group;
    }

    /**
     * Import field group from JSON
     *
     * @param array $group_data Field group data.
     * @return string|false Field group key or false on failure.
     */
    public function import_field_group( array $group_data ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        // Remove ID to create new
        unset( $group_data['ID'] );

        $fields = $group_data['fields'] ?? [];
        unset( $group_data['fields'] );

        // Import group
        $group = acf_import_field_group( $group_data );

        if ( ! $group ) {
            return false;
        }

        // Import fields
        foreach ( $fields as $field ) {
            $field['parent'] = $group['key'];
            acf_import_field( $field );
        }

        return $group['key'];
    }
}
