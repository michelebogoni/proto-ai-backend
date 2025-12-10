<?php
/**
 * WordPress function stubs for unit testing
 *
 * @package CreatorCore
 */

// Global variables.
global $wpdb;

/**
 * Mock wpdb class
 */
class wpdb {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $options = 'wp_options';
    public $last_error = '';
    public $insert_id = 0;

    private $mock_results = [];
    private $mock_row = null;

    public function set_mock_results( $results ) {
        $this->mock_results = $results;
    }

    public function set_mock_row( $row ) {
        $this->mock_row = $row;
    }

    public function prepare( $query, ...$args ) {
        return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
    }

    public function get_results( $query, $output = OBJECT ) {
        return $this->mock_results;
    }

    public function get_row( $query, $output = OBJECT, $y = 0 ) {
        return $this->mock_row;
    }

    public function get_var( $query = null, $x = 0, $y = 0 ) {
        return $this->mock_row;
    }

    public function query( $query ) {
        return true;
    }

    public function insert( $table, $data, $format = null ) {
        $this->insert_id = rand( 1, 1000 );
        return true;
    }

    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        return 1;
    }

    public function delete( $table, $where, $where_format = null ) {
        return 1;
    }
}

$wpdb = new wpdb();

// Define constants.
if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'ARRAY_N' ) ) {
    define( 'ARRAY_N', 'ARRAY_N' );
}

// Options storage.
$_wp_options = [];

/**
 * Get option
 */
function get_option( $option, $default = false ) {
    global $_wp_options;
    return isset( $_wp_options[ $option ] ) ? $_wp_options[ $option ] : $default;
}

/**
 * Update option
 */
function update_option( $option, $value, $autoload = null ) {
    global $_wp_options;
    $_wp_options[ $option ] = $value;
    return true;
}

/**
 * Delete option
 */
function delete_option( $option ) {
    global $_wp_options;
    unset( $_wp_options[ $option ] );
    return true;
}

// Transients storage.
$_wp_transients = [];

/**
 * Get transient
 */
function get_transient( $transient ) {
    global $_wp_transients;
    return isset( $_wp_transients[ $transient ] ) ? $_wp_transients[ $transient ] : false;
}

/**
 * Set transient
 */
function set_transient( $transient, $value, $expiration = 0 ) {
    global $_wp_transients;
    $_wp_transients[ $transient ] = $value;
    return true;
}

/**
 * Delete transient
 */
function delete_transient( $transient ) {
    global $_wp_transients;
    unset( $_wp_transients[ $transient ] );
    return true;
}

/**
 * Current time
 */
function current_time( $type, $gmt = 0 ) {
    if ( $type === 'mysql' ) {
        return date( 'Y-m-d H:i:s' );
    }
    return time();
}

/**
 * wp_json_encode
 */
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
    return json_encode( $data, $options, $depth );
}

/**
 * Get current user ID
 */
function get_current_user_id() {
    return 1;
}

/**
 * Current user can
 */
function current_user_can( $capability ) {
    return true;
}

/**
 * User can
 */
function user_can( $user, $capability ) {
    return true;
}

/**
 * Is user logged in
 */
function is_user_logged_in() {
    return true;
}

/**
 * Get user by
 */
function get_user_by( $field, $value ) {
    return (object) [
        'ID' => 1,
        'user_login' => 'admin',
        'user_email' => 'admin@example.com',
        'display_name' => 'Admin',
        'roles' => [ 'administrator' ],
    ];
}

/**
 * Get userdata
 */
function get_userdata( $user_id ) {
    return get_user_by( 'ID', $user_id );
}

/**
 * wp_get_current_user
 */
function wp_get_current_user() {
    return get_user_by( 'ID', 1 );
}

/**
 * Sanitize text field
 */
function sanitize_text_field( $str ) {
    return trim( strip_tags( $str ) );
}

/**
 * Sanitize textarea field
 */
function sanitize_textarea_field( $str ) {
    return trim( strip_tags( $str ) );
}

/**
 * Sanitize key
 */
function sanitize_key( $key ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

/**
 * Absint
 */
function absint( $maybeint ) {
    return abs( (int) $maybeint );
}

/**
 * Esc html
 */
function esc_html( $text ) {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Esc attr
 */
function esc_attr( $text ) {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Esc url
 */
function esc_url( $url ) {
    return filter_var( $url, FILTER_SANITIZE_URL );
}

/**
 * wp_kses_post
 */
function wp_kses_post( $data ) {
    return strip_tags( $data, '<a><strong><em><p><br><ul><ol><li>' );
}

/**
 * Is WP error
 */
function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

/**
 * WP Error class
 */
class WP_Error {
    private $errors = [];
    private $error_data = [];

    public function __construct( $code = '', $message = '', $data = '' ) {
        if ( ! empty( $code ) ) {
            $this->errors[ $code ][] = $message;
            if ( ! empty( $data ) ) {
                $this->error_data[ $code ] = $data;
            }
        }
    }

    public function get_error_code() {
        $codes = array_keys( $this->errors );
        return ! empty( $codes ) ? $codes[0] : '';
    }

    public function get_error_message( $code = '' ) {
        if ( empty( $code ) ) {
            $code = $this->get_error_code();
        }
        return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
    }

    public function get_error_messages( $code = '' ) {
        if ( empty( $code ) ) {
            $all_messages = [];
            foreach ( $this->errors as $messages ) {
                $all_messages = array_merge( $all_messages, $messages );
            }
            return $all_messages;
        }
        return isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : [];
    }

    public function add( $code, $message, $data = '' ) {
        $this->errors[ $code ][] = $message;
        if ( ! empty( $data ) ) {
            $this->error_data[ $code ] = $data;
        }
    }

    public function has_errors() {
        return ! empty( $this->errors );
    }
}

/**
 * Hooks storage
 */
$_wp_actions = [];
$_wp_filters = [];

/**
 * Add action
 */
function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
    global $_wp_actions;
    $_wp_actions[ $tag ][] = [
        'function' => $function_to_add,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];
    return true;
}

/**
 * Do action
 */
function do_action( $tag, ...$args ) {
    global $_wp_actions;
    if ( isset( $_wp_actions[ $tag ] ) ) {
        foreach ( $_wp_actions[ $tag ] as $action ) {
            call_user_func_array( $action['function'], array_slice( $args, 0, $action['accepted_args'] ) );
        }
    }
}

/**
 * Add filter
 */
function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
    global $_wp_filters;
    $_wp_filters[ $tag ][] = [
        'function' => $function_to_add,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];
    return true;
}

/**
 * Apply filters
 */
function apply_filters( $tag, $value, ...$args ) {
    global $_wp_filters;
    if ( isset( $_wp_filters[ $tag ] ) ) {
        foreach ( $_wp_filters[ $tag ] as $filter ) {
            $value = call_user_func_array( $filter['function'], array_merge( [ $value ], array_slice( $args, 0, $filter['accepted_args'] - 1 ) ) );
        }
    }
    return $value;
}

/**
 * Remove action
 */
function remove_action( $tag, $function_to_remove, $priority = 10 ) {
    return true;
}

/**
 * Remove filter
 */
function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
    return true;
}

/**
 * Did action
 */
function did_action( $tag ) {
    return 1;
}

/**
 * Has filter
 */
function has_filter( $tag, $function_to_check = false ) {
    global $_wp_filters;
    return isset( $_wp_filters[ $tag ] );
}

/**
 * Has action
 */
function has_action( $tag, $function_to_check = false ) {
    global $_wp_actions;
    return isset( $_wp_actions[ $tag ] );
}

/**
 * Plugin functions
 */
function plugin_dir_path( $file ) {
    return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
    return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function plugins_url( $path = '', $plugin = '' ) {
    return 'http://example.com/wp-content/plugins/' . $path;
}

function is_plugin_active( $plugin ) {
    return true;
}

function activate_plugin( $plugin ) {
    return null;
}

function deactivate_plugins( $plugins, $silent = false, $network_wide = null ) {
    return;
}

/**
 * Admin URL
 */
function admin_url( $path = '', $scheme = 'admin' ) {
    return 'http://example.com/wp-admin/' . $path;
}

/**
 * Site URL
 */
function site_url( $path = '', $scheme = null ) {
    return 'http://example.com/' . $path;
}

/**
 * Home URL
 */
function home_url( $path = '', $scheme = null ) {
    return 'http://example.com/' . $path;
}

/**
 * Content URL
 */
function content_url( $path = '' ) {
    return 'http://example.com/wp-content/' . $path;
}

/**
 * Get bloginfo
 */
function get_bloginfo( $show = '', $filter = 'raw' ) {
    $info = [
        'name' => 'Test Site',
        'description' => 'Just another WordPress site',
        'url' => 'http://example.com',
        'admin_email' => 'admin@example.com',
        'version' => '6.0',
    ];
    return isset( $info[ $show ] ) ? $info[ $show ] : '';
}

/**
 * Upload directory
 */
function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
    return [
        'path' => '/tmp/uploads',
        'url' => 'http://example.com/wp-content/uploads',
        'subdir' => '',
        'basedir' => '/tmp/uploads',
        'baseurl' => 'http://example.com/wp-content/uploads',
        'error' => false,
    ];
}

/**
 * WP filesystem
 */
function WP_Filesystem() {
    return true;
}

/**
 * Mkdir
 */
function wp_mkdir_p( $target ) {
    if ( ! is_dir( $target ) ) {
        return @mkdir( $target, 0755, true );
    }
    return true;
}

/**
 * HTTP API
 */
function wp_remote_post( $url, $args = [] ) {
    return [
        'response' => [ 'code' => 200 ],
        'body' => json_encode( [ 'success' => true ] ),
    ];
}

function wp_remote_get( $url, $args = [] ) {
    return [
        'response' => [ 'code' => 200 ],
        'body' => json_encode( [ 'success' => true ] ),
    ];
}

function wp_remote_retrieve_response_code( $response ) {
    return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
    return isset( $response['body'] ) ? $response['body'] : '';
}

/**
 * Post functions
 */
function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
    return (object) [
        'ID' => is_numeric( $post ) ? $post : 1,
        'post_title' => 'Test Post',
        'post_content' => 'Test content',
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_author' => 1,
        'post_date' => '2024-01-01 00:00:00',
    ];
}

function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
    return rand( 1, 1000 );
}

function wp_update_post( $postarr = [], $wp_error = false, $fire_after_hooks = true ) {
    return isset( $postarr['ID'] ) ? $postarr['ID'] : 1;
}

function wp_delete_post( $postid = 0, $force_delete = false ) {
    return get_post( $postid );
}

function get_post_meta( $post_id, $key = '', $single = false ) {
    return $single ? '' : [];
}

function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
    return true;
}

function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
    return true;
}

/**
 * Nonce functions
 */
function wp_create_nonce( $action = -1 ) {
    return md5( $action . time() );
}

function wp_verify_nonce( $nonce, $action = -1 ) {
    return 1;
}

function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
    return 1;
}

/**
 * REST API
 */
function register_rest_route( $namespace, $route, $args = [], $override = false ) {
    return true;
}

class WP_REST_Request {
    private $params = [];
    private $body = '';

    public function __construct( $method = '', $route = '' ) {}

    public function set_param( $key, $value ) {
        $this->params[ $key ] = $value;
    }

    public function get_param( $key ) {
        return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
    }

    public function get_params() {
        return $this->params;
    }

    public function set_body( $body ) {
        $this->body = $body;
    }

    public function get_body() {
        return $this->body;
    }

    public function get_json_params() {
        return json_decode( $this->body, true ) ?: [];
    }
}

class WP_REST_Response {
    public $data;
    public $status;

    public function __construct( $data = null, $status = 200 ) {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data() {
        return $this->data;
    }

    public function get_status() {
        return $this->status;
    }
}

function rest_ensure_response( $response ) {
    if ( $response instanceof WP_REST_Response ) {
        return $response;
    }
    return new WP_REST_Response( $response );
}

/**
 * Translation functions
 */
function __( $text, $domain = 'default' ) {
    return $text;
}

function _e( $text, $domain = 'default' ) {
    echo $text;
}

function esc_html__( $text, $domain = 'default' ) {
    return esc_html( $text );
}

function esc_attr__( $text, $domain = 'default' ) {
    return esc_attr( $text );
}

function _x( $text, $context, $domain = 'default' ) {
    return $text;
}

function _n( $single, $plural, $number, $domain = 'default' ) {
    return $number === 1 ? $single : $plural;
}

/**
 * Cache functions
 */
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
    return false;
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
    return true;
}

function wp_cache_delete( $key, $group = '' ) {
    return true;
}

function wp_cache_flush() {
    return true;
}

/**
 * Cron functions
 */
function wp_schedule_event( $timestamp, $recurrence, $hook, $args = [], $wp_error = false ) {
    return true;
}

function wp_clear_scheduled_hook( $hook, $args = [] ) {
    return 0;
}

function wp_next_scheduled( $hook, $args = [] ) {
    return false;
}

/**
 * Debug functions
 */
function wp_debug_backtrace_summary( $ignore_class = null, $skip_frames = 0, $pretty = true ) {
    return '';
}

/**
 * Other functions
 */
function wp_parse_args( $args, $defaults = [] ) {
    if ( is_object( $args ) ) {
        $args = get_object_vars( $args );
    } elseif ( is_string( $args ) ) {
        parse_str( $args, $args );
    }
    return array_merge( $defaults, $args );
}

function wp_list_pluck( $list, $field, $index_key = null ) {
    $result = [];
    foreach ( $list as $key => $row ) {
        $row = (array) $row;
        if ( isset( $row[ $field ] ) ) {
            if ( $index_key && isset( $row[ $index_key ] ) ) {
                $result[ $row[ $index_key ] ] = $row[ $field ];
            } else {
                $result[] = $row[ $field ];
            }
        }
    }
    return $result;
}

function human_time_diff( $from, $to = 0 ) {
    if ( empty( $to ) ) {
        $to = time();
    }
    $diff = abs( $to - $from );
    if ( $diff < 60 ) {
        return $diff . ' seconds';
    }
    return round( $diff / 60 ) . ' mins';
}

function size_format( $bytes, $decimals = 0 ) {
    $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
    $bytes = max( $bytes, 0 );
    $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
    $pow = min( $pow, count( $units ) - 1 );
    $bytes /= pow( 1024, $pow );
    return round( $bytes, $decimals ) . ' ' . $units[ $pow ];
}

function dbDelta( $queries = '', $execute = true ) {
    return [];
}
