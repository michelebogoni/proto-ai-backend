<?php
/**
 * Code Analyzer
 *
 * @package CreatorCore
 */

namespace CreatorCore\Development;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class CodeAnalyzer
 *
 * Analyzes PHP code for errors, security issues, and best practices
 */
class CodeAnalyzer {

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * File system manager instance
     *
     * @var FileSystemManager
     */
    private FileSystemManager $filesystem;

    /**
     * Security patterns to check
     *
     * @var array
     */
    private array $security_patterns = [
        'sql_injection' => [
            'pattern' => '/\$wpdb\s*->\s*(query|prepare|get_results|get_var|get_row|get_col)\s*\([^)]*\$_(GET|POST|REQUEST|COOKIE)/i',
            'message' => 'Potential SQL injection: User input used directly in database query',
            'severity' => 'critical',
        ],
        'xss_echo' => [
            'pattern' => '/echo\s+\$_(GET|POST|REQUEST|COOKIE)/i',
            'message' => 'Potential XSS: Echoing user input without escaping',
            'severity' => 'critical',
        ],
        'xss_print' => [
            'pattern' => '/print\s+\$_(GET|POST|REQUEST|COOKIE)/i',
            'message' => 'Potential XSS: Printing user input without escaping',
            'severity' => 'critical',
        ],
        'eval_usage' => [
            'pattern' => '/\beval\s*\(/i',
            'message' => 'Dangerous: eval() function usage detected',
            'severity' => 'critical',
        ],
        'shell_exec' => [
            'pattern' => '/\b(shell_exec|exec|system|passthru|popen|proc_open)\s*\(/i',
            'message' => 'Dangerous: Shell execution function detected',
            'severity' => 'high',
        ],
        'file_inclusion' => [
            'pattern' => '/(include|require|include_once|require_once)\s*\(\s*\$_(GET|POST|REQUEST)/i',
            'message' => 'Critical: Potential remote file inclusion vulnerability',
            'severity' => 'critical',
        ],
        'unserialize' => [
            'pattern' => '/\bunserialize\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i',
            'message' => 'Dangerous: Unserializing user input can lead to object injection',
            'severity' => 'critical',
        ],
        'missing_nonce' => [
            'pattern' => '/\$_POST\[.*\].*(?!.*wp_verify_nonce)/s',
            'message' => 'Warning: Processing POST data without nonce verification',
            'severity' => 'medium',
        ],
        'missing_capability' => [
            'pattern' => '/add_action\s*\(\s*[\'"]admin_init[\'"].*(?!.*current_user_can)/s',
            'message' => 'Warning: Admin action without capability check',
            'severity' => 'medium',
        ],
        'hardcoded_credentials' => [
            'pattern' => '/(password|secret|api_key|apikey|token)\s*=\s*[\'"][^\'"]{8,}[\'"]/i',
            'message' => 'Warning: Possible hardcoded credentials detected',
            'severity' => 'high',
        ],
    ];

    /**
     * WordPress coding standards patterns
     *
     * @var array
     */
    private array $wpcs_patterns = [
        'missing_text_domain' => [
            'pattern' => '/__\s*\(\s*[\'"][^\'"]+[\'"]\s*\)(?!\s*,)/i',
            'message' => 'Missing text domain in translation function',
            'severity' => 'low',
        ],
        'direct_database' => [
            'pattern' => '/\$wpdb\s*->\s*query\s*\([^)]*(?<!prepare\s*\()/i',
            'message' => 'Use $wpdb->prepare() for database queries',
            'severity' => 'medium',
        ],
        'short_php_tags' => [
            'pattern' => '/<\?(?!php|=)/i',
            'message' => 'Use full <?php tags instead of short tags',
            'severity' => 'low',
        ],
        'closing_php_tag' => [
            'pattern' => '/\?>\s*$/i',
            'message' => 'Omit closing PHP tag at end of file',
            'severity' => 'low',
        ],
    ];

    /**
     * Constructor
     *
     * @param AuditLogger|null       $logger     Audit logger instance.
     * @param FileSystemManager|null $filesystem File system manager instance.
     */
    public function __construct( ?AuditLogger $logger = null, ?FileSystemManager $filesystem = null ) {
        $this->logger     = $logger ?? new AuditLogger();
        $this->filesystem = $filesystem ?? new FileSystemManager( $this->logger );
    }

    /**
     * Analyze a PHP file
     *
     * @param string $file_path Path to PHP file.
     * @return array Analysis results.
     */
    public function analyze_file( string $file_path ): array {
        $read_result = $this->filesystem->read_file( $file_path );

        if ( ! $read_result['success'] ) {
            return $read_result;
        }

        $content = $read_result['content'];
        $results = [
            'success'         => true,
            'file'            => $file_path,
            'syntax_valid'    => true,
            'syntax_errors'   => [],
            'security_issues' => [],
            'coding_issues'   => [],
            'complexity'      => [],
            'summary'         => [],
        ];

        // Check syntax
        $syntax_result = $this->check_syntax( $content );
        $results['syntax_valid']  = $syntax_result['valid'];
        $results['syntax_errors'] = $syntax_result['errors'];

        // Security analysis
        $results['security_issues'] = $this->check_security( $content );

        // Coding standards
        $results['coding_issues'] = $this->check_coding_standards( $content );

        // Code complexity
        $results['complexity'] = $this->analyze_complexity( $content );

        // Generate summary
        $results['summary'] = $this->generate_summary( $results );

        $this->logger->info( 'code_analyzed', [
            'file'           => $file_path,
            'security_count' => count( $results['security_issues'] ),
            'coding_count'   => count( $results['coding_issues'] ),
        ]);

        return $results;
    }

    /**
     * Analyze a plugin
     *
     * @param string $plugin_slug Plugin slug.
     * @return array Analysis results for all plugin files.
     */
    public function analyze_plugin( string $plugin_slug ): array {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        if ( ! is_dir( $plugin_dir ) ) {
            return [
                'success' => false,
                'error'   => __( 'Plugin not found', 'creator-core' ),
            ];
        }

        $find_result = $this->filesystem->find_files( $plugin_dir, '*.php', true );

        if ( ! $find_result['success'] ) {
            return $find_result;
        }

        $results = [
            'success'      => true,
            'plugin'       => $plugin_slug,
            'files'        => [],
            'total_issues' => 0,
            'by_severity'  => [
                'critical' => 0,
                'high'     => 0,
                'medium'   => 0,
                'low'      => 0,
            ],
        ];

        foreach ( $find_result['files'] as $file ) {
            $analysis = $this->analyze_file( $file['path'] );

            if ( $analysis['success'] ) {
                $file_result = [
                    'file'            => $file['path'],
                    'relative_path'   => str_replace( $plugin_dir . '/', '', $file['path'] ),
                    'syntax_valid'    => $analysis['syntax_valid'],
                    'security_issues' => $analysis['security_issues'],
                    'coding_issues'   => $analysis['coding_issues'],
                    'issue_count'     => count( $analysis['security_issues'] ) + count( $analysis['coding_issues'] ),
                ];

                $results['files'][] = $file_result;
                $results['total_issues'] += $file_result['issue_count'];

                // Count by severity
                foreach ( $analysis['security_issues'] as $issue ) {
                    $results['by_severity'][ $issue['severity'] ]++;
                }
                foreach ( $analysis['coding_issues'] as $issue ) {
                    $results['by_severity'][ $issue['severity'] ]++;
                }
            }
        }

        return $results;
    }

    /**
     * Analyze a theme
     *
     * @param string $theme_slug Theme slug (stylesheet).
     * @return array Analysis results for all theme files.
     */
    public function analyze_theme( string $theme_slug ): array {
        $theme = wp_get_theme( $theme_slug );

        if ( ! $theme->exists() ) {
            return [
                'success' => false,
                'error'   => __( 'Theme not found', 'creator-core' ),
            ];
        }

        $theme_dir   = $theme->get_stylesheet_directory();
        $find_result = $this->filesystem->find_files( $theme_dir, '*.php', true );

        if ( ! $find_result['success'] ) {
            return $find_result;
        }

        $results = [
            'success'      => true,
            'theme'        => $theme_slug,
            'theme_name'   => $theme->get( 'Name' ),
            'files'        => [],
            'total_issues' => 0,
            'by_severity'  => [
                'critical' => 0,
                'high'     => 0,
                'medium'   => 0,
                'low'      => 0,
            ],
        ];

        foreach ( $find_result['files'] as $file ) {
            $analysis = $this->analyze_file( $file['path'] );

            if ( $analysis['success'] ) {
                $file_result = [
                    'file'            => $file['path'],
                    'relative_path'   => str_replace( $theme_dir . '/', '', $file['path'] ),
                    'security_issues' => $analysis['security_issues'],
                    'coding_issues'   => $analysis['coding_issues'],
                    'issue_count'     => count( $analysis['security_issues'] ) + count( $analysis['coding_issues'] ),
                ];

                $results['files'][] = $file_result;
                $results['total_issues'] += $file_result['issue_count'];

                foreach ( $analysis['security_issues'] as $issue ) {
                    $results['by_severity'][ $issue['severity'] ]++;
                }
                foreach ( $analysis['coding_issues'] as $issue ) {
                    $results['by_severity'][ $issue['severity'] ]++;
                }
            }
        }

        return $results;
    }

    /**
     * Check PHP syntax
     *
     * @param string $code PHP code.
     * @return array Syntax check result.
     */
    private function check_syntax( string $code ): array {
        $result = [
            'valid'  => true,
            'errors' => [],
        ];

        // Use php -l equivalent via token_get_all
        try {
            $tokens = @token_get_all( $code );

            // Check for parse errors by attempting to tokenize
            $last_error = error_get_last();
            if ( $last_error && strpos( $last_error['message'], 'syntax error' ) !== false ) {
                $result['valid']    = false;
                $result['errors'][] = [
                    'message' => $last_error['message'],
                    'line'    => $last_error['line'] ?? 0,
                ];
            }
        } catch ( \ParseError $e ) {
            $result['valid']    = false;
            $result['errors'][] = [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ];
        }

        // Additional syntax checks
        $brace_count   = substr_count( $code, '{' ) - substr_count( $code, '}' );
        $paren_count   = substr_count( $code, '(' ) - substr_count( $code, ')' );
        $bracket_count = substr_count( $code, '[' ) - substr_count( $code, ']' );

        if ( $brace_count !== 0 ) {
            $result['errors'][] = [
                'message' => 'Mismatched curly braces',
                'line'    => 0,
            ];
        }

        if ( $paren_count !== 0 ) {
            $result['errors'][] = [
                'message' => 'Mismatched parentheses',
                'line'    => 0,
            ];
        }

        if ( $bracket_count !== 0 ) {
            $result['errors'][] = [
                'message' => 'Mismatched square brackets',
                'line'    => 0,
            ];
        }

        if ( ! empty( $result['errors'] ) ) {
            $result['valid'] = false;
        }

        return $result;
    }

    /**
     * Check for security issues
     *
     * @param string $code PHP code.
     * @return array Security issues found.
     */
    private function check_security( string $code ): array {
        $issues = [];
        $lines  = explode( "\n", $code );

        foreach ( $this->security_patterns as $name => $check ) {
            foreach ( $lines as $line_num => $line ) {
                if ( preg_match( $check['pattern'], $line ) ) {
                    $issues[] = [
                        'type'     => $name,
                        'message'  => $check['message'],
                        'severity' => $check['severity'],
                        'line'     => $line_num + 1,
                        'code'     => trim( $line ),
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Check WordPress coding standards
     *
     * @param string $code PHP code.
     * @return array Coding issues found.
     */
    private function check_coding_standards( string $code ): array {
        $issues = [];
        $lines  = explode( "\n", $code );

        foreach ( $this->wpcs_patterns as $name => $check ) {
            foreach ( $lines as $line_num => $line ) {
                if ( preg_match( $check['pattern'], $line ) ) {
                    $issues[] = [
                        'type'     => $name,
                        'message'  => $check['message'],
                        'severity' => $check['severity'],
                        'line'     => $line_num + 1,
                        'code'     => trim( $line ),
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Analyze code complexity
     *
     * @param string $code PHP code.
     * @return array Complexity metrics.
     */
    private function analyze_complexity( string $code ): array {
        $lines = explode( "\n", $code );

        return [
            'total_lines'      => count( $lines ),
            'code_lines'       => $this->count_code_lines( $lines ),
            'comment_lines'    => $this->count_comment_lines( $lines ),
            'blank_lines'      => $this->count_blank_lines( $lines ),
            'function_count'   => preg_match_all( '/function\s+\w+\s*\(/', $code ),
            'class_count'      => preg_match_all( '/class\s+\w+/', $code ),
            'cyclomatic_complexity' => $this->calculate_cyclomatic_complexity( $code ),
        ];
    }

    /**
     * Count code lines (non-blank, non-comment)
     *
     * @param array $lines Lines of code.
     * @return int
     */
    private function count_code_lines( array $lines ): int {
        $count = 0;
        $in_multiline_comment = false;

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            if ( $in_multiline_comment ) {
                if ( strpos( $trimmed, '*/' ) !== false ) {
                    $in_multiline_comment = false;
                }
                continue;
            }

            if ( strpos( $trimmed, '/*' ) !== false ) {
                $in_multiline_comment = strpos( $trimmed, '*/' ) === false;
                continue;
            }

            if ( empty( $trimmed ) || strpos( $trimmed, '//' ) === 0 || strpos( $trimmed, '#' ) === 0 ) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Count comment lines
     *
     * @param array $lines Lines of code.
     * @return int
     */
    private function count_comment_lines( array $lines ): int {
        $count = 0;
        $in_multiline_comment = false;

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            if ( $in_multiline_comment ) {
                $count++;
                if ( strpos( $trimmed, '*/' ) !== false ) {
                    $in_multiline_comment = false;
                }
                continue;
            }

            if ( strpos( $trimmed, '/*' ) !== false ) {
                $count++;
                $in_multiline_comment = strpos( $trimmed, '*/' ) === false;
                continue;
            }

            if ( strpos( $trimmed, '//' ) === 0 || strpos( $trimmed, '#' ) === 0 ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count blank lines
     *
     * @param array $lines Lines of code.
     * @return int
     */
    private function count_blank_lines( array $lines ): int {
        return count( array_filter( $lines, function ( $line ) {
            return trim( $line ) === '';
        }));
    }

    /**
     * Calculate cyclomatic complexity (simplified)
     *
     * @param string $code PHP code.
     * @return int
     */
    private function calculate_cyclomatic_complexity( string $code ): int {
        $complexity = 1; // Base complexity

        // Count decision points
        $decision_patterns = [
            '/\bif\s*\(/',
            '/\belseif\s*\(/',
            '/\belse\s*{/',
            '/\bfor\s*\(/',
            '/\bforeach\s*\(/',
            '/\bwhile\s*\(/',
            '/\bcase\s+/',
            '/\bcatch\s*\(/',
            '/\?\s*[^:]+\s*:/', // Ternary operator
            '/\&\&/',
            '/\|\|/',
        ];

        foreach ( $decision_patterns as $pattern ) {
            $complexity += preg_match_all( $pattern, $code );
        }

        return $complexity;
    }

    /**
     * Generate analysis summary
     *
     * @param array $results Analysis results.
     * @return array Summary.
     */
    private function generate_summary( array $results ): array {
        $total_issues   = count( $results['security_issues'] ) + count( $results['coding_issues'] );
        $critical_count = 0;
        $high_count     = 0;

        foreach ( array_merge( $results['security_issues'], $results['coding_issues'] ) as $issue ) {
            if ( $issue['severity'] === 'critical' ) {
                $critical_count++;
            } elseif ( $issue['severity'] === 'high' ) {
                $high_count++;
            }
        }

        $grade = 'A';
        if ( $critical_count > 0 ) {
            $grade = 'F';
        } elseif ( $high_count > 0 ) {
            $grade = 'D';
        } elseif ( $total_issues > 10 ) {
            $grade = 'C';
        } elseif ( $total_issues > 5 ) {
            $grade = 'B';
        }

        return [
            'total_issues'   => $total_issues,
            'critical_count' => $critical_count,
            'high_count'     => $high_count,
            'grade'          => $grade,
            'syntax_valid'   => $results['syntax_valid'],
            'complexity'     => $results['complexity']['cyclomatic_complexity'] ?? 1,
        ];
    }

    /**
     * Debug a PHP error
     *
     * @param string $error_message Error message.
     * @param string $file_path     File where error occurred.
     * @param int    $line_number   Line number.
     * @return array Debug information.
     */
    public function debug_error( string $error_message, string $file_path, int $line_number ): array {
        $result = [
            'success'       => true,
            'error_message' => $error_message,
            'file'          => $file_path,
            'line'          => $line_number,
            'context'       => [],
            'suggestions'   => [],
        ];

        // Read file and get context
        $read_result = $this->filesystem->read_file( $file_path );

        if ( $read_result['success'] ) {
            $lines   = explode( "\n", $read_result['content'] );
            $start   = max( 0, $line_number - 6 );
            $end     = min( count( $lines ), $line_number + 5 );

            for ( $i = $start; $i < $end; $i++ ) {
                $result['context'][ $i + 1 ] = $lines[ $i ];
            }

            $result['line_content'] = $lines[ $line_number - 1 ] ?? '';
        }

        // Generate suggestions based on error type
        $result['suggestions'] = $this->get_error_suggestions( $error_message );

        return $result;
    }

    /**
     * Get suggestions for an error
     *
     * @param string $error_message Error message.
     * @return array Suggestions.
     */
    private function get_error_suggestions( string $error_message ): array {
        $suggestions = [];

        if ( stripos( $error_message, 'undefined variable' ) !== false ) {
            $suggestions[] = 'Check if the variable is defined before use';
            $suggestions[] = 'Ensure the variable is in the correct scope';
            $suggestions[] = 'Use isset() or null coalescing operator (??) to handle undefined variables';
        }

        if ( stripos( $error_message, 'undefined function' ) !== false ) {
            $suggestions[] = 'Check if the function is defined or included';
            $suggestions[] = 'Ensure the file containing the function is loaded';
            $suggestions[] = 'Check for typos in the function name';
        }

        if ( stripos( $error_message, 'class not found' ) !== false ) {
            $suggestions[] = 'Verify the class file is included or autoloaded';
            $suggestions[] = 'Check the namespace declaration';
            $suggestions[] = 'Ensure the class name matches the file name';
        }

        if ( stripos( $error_message, 'syntax error' ) !== false ) {
            $suggestions[] = 'Check for missing semicolons or brackets';
            $suggestions[] = 'Verify proper quote matching';
            $suggestions[] = 'Look for unexpected tokens';
        }

        if ( stripos( $error_message, 'call to undefined method' ) !== false ) {
            $suggestions[] = 'Verify the method exists in the class';
            $suggestions[] = 'Check for typos in the method name';
            $suggestions[] = 'Ensure you are calling the method on the correct object';
        }

        if ( stripos( $error_message, 'cannot access' ) !== false ) {
            $suggestions[] = 'Check the visibility (public/private/protected) of the property or method';
            $suggestions[] = 'Use getters/setters for private properties';
        }

        if ( empty( $suggestions ) ) {
            $suggestions[] = 'Check the error log for more details';
            $suggestions[] = 'Enable WP_DEBUG for more verbose error reporting';
            $suggestions[] = 'Search the WordPress documentation or forums for similar issues';
        }

        return $suggestions;
    }

    /**
     * Check WordPress debug log
     *
     * @param int $lines_to_read Number of lines to read from end.
     * @return array Debug log contents.
     */
    public function get_debug_log( int $lines_to_read = 100 ): array {
        $log_path = WP_CONTENT_DIR . '/debug.log';

        if ( ! file_exists( $log_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Debug log file not found. Enable WP_DEBUG_LOG in wp-config.php', 'creator-core' ),
            ];
        }

        $log_content = file_get_contents( $log_path );
        $lines       = explode( "\n", $log_content );
        $total_lines = count( $lines );

        // Get last N lines
        $start = max( 0, $total_lines - $lines_to_read );
        $last_lines = array_slice( $lines, $start );

        // Parse log entries
        $entries = [];
        $current_entry = null;

        foreach ( $last_lines as $line ) {
            if ( preg_match( '/^\[(\d{2}-\w{3}-\d{4}\s+\d{2}:\d{2}:\d{2}\s+\w+)\]/', $line, $matches ) ) {
                if ( $current_entry ) {
                    $entries[] = $current_entry;
                }
                $current_entry = [
                    'timestamp' => $matches[1],
                    'message'   => trim( substr( $line, strlen( $matches[0] ) ) ),
                    'full'      => $line,
                ];
            } elseif ( $current_entry ) {
                $current_entry['message'] .= "\n" . $line;
                $current_entry['full'] .= "\n" . $line;
            }
        }

        if ( $current_entry ) {
            $entries[] = $current_entry;
        }

        return [
            'success'     => true,
            'path'        => $log_path,
            'total_lines' => $total_lines,
            'entries'     => $entries,
            'entry_count' => count( $entries ),
        ];
    }

    /**
     * Test PHP code snippet
     *
     * @param string $code PHP code to test.
     * @return array Test results.
     */
    public function test_code( string $code ): array {
        $result = [
            'success'      => true,
            'syntax_valid' => true,
            'output'       => null,
            'errors'       => [],
        ];

        // Check syntax first
        $syntax_check = $this->check_syntax( "<?php\n" . $code );
        $result['syntax_valid'] = $syntax_check['valid'];
        $result['errors']       = $syntax_check['errors'];

        if ( ! $syntax_check['valid'] ) {
            $result['success'] = false;
            return $result;
        }

        // Security check - don't execute dangerous code
        $security_check = $this->check_security( $code );
        $critical_issues = array_filter( $security_check, function ( $issue ) {
            return $issue['severity'] === 'critical';
        });

        if ( ! empty( $critical_issues ) ) {
            $result['success'] = false;
            $result['errors']  = [
                [
                    'message' => __( 'Code contains security issues and cannot be executed', 'creator-core' ),
                    'issues'  => $critical_issues,
                ],
            ];
            return $result;
        }

        // Note: Actual code execution should be handled very carefully
        // This is a simplified version for demonstration
        $result['message'] = __( 'Code syntax is valid. For security, live execution is disabled.', 'creator-core' );

        return $result;
    }
}
