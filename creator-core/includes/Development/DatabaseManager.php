<?php
/**
 * Database Manager
 *
 * @package CreatorCore
 */

namespace CreatorCore\Development;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class DatabaseManager
 *
 * Manages WordPress database operations for development
 */
class DatabaseManager {

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Protected tables that cannot be modified directly
     *
     * @var array
     */
    private array $protected_tables = [
        'users',
        'usermeta',
        'options',       // Sensitive WordPress settings
    ];

    /**
     * Forbidden SQL keywords for query validation
     *
     * @var array
     */
    private array $forbidden_keywords = [
        // DML (Data Modification)
        'INSERT',
        'UPDATE',
        'DELETE',
        'REPLACE',
        'MERGE',
        // DDL (Schema Modification)
        'DROP',
        'ALTER',
        'TRUNCATE',
        'CREATE',
        'RENAME',
        // DCL (Access Control)
        'GRANT',
        'REVOKE',
        // File Operations (MySQL specific - high risk)
        'INTO OUTFILE',
        'INTO DUMPFILE',
        'LOAD_FILE',
        'LOAD DATA',
        // Stored Procedures / Functions
        'CALL',
        'EXECUTE',
        'EXEC',
        // Transaction Control (prevent lock attacks)
        'LOCK TABLES',
        'UNLOCK TABLES',
        // Other dangerous operations
        'PREPARE',
        'DEALLOCATE',
        'HANDLER',
        'FLUSH',
        'RESET',
        'PURGE',
        'SHUTDOWN',
        'KILL',
    ];

    /**
     * Maximum rows for SELECT queries
     *
     * @var int
     */
    private int $max_rows = 1000;

    /**
     * Constructor
     *
     * @param AuditLogger|null $logger Audit logger instance.
     */
    public function __construct( ?AuditLogger $logger = null ) {
        global $wpdb;
        $this->wpdb   = $wpdb;
        $this->logger = $logger ?? new AuditLogger();
    }

    /**
     * Get database information
     *
     * @return array Database info.
     */
    public function get_database_info(): array {
        return [
            'success'          => true,
            'database_name'    => DB_NAME,
            'table_prefix'     => $this->wpdb->prefix,
            'charset'          => $this->wpdb->charset,
            'collate'          => $this->wpdb->collate,
            'mysql_version'    => $this->wpdb->db_version(),
            'wordpress_tables' => $this->get_wordpress_tables(),
            'custom_tables'    => $this->get_custom_tables(),
        ];
    }

    /**
     * Get list of WordPress core tables
     *
     * @return array WordPress tables.
     */
    public function get_wordpress_tables(): array {
        $core_tables = [
            'posts',
            'postmeta',
            'comments',
            'commentmeta',
            'terms',
            'termmeta',
            'term_taxonomy',
            'term_relationships',
            'options',
            'users',
            'usermeta',
            'links',
        ];

        $tables = [];
        foreach ( $core_tables as $table ) {
            $full_name = $this->wpdb->prefix . $table;
            $tables[ $table ] = [
                'name'        => $full_name,
                'exists'      => $this->table_exists( $full_name ),
                'row_count'   => $this->get_table_row_count( $full_name ),
            ];
        }

        return $tables;
    }

    /**
     * Get list of custom (non-WordPress core) tables
     *
     * @return array Custom tables.
     */
    public function get_custom_tables(): array {
        $all_tables  = $this->wpdb->get_results( 'SHOW TABLES', ARRAY_N );
        $core_tables = [
            'posts', 'postmeta', 'comments', 'commentmeta', 'terms',
            'termmeta', 'term_taxonomy', 'term_relationships', 'options',
            'users', 'usermeta', 'links',
        ];

        $custom = [];
        foreach ( $all_tables as $table ) {
            $table_name = $table[0];

            // Skip core tables
            $is_core = false;
            foreach ( $core_tables as $core ) {
                if ( $table_name === $this->wpdb->prefix . $core ) {
                    $is_core = true;
                    break;
                }
            }

            if ( ! $is_core && strpos( $table_name, $this->wpdb->prefix ) === 0 ) {
                $custom[] = [
                    'name'      => $table_name,
                    'short_name' => str_replace( $this->wpdb->prefix, '', $table_name ),
                    'row_count' => $this->get_table_row_count( $table_name ),
                ];
            }
        }

        return $custom;
    }

    /**
     * Check if a table exists
     *
     * @param string $table_name Table name.
     * @return bool
     */
    public function table_exists( string $table_name ): bool {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        return $result === $table_name;
    }

    /**
     * Get table row count
     *
     * @param string $table_name Table name.
     * @return int
     */
    public function get_table_row_count( string $table_name ): int {
        if ( ! $this->table_exists( $table_name ) ) {
            return 0;
        }

        $count = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM `{$table_name}`"
        );

        return (int) $count;
    }

    /**
     * Get table structure
     *
     * @param string $table_name Table name.
     * @return array Table structure.
     */
    public function get_table_structure( string $table_name ): array {
        if ( ! $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Table not found', 'creator-core' ),
            ];
        }

        $columns = $this->wpdb->get_results(
            "DESCRIBE `{$table_name}`",
            ARRAY_A
        );

        $indexes = $this->wpdb->get_results(
            "SHOW INDEX FROM `{$table_name}`",
            ARRAY_A
        );

        $create_sql = $this->wpdb->get_row(
            "SHOW CREATE TABLE `{$table_name}`",
            ARRAY_N
        );

        return [
            'success'    => true,
            'table'      => $table_name,
            'columns'    => $columns,
            'indexes'    => $indexes,
            'create_sql' => $create_sql[1] ?? '',
            'row_count'  => $this->get_table_row_count( $table_name ),
        ];
    }

    /**
     * Execute a SELECT query (read-only)
     *
     * @param string $query  SQL query.
     * @param int    $limit  Maximum rows to return.
     * @param int    $offset Offset for pagination.
     * @return array Query results.
     */
    public function select( string $query, int $limit = 100, int $offset = 0 ): array {
        // Validate query using comprehensive security checks
        $validation = $this->validate_select_query( $query );
        if ( ! $validation['valid'] ) {
            $this->logger->warning( 'database_query_blocked', [
                'query'  => substr( $query, 0, 200 ),
                'reason' => $validation['error'],
            ]);
            return [
                'success' => false,
                'error'   => $validation['error'],
            ];
        }

        $query = $validation['query'];

        // Apply limit if not present
        if ( stripos( $query, 'LIMIT' ) === false ) {
            $limit = min( $limit, $this->max_rows );
            $query .= " LIMIT {$offset}, {$limit}";
        }

        $results = $this->wpdb->get_results( $query, ARRAY_A );

        if ( $this->wpdb->last_error ) {
            $this->logger->warning( 'database_query_error', [
                'query' => substr( $query, 0, 200 ),
                'error' => $this->wpdb->last_error,
            ]);
            return [
                'success' => false,
                'error'   => $this->wpdb->last_error,
                'query'   => $query,
            ];
        }

        $this->logger->info( 'database_query', [
            'query'      => substr( $query, 0, 200 ),
            'row_count'  => count( $results ),
        ]);

        return [
            'success'   => true,
            'query'     => $query,
            'results'   => $results,
            'row_count' => count( $results ),
        ];
    }

    /**
     * Get rows from a table with conditions
     *
     * @param string $table      Table name (without prefix).
     * @param array  $conditions WHERE conditions.
     * @param array  $args       Additional arguments (limit, offset, orderby).
     * @return array Results.
     */
    public function get_rows( string $table, array $conditions = [], array $args = [] ): array {
        $table_name = $this->wpdb->prefix . $table;

        if ( ! $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Table not found', 'creator-core' ),
            ];
        }

        $defaults = [
            'limit'   => 100,
            'offset'  => 0,
            'orderby' => null,
            'order'   => 'ASC',
            'fields'  => '*',
        ];

        $args = wp_parse_args( $args, $defaults );

        // Build query
        $query = "SELECT {$args['fields']} FROM `{$table_name}`";

        // Build WHERE clause
        if ( ! empty( $conditions ) ) {
            $where_parts = [];
            foreach ( $conditions as $column => $value ) {
                if ( is_null( $value ) ) {
                    $where_parts[] = "`{$column}` IS NULL";
                } elseif ( is_numeric( $value ) ) {
                    $where_parts[] = $this->wpdb->prepare( "`{$column}` = %d", $value );
                } else {
                    $where_parts[] = $this->wpdb->prepare( "`{$column}` = %s", $value );
                }
            }
            $query .= ' WHERE ' . implode( ' AND ', $where_parts );
        }

        // ORDER BY
        if ( $args['orderby'] ) {
            $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
            $query .= " ORDER BY `{$args['orderby']}` {$order}";
        }

        // LIMIT
        $limit = min( $args['limit'], $this->max_rows );
        $query .= " LIMIT {$args['offset']}, {$limit}";

        $results = $this->wpdb->get_results( $query, ARRAY_A );

        if ( $this->wpdb->last_error ) {
            return [
                'success' => false,
                'error'   => $this->wpdb->last_error,
            ];
        }

        return [
            'success'   => true,
            'table'     => $table_name,
            'results'   => $results,
            'row_count' => count( $results ),
        ];
    }

    /**
     * Insert a row into a table
     *
     * @param string $table Table name (without prefix).
     * @param array  $data  Data to insert.
     * @return array Result.
     */
    public function insert( string $table, array $data ): array {
        $table_name = $this->wpdb->prefix . $table;

        if ( $this->is_protected_table( $table ) ) {
            return [
                'success' => false,
                'error'   => __( 'Cannot modify protected table', 'creator-core' ),
            ];
        }

        if ( ! $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Table not found', 'creator-core' ),
            ];
        }

        $result = $this->wpdb->insert( $table_name, $data );

        if ( $result === false ) {
            return [
                'success' => false,
                'error'   => $this->wpdb->last_error ?: __( 'Insert failed', 'creator-core' ),
            ];
        }

        $insert_id = $this->wpdb->insert_id;

        $this->logger->info( 'database_insert', [
            'table'     => $table_name,
            'insert_id' => $insert_id,
        ]);

        return [
            'success'   => true,
            'table'     => $table_name,
            'insert_id' => $insert_id,
            'message'   => __( 'Row inserted successfully', 'creator-core' ),
        ];
    }

    /**
     * Update rows in a table
     *
     * @param string $table Table name (without prefix).
     * @param array  $data  Data to update.
     * @param array  $where WHERE conditions.
     * @return array Result.
     */
    public function update( string $table, array $data, array $where ): array {
        $table_name = $this->wpdb->prefix . $table;

        if ( $this->is_protected_table( $table ) ) {
            return [
                'success' => false,
                'error'   => __( 'Cannot modify protected table', 'creator-core' ),
            ];
        }

        if ( ! $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Table not found', 'creator-core' ),
            ];
        }

        if ( empty( $where ) ) {
            return [
                'success' => false,
                'error'   => __( 'WHERE clause is required for UPDATE', 'creator-core' ),
            ];
        }

        $result = $this->wpdb->update( $table_name, $data, $where );

        if ( $result === false ) {
            return [
                'success' => false,
                'error'   => $this->wpdb->last_error ?: __( 'Update failed', 'creator-core' ),
            ];
        }

        $this->logger->info( 'database_update', [
            'table'        => $table_name,
            'rows_updated' => $result,
        ]);

        return [
            'success'      => true,
            'table'        => $table_name,
            'rows_updated' => $result,
            'message'      => sprintf(
                /* translators: %d: Number of rows */
                __( '%d row(s) updated', 'creator-core' ),
                $result
            ),
        ];
    }

    /**
     * Delete rows from a table
     *
     * @param string $table Table name (without prefix).
     * @param array  $where WHERE conditions.
     * @return array Result.
     */
    public function delete( string $table, array $where ): array {
        $table_name = $this->wpdb->prefix . $table;

        if ( $this->is_protected_table( $table ) ) {
            return [
                'success' => false,
                'error'   => __( 'Cannot modify protected table', 'creator-core' ),
            ];
        }

        if ( ! $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Table not found', 'creator-core' ),
            ];
        }

        if ( empty( $where ) ) {
            return [
                'success' => false,
                'error'   => __( 'WHERE clause is required for DELETE', 'creator-core' ),
            ];
        }

        $result = $this->wpdb->delete( $table_name, $where );

        if ( $result === false ) {
            return [
                'success' => false,
                'error'   => $this->wpdb->last_error ?: __( 'Delete failed', 'creator-core' ),
            ];
        }

        $this->logger->info( 'database_delete', [
            'table'        => $table_name,
            'rows_deleted' => $result,
        ]);

        return [
            'success'      => true,
            'table'        => $table_name,
            'rows_deleted' => $result,
            'message'      => sprintf(
                /* translators: %d: Number of rows */
                __( '%d row(s) deleted', 'creator-core' ),
                $result
            ),
        ];
    }

    /**
     * Create a custom table
     *
     * @param string $table   Table name (without prefix).
     * @param array  $columns Column definitions.
     * @param array  $options Table options (primary_key, indexes, etc).
     * @return array Result.
     */
    public function create_table( string $table, array $columns, array $options = [] ): array {
        $table_name = $this->wpdb->prefix . $table;

        if ( $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Table already exists', 'creator-core' ),
            ];
        }

        $column_sql = [];
        foreach ( $columns as $name => $definition ) {
            $column_sql[] = "`{$name}` {$definition}";
        }

        $sql = "CREATE TABLE `{$table_name}` (\n" . implode( ",\n", $column_sql );

        // Primary key
        if ( ! empty( $options['primary_key'] ) ) {
            $sql .= ",\nPRIMARY KEY (`{$options['primary_key']}`)";
        }

        // Additional indexes
        if ( ! empty( $options['indexes'] ) ) {
            foreach ( $options['indexes'] as $index_name => $index_column ) {
                $sql .= ",\nINDEX `{$index_name}` (`{$index_column}`)";
            }
        }

        $sql .= "\n) {$this->wpdb->get_charset_collate()};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        if ( ! $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to create table', 'creator-core' ),
                'sql'     => $sql,
            ];
        }

        $this->logger->info( 'table_created', [
            'table' => $table_name,
        ]);

        return [
            'success' => true,
            'table'   => $table_name,
            'sql'     => $sql,
            'message' => __( 'Table created successfully', 'creator-core' ),
        ];
    }

    /**
     * Drop a custom table
     *
     * @param string $table  Table name (without prefix).
     * @param bool   $backup Create backup before dropping.
     * @return array Result.
     */
    public function drop_table( string $table, bool $backup = true ): array {
        $table_name = $this->wpdb->prefix . $table;

        if ( $this->is_protected_table( $table ) ) {
            return [
                'success' => false,
                'error'   => __( 'Cannot drop protected table', 'creator-core' ),
            ];
        }

        if ( ! $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Table not found', 'creator-core' ),
            ];
        }

        // Create backup
        $backup_path = null;
        if ( $backup ) {
            $backup_result = $this->backup_table( $table );
            if ( $backup_result['success'] ) {
                $backup_path = $backup_result['backup_path'];
            }
        }

        $result = $this->wpdb->query( "DROP TABLE `{$table_name}`" );

        if ( $result === false ) {
            return [
                'success' => false,
                'error'   => $this->wpdb->last_error ?: __( 'Failed to drop table', 'creator-core' ),
            ];
        }

        $this->logger->info( 'table_dropped', [
            'table'       => $table_name,
            'backup_path' => $backup_path,
        ]);

        return [
            'success'     => true,
            'table'       => $table_name,
            'backup_path' => $backup_path,
            'message'     => __( 'Table dropped successfully', 'creator-core' ),
        ];
    }

    /**
     * Backup a table to SQL file
     *
     * @param string $table Table name (without prefix).
     * @return array Result with backup path.
     */
    public function backup_table( string $table ): array {
        $table_name = $this->wpdb->prefix . $table;

        if ( ! $this->table_exists( $table_name ) ) {
            return [
                'success' => false,
                'error'   => __( 'Table not found', 'creator-core' ),
            ];
        }

        $backup_dir = WP_CONTENT_DIR . '/creator-backups/database';
        if ( ! is_dir( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
        }

        $backup_file = $backup_dir . '/' . $table . '_' . gmdate( 'Y-m-d_H-i-s' ) . '.sql';

        // Get CREATE TABLE statement
        $create = $this->wpdb->get_row( "SHOW CREATE TABLE `{$table_name}`", ARRAY_N );
        $sql    = "-- Table: {$table_name}\n";
        $sql   .= "-- Backup: " . gmdate( 'Y-m-d H:i:s' ) . "\n\n";
        $sql   .= "DROP TABLE IF EXISTS `{$table_name}`;\n\n";
        $sql   .= $create[1] . ";\n\n";

        // Get all rows
        $rows = $this->wpdb->get_results( "SELECT * FROM `{$table_name}`", ARRAY_A );

        if ( ! empty( $rows ) ) {
            $columns = array_keys( $rows[0] );
            $column_list = '`' . implode( '`, `', $columns ) . '`';

            foreach ( $rows as $row ) {
                $values = array_map( function ( $value ) {
                    if ( is_null( $value ) ) {
                        return 'NULL';
                    }
                    return "'" . esc_sql( $value ) . "'";
                }, array_values( $row ) );

                $sql .= "INSERT INTO `{$table_name}` ({$column_list}) VALUES (" . implode( ', ', $values ) . ");\n";
            }
        }

        $written = file_put_contents( $backup_file, $sql );

        if ( $written === false ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to write backup file', 'creator-core' ),
            ];
        }

        $this->logger->info( 'table_backed_up', [
            'table'       => $table_name,
            'backup_path' => $backup_file,
            'size'        => $written,
        ]);

        return [
            'success'     => true,
            'table'       => $table_name,
            'backup_path' => $backup_file,
            'size'        => $written,
        ];
    }

    /**
     * Restore a table from backup
     *
     * @param string $backup_path Path to backup file.
     * @return array Result.
     */
    public function restore_from_backup( string $backup_path ): array {
        if ( ! file_exists( $backup_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Backup file not found', 'creator-core' ),
            ];
        }

        $sql = file_get_contents( $backup_path );

        if ( $sql === false ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to read backup file', 'creator-core' ),
            ];
        }

        // Split into statements
        $statements = preg_split( '/;\s*\n/', $sql );
        $executed   = 0;
        $errors     = [];

        foreach ( $statements as $statement ) {
            $statement = trim( $statement );
            if ( empty( $statement ) || strpos( $statement, '--' ) === 0 ) {
                continue;
            }

            $result = $this->wpdb->query( $statement );
            if ( $result === false ) {
                $errors[] = $this->wpdb->last_error;
            } else {
                $executed++;
            }
        }

        $this->logger->info( 'table_restored', [
            'backup_path' => $backup_path,
            'statements'  => $executed,
        ]);

        return [
            'success'    => empty( $errors ),
            'executed'   => $executed,
            'errors'     => $errors,
            'message'    => sprintf(
                /* translators: %d: Number of statements */
                __( '%d SQL statements executed', 'creator-core' ),
                $executed
            ),
        ];
    }

    /**
     * Check if table is protected
     *
     * @param string $table Table name (without prefix).
     * @return bool
     */
    private function is_protected_table( string $table ): bool {
        return in_array( $table, $this->protected_tables, true );
    }

    /**
     * Get WordPress options
     *
     * @param string $search Search term.
     * @param int    $limit  Maximum results.
     * @return array Options.
     */
    public function get_options( string $search = '', int $limit = 100 ): array {
        $query = "SELECT option_name, option_value, autoload FROM {$this->wpdb->options}";

        if ( ! empty( $search ) ) {
            $query .= $this->wpdb->prepare(
                ' WHERE option_name LIKE %s',
                '%' . $this->wpdb->esc_like( $search ) . '%'
            );
        }

        $query .= " LIMIT {$limit}";

        $options = $this->wpdb->get_results( $query, ARRAY_A );

        // Format option values
        foreach ( $options as &$option ) {
            $option['value_preview'] = substr( $option['option_value'], 0, 100 );
            $option['is_serialized'] = is_serialized( $option['option_value'] );
            if ( $option['is_serialized'] ) {
                $option['unserialized'] = maybe_unserialize( $option['option_value'] );
            }
        }

        return [
            'success' => true,
            'options' => $options,
            'count'   => count( $options ),
        ];
    }

    /**
     * Optimize database tables
     *
     * @return array Optimization results.
     */
    public function optimize_tables(): array {
        $tables = $this->wpdb->get_results( 'SHOW TABLES', ARRAY_N );
        $results = [];

        foreach ( $tables as $table ) {
            $table_name = $table[0];

            // Only optimize tables with our prefix
            if ( strpos( $table_name, $this->wpdb->prefix ) === 0 ) {
                $result = $this->wpdb->query( "OPTIMIZE TABLE `{$table_name}`" );
                $results[ $table_name ] = $result !== false;
            }
        }

        $this->logger->info( 'database_optimized', [
            'tables' => count( $results ),
        ]);

        return [
            'success' => true,
            'tables'  => $results,
            'count'   => count( $results ),
        ];
    }

    /**
     * Get database size
     *
     * @return array Size information.
     */
    public function get_database_size(): array {
        $size = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(data_length + index_length)
                 FROM information_schema.TABLES
                 WHERE table_schema = %s",
                DB_NAME
            )
        );

        $table_sizes = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT table_name,
                        data_length,
                        index_length,
                        (data_length + index_length) as total_size
                 FROM information_schema.TABLES
                 WHERE table_schema = %s
                 ORDER BY total_size DESC",
                DB_NAME
            ),
            ARRAY_A
        );

        return [
            'success'     => true,
            'total_size'  => (int) $size,
            'formatted'   => size_format( $size ),
            'tables'      => $table_sizes,
        ];
    }

    /**
     * Validate a SELECT query for security
     *
     * Performs comprehensive security checks to prevent SQL injection
     * and unauthorized data access/modification.
     *
     * @param string $query The SQL query to validate.
     * @return array Validation result with 'valid', 'error', and sanitized 'query'.
     */
    private function validate_select_query( string $query ): array {
        // Normalize query
        $query = trim( $query );

        // Must start with SELECT
        if ( stripos( $query, 'SELECT' ) !== 0 ) {
            return [
                'valid' => false,
                'error' => __( 'Only SELECT queries are allowed', 'creator-core' ),
            ];
        }

        // Convert to uppercase for keyword matching
        $query_upper = strtoupper( $query );

        // Check for forbidden keywords
        foreach ( $this->forbidden_keywords as $keyword ) {
            // Use word boundary matching to avoid false positives
            // e.g., "CREATED_AT" should not match "CREATE"
            $pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/i';
            if ( preg_match( $pattern, $query ) ) {
                return [
                    'valid' => false,
                    'error' => sprintf(
                        /* translators: %s: SQL keyword */
                        __( 'Query contains forbidden keyword: %s', 'creator-core' ),
                        $keyword
                    ),
                ];
            }
        }

        // Block stacked queries (multiple statements)
        if ( substr_count( $query, ';' ) > 0 ) {
            // Allow semicolon only at the very end
            $trimmed = rtrim( $query, "; \t\n\r" );
            if ( strpos( $trimmed, ';' ) !== false ) {
                return [
                    'valid' => false,
                    'error' => __( 'Multiple SQL statements are not allowed', 'creator-core' ),
                ];
            }
        }

        // Block comments that could hide malicious code
        if ( preg_match( '/\/\*|\*\/|--\s|#/', $query ) ) {
            return [
                'valid' => false,
                'error' => __( 'SQL comments are not allowed', 'creator-core' ),
            ];
        }

        // Block UNION-based injection attempts
        if ( preg_match( '/\bUNION\s+(ALL\s+)?SELECT\b/i', $query ) ) {
            return [
                'valid' => false,
                'error' => __( 'UNION SELECT is not allowed', 'creator-core' ),
            ];
        }

        // Block subqueries that modify data
        if ( preg_match( '/\(\s*(?:INSERT|UPDATE|DELETE|DROP|ALTER|CREATE)\b/i', $query ) ) {
            return [
                'valid' => false,
                'error' => __( 'Subqueries with data modification are not allowed', 'creator-core' ),
            ];
        }

        // Block access to MySQL system tables
        $system_tables = [
            'mysql.',
            'information_schema.',
            'performance_schema.',
            'sys.',
        ];
        foreach ( $system_tables as $sys_table ) {
            if ( stripos( $query, $sys_table ) !== false ) {
                return [
                    'valid' => false,
                    'error' => __( 'Access to system tables is not allowed', 'creator-core' ),
                ];
            }
        }

        // Block hex-encoded strings (common injection technique)
        if ( preg_match( '/0x[0-9a-fA-F]{8,}/', $query ) ) {
            return [
                'valid' => false,
                'error' => __( 'Hex-encoded values are not allowed', 'creator-core' ),
            ];
        }

        // Block CHAR() function (often used to bypass filters)
        if ( preg_match( '/\bCHAR\s*\(/i', $query ) ) {
            return [
                'valid' => false,
                'error' => __( 'CHAR() function is not allowed', 'creator-core' ),
            ];
        }

        // Block BENCHMARK and SLEEP (timing attacks)
        if ( preg_match( '/\b(?:BENCHMARK|SLEEP)\s*\(/i', $query ) ) {
            return [
                'valid' => false,
                'error' => __( 'Timing functions are not allowed', 'creator-core' ),
            ];
        }

        return [
            'valid' => true,
            'query' => $query,
        ];
    }
}
