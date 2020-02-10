<?php
/**
 * Handling for the Alert bar.
 *
 * @package PFMC_Feature_Set
 */

namespace PFMCFS\Alert;

add_action( 'init', __NAMESPACE__ . '\register_post_type', 10 );
add_action( 'save_post_alert', __NAMESPACE__ . '\save_post_meta', 10, 2 );

/**
 * Register the Alert post type.
 */
function register_post_type() {

	$args = array(
		'label'                => __( 'Alerts', 'pfmc-feature-set' ),
		'labels'               => array(
			'name'          => _x( 'Alerts', 'Post Type General Name', 'pfmc-feature-set' ),
			'singular_name' => _x( 'Alert', 'Post Type Singular Name', 'pfmc-feature-set' ),
			'add_new'       => __( 'Add New Alert', 'pfmc-feature-set' ),
		),
		'description'          => '',
		'public'               => true,
		'exclude_from_search'  => true,
		'show_in_nav_menus'    => false,
		'show_in_rest'         => true,
		'menu_position'        => 25,
		'menu_icon'            => 'dashicons-warning',
		'supports'             => array(
			'title',
			'editor',
			'excerpt',
			'author',
			'revisions',
		),
		'register_meta_box_cb' => __NAMESPACE__ . '\add_meta_boxes',
		'delete_with_user'     => false,
	);

	\register_post_type( 'alert', $args );
}

/**
 * Adds a meta box for managing alert level and display duration.
 */
function add_meta_boxes() {
	add_meta_box(
		'pfmcfs-alert',
		'Alert Settings',
		__NAMESPACE__ . '\display_alert_meta_box',
		'alert',
		'side',
		'high'
	);
}

/**
 * Returns an array of alert level field labels keyed by id.
 *
 * @return array Field values keyed by id.
 */
function get_alert_level_fields() {
	return array(
		'low'    => __( 'Announcement', 'pfmc-feature-set' ),
		'medium' => __( 'High-level announcement', 'pfmc-feature-set' ),
		'high'   => __( 'Safety alert', 'pfmc-feature-set' ),
	);
}

/**
 * Displays a meta box used to manage alert level and display duration.
 *
 * @param \WP_Post $post The post object.
 */
function display_alert_meta_box( $post ) {
	wp_nonce_field( 'pfmcfs_check_alert', 'pfmcfs_alert_nonce' );

	// Get existing meta values.
	$level = get_post_meta( $post->ID, '_pfmcfs_alert_level', true );
	$until = get_post_meta( $post->ID, '_pfmcfs_alert_display_until', true );

	// Set `low` as the default alert level.
	$level = ( $level ) ? $level : 'low';

	// Set the default until value as one day from now.
	if ( ! $until ) {
		$timezone = get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : 'UTC';
		$timezone = new \DateTimeZone( $timezone );
		$today    = new \DateTime( null, $timezone );
		$until    = $today->modify( '+1 day' )->format( 'Y-m-d\TH:i' );
	}

	?>
	<p><?php esc_html_e( 'Alert level', 'pfmc-feature-set' ); ?></p>
	<?php

	foreach ( get_alert_level_fields() as $id => $label ) :
		?>
		<p>
			<input
				type="radio"
				id="pfmcfs-alert_level-<?php echo esc_attr( $id ); ?>"
				name="_pfmcfs_alert_level"
				value="<?php echo esc_attr( $id ); ?>"
				<?php checked( $level, $id ); ?>
			>
			<label for="pfmcfs-alert_level-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		</p>
		<?php
	endforeach;

	?>
	<p>
		<label for="pfmcfs-alert_display-until"><?php esc_html_e( 'Display alert until', 'pfmc-feature-set' ); ?></label>
		<input
			type="datetime-local"
			id="pfmcfs-alert_display-until"
			name="_pfmcfs_alert_display_until"
			value="<?php echo esc_attr( $until ); ?>"
			min="<?php echo esc_attr( $until_default ); ?>"
		/>
	</p>
	<?php
}

/**
 * Saves alert post meta.
 *
 * @param int     $post_id The post ID.
 * @param WP_Post $post    Post object.
 */
function save_post_meta( $post_id, $post ) {

	/**
	 * Return early if:
	 *     the user doesn't have edit permissions;
	 *     this is an autosave;
	 *     this is a revision; or
	 *     the nonce can't be verified.
	 */
	if (
		( ! current_user_can( 'edit_post', $post_id ) )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| wp_is_post_revision( $post_id )
		|| ( ! isset( $_POST['pfmcfs_alert_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pfmcfs_alert_nonce'] ) ), 'pfmcfs_check_alert' ) )
	) {
		return;
	}

	if ( isset( $_POST['_pfmcfs_alert_level'] ) && in_array( $_POST['_pfmcfs_alert_level'], array_keys( get_alert_level_fields() ), true ) ) {
		update_post_meta( $post_id, '_pfmcfs_alert_level', sanitize_text_field( wp_unslash( $_POST['_pfmcfs_alert_level'] ) ) );
	}

	if ( isset( $_POST['_pfmcfs_alert_display_until'] ) && '' !== sanitize_text_field( wp_unslash( $_POST['_pfmcfs_alert_display_until'] ) ) ) {
		update_post_meta( $post_id, '_pfmcfs_alert_display_until', sanitize_text_field( wp_unslash( $_POST['_pfmcfs_alert_display_until'] ) ) );
	}
}
