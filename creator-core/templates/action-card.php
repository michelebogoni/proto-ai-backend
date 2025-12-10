<?php
/**
 * Action Card Template
 *
 * @package CreatorCore
 * @var array $action Action data
 */

defined( 'ABSPATH' ) || exit;

$status_class = $action['status'] ?? 'pending';
$status_icon  = 'marker';
$status_text  = __( 'Pending', 'creator-core' );

switch ( $status_class ) {
    case 'completed':
        $status_icon = 'yes-alt';
        $status_text = __( 'Completed', 'creator-core' );
        break;
    case 'failed':
        $status_icon = 'dismiss';
        $status_text = __( 'Failed', 'creator-core' );
        break;
    case 'executing':
        $status_icon = 'update';
        $status_text = __( 'Executing', 'creator-core' );
        break;
}
?>
<div class="creator-action-card creator-action-<?php echo esc_attr( $status_class ); ?>" data-action-id="<?php echo esc_attr( $action['id'] ?? '' ); ?>">
    <div class="creator-action-header">
        <span class="creator-action-icon">
            <span class="dashicons dashicons-admin-tools"></span>
        </span>
        <span class="creator-action-title">
            <?php echo esc_html( ucwords( str_replace( '_', ' ', $action['action_type'] ?? 'Action' ) ) ); ?>
        </span>
        <span class="creator-action-status creator-status-<?php echo esc_attr( $status_class ); ?>">
            <span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>"></span>
            <?php echo esc_html( $status_text ); ?>
        </span>
    </div>

    <?php if ( ! empty( $action['target'] ) ) : ?>
        <div class="creator-action-target">
            <?php esc_html_e( 'Target:', 'creator-core' ); ?> <?php echo esc_html( $action['target'] ); ?>
        </div>
    <?php endif; ?>

    <?php if ( $status_class === 'failed' && ! empty( $action['error_message'] ) ) : ?>
        <div class="creator-action-error">
            <span class="dashicons dashicons-warning"></span>
            <?php echo esc_html( $action['error_message'] ); ?>
        </div>
    <?php endif; ?>

    <?php if ( $status_class === 'completed' ) : ?>
        <div class="creator-action-buttons">
            <?php if ( ! empty( $action['snapshot_id'] ) ) : ?>
                <button type="button"
                    class="creator-btn creator-btn-outline creator-btn-sm creator-undo-btn"
                    data-action-id="<?php echo esc_attr( $action['id'] ); ?>"
                    data-snapshot-id="<?php echo esc_attr( $action['snapshot_id'] ); ?>">
                    <span class="dashicons dashicons-undo"></span>
                    <?php esc_html_e( 'Undo', 'creator-core' ); ?>
                </button>
            <?php endif; ?>

            <?php
            // Show relevant link based on action type
            $action_type = $action['action_type'] ?? '';
            if ( in_array( $action_type, [ 'create_post', 'create_page', 'update_post', 'update_page' ], true ) ) :
                $post_id = $action['target'] ?? 0;
                if ( $post_id ) :
                    ?>
                    <a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>"
                       class="creator-btn creator-btn-outline creator-btn-sm"
                       target="_blank">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Edit', 'creator-core' ); ?>
                    </a>

                    <?php if ( class_exists( '\Elementor\Plugin' ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) ); ?>"
                           class="creator-btn creator-btn-outline creator-btn-sm"
                           target="_blank">
                            <span class="dashicons dashicons-welcome-widgets-menus"></span>
                            <?php esc_html_e( 'Elementor', 'creator-core' ); ?>
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"
                       class="creator-btn creator-btn-outline creator-btn-sm"
                       target="_blank">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e( 'View', 'creator-core' ); ?>
                    </a>
                    <?php
                endif;
            endif;
            ?>
        </div>
    <?php endif; ?>
</div>
