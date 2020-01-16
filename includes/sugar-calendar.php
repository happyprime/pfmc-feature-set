<?php
/**
 * Custom handling of Sugar Calendar plugin.
 *
 * @package PFMC_Feature_Set
 */

namespace PFMCFS\SugarCalendar;

add_filter( 'register_post_type_args', __NAMESPACE__ . '\filter_post_type_args', 10, 2 );
add_action( 'init', __NAMESPACE__ . '\register_categories_for_events', 11 );
add_action( 'after_setup_theme', __NAMESPACE__ . '\remove_default_shortcode_registration' );
add_action( 'init', __NAMESPACE__ . '\add_shortcodes' );
add_filter( 'sc_events_query_clauses', __NAMESPACE__ . '\sugar_calendar_join_by_taxonomy_term', 15, 2 );
add_action( 'sc_parse_events_query', __NAMESPACE__ . '\sugar_calendar_pre_get_events_by_taxonomy', 15 );

/**
 * Expose the `sc_event` post type in the REST API.
 *
 * @param array  $args      Array of arguments for registering a post type.
 * @param string $post_type Post type key.
 */
function filter_post_type_args( $args, $post_type ) {
	if ( 'sc_event' === $post_type ) {
		$args['show_in_rest'] = true;
	}

	return $args;
}

/**
 * Register categories for the `sc_event` post type.
 */
function register_categories_for_events() {
	register_taxonomy_for_object_type( 'category', 'sc_event' );
}

/**
 * Remove the default shortcode registration provided by the Sugar Calendar plugin.
 */
function remove_default_shortcode_registration() {
	remove_action( 'init', 'sc_add_shortcodes' );
}

/**
 * Add a replacement handler for the sc_events_list shortcode and add back
 * the default handler for the sc_events_calendar shortcode, which does not
 * require the same adjustments in this theme.
 */
function add_shortcodes() {
	add_shortcode( 'sc_events_list', __NAMESPACE__ . '\display_sc_events_list_shortcode' );
	add_shortcode( 'sc_events_calendar', 'sc_events_calendar_shortcode' );
}

/**
 * Provide a list of custom taxonomies that have registered support
 * for the sc_event post type.
 *
 * @return array A list of taxonomy slugs.
 */
function get_custom_calendar_taxonomies() {
	$available_taxonomies = get_object_taxonomies( 'sc_event' );

	// This is already handled as the "category" attribute in the shortcode by
	// the core Sugar Calendar plugin code.
	$key = array_search( 'sc_event_category', $available_taxonomies, true );
	if ( false !== $key ) {
		unset( $available_taxonomies[ $key ] );
	}

	// This would conflict with the "category" attribute and is not supported as
	// part of the shortcode query.
	$key = array_search( 'category', $available_taxonomies, true );
	if ( false !== $key ) {
		unset( $available_taxonomies[ $key ] );
	}

	return $available_taxonomies;
}

/**
 * Event list shortcode callback.
 *
 * This is forked from Sugar Calendar Lite and updated to add rudimentary support
 * for querying events by custom taxonomy terms.
 *
 * @since 1.0.0
 *
 * @param array $atts    A list of shortcode attributes.
 * @param null  $content Content that may appear inside the shortcode. Unused.
 *
 * @return string HTML representing the shortcode.
 */
function display_sc_events_list_shortcode( $atts, $content = null ) {

	$default_atts = array(
		'display'         => 'upcoming',
		'order'           => '',
		'number'          => '5',
		'category'        => null,
		'show_date'       => null,
		'show_time'       => null,
		'show_categories' => null,
		'show_link'       => null,
	);

	$available_taxonomies = get_custom_calendar_taxonomies();

	foreach ( $available_taxonomies as $taxonomy ) {
		$default_atts[ $taxonomy ] = null;
	}

	$atts = shortcode_atts( $default_atts, $atts );

	$display         = esc_attr( $atts['display'] );
	$order           = esc_attr( $atts['order'] );
	$number          = esc_attr( $atts['number'] );
	$show_date       = esc_attr( $atts['show_date'] );
	$show_time       = esc_attr( $atts['show_time'] );
	$show_categories = esc_attr( $atts['show_categories'] );
	$show_link       = esc_attr( $atts['show_link'] );

	$taxonomies = array(
		'category' => esc_attr( $atts['category'] ),
	);

	foreach ( $available_taxonomies as $taxonomy ) {
		if ( isset( $atts[ $taxonomy ] ) ) {
			$taxonomies[ $taxonomy ] = esc_attr( $atts[ $taxonomy ] );
		}
	}

	$args = array(
		'date'       => $show_date,
		'time'       => $show_time,
		'categories' => $show_categories,
		'link'       => $show_link,
	);

	return get_events_list( $display, $taxonomies, $number, $args, $order );
}

/**
 * Get a formatted list of upcoming or past events from today's date.
 *
 * This is forked from Sugar Calendar Lite and updated to add rudimentary support
 * for querying events by custom taxonomy terms.
 *
 * @see sc_events_list_widget
 *
 * @since 1.0.0
 * @param string $display    Whether to display upcoming, past, or all events.
 * @param array  $taxonomies Taxonomies to use.
 * @param int    $number     Number of events to display.
 * @param array  $show       A series of arguments.
 * @param string $order      The order in which to display events.
 *
 * @return string HTML representing a list of events.
 */
function get_events_list( $display = 'upcoming', $taxonomies = array(), $number = 5, $show = array(), $order = '' ) {

	// Get today, to query before/after.
	$today = date( 'Y-m-d' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

	// Mutate order to uppercase if not empty.
	if ( ! empty( $order ) ) {
		$order = strtoupper( $order );
	} else {
		$order = ( 'past' === $display )
			? 'DESC'
			: 'ASC';
	}

	// Maybe force a default.
	if ( ! in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ) {
		$order = 'ASC';
	}

	if ( 'upcoming' === $display ) {
		$args = array(
			'object_type' => 'post',
			'status'      => 'publish',
			'orderby'     => 'start',
			'order'       => $order,
			'number'      => $number,
			'start_query' => array(
				'inclusive' => true,
				'after'     => $today,
			),
		);
	} elseif ( 'past' === $display ) {
		$args = array(
			'object_type' => 'post',
			'status'      => 'publish',
			'orderby'     => 'start',
			'order'       => $order,
			'number'      => $number,
			'start_query' => array(
				'inclusive' => true,
				'before'    => $today,
			),
		);
	} else {
		// All events.
		$args = array(
			'object_type' => 'post',
			'status'      => 'publish',
			'orderby'     => 'start',
			'order'       => $order,
			'number'      => $number,
		);
	}

	// Maybe filter by taxonomy term.
	if ( ! empty( $taxonomies['category'] ) ) {
		$args[ sugar_calendar_get_calendar_taxonomy_id() ] = $taxonomies['category'];
		unset( $taxonomies['category'] );
	}

	foreach ( $taxonomies as $taxonomy => $term ) {
		$args[ $taxonomy ] = esc_attr( $term );
	}

	// Query for events.
	$events = sugar_calendar_get_events( $args );

	// Bail if no events.
	if ( empty( $events ) ) {
		return '';
	}

	// Start an output buffer to store these result.
	ob_start();

	do_action( 'sc_before_events_list' );

	// Start an unordered list.
	echo '<ul class="sc_events_list">';

	// Loop through all events.
	foreach ( $events as $event ) {

		// Get the object ID and use it for the event ID (for back compat).
		$event_id = $event->object_id;

		echo '<li class="' . esc_attr( str_replace( 'hentry', '', implode( ' ', get_post_class( 'sc_event', $event_id ) ) ) ) . '">';

		do_action( 'sc_before_event_list_item', $event_id );

		echo '<a href="' . esc_url( get_permalink( $event_id ) ) . '" class="sc_event_link">';
		echo '<span class="sc_event_title">' . wp_kses_post( get_the_title( $event_id ) ) . '</span></a>';

		if ( ! empty( $show['date'] ) ) {
			echo '<span class="sc_event_date">' . esc_html( sc_get_formatted_date( $event_id ) ) . '</span>';
		}

		if ( isset( $show['time'] ) && $show['time'] ) {
			$start_time = sc_get_event_start_time( $event_id );
			$end_time   = sc_get_event_end_time( $event_id );

			if ( $event->is_all_day() ) {
				echo '<span class="sc_event_time">' . esc_html__( 'All-day', 'pfmc-feature-set' ) . '</span>';
			} elseif ( $end_time !== $start_time ) {
				echo '<span class="sc_event_time">' . esc_html( $start_time ) . '&nbsp;&ndash;&nbsp;' . esc_html( $end_time ) . '</span>';
			} elseif ( ! empty( $start_time ) ) {
				echo '<span class="sc_event_time">' . esc_html( $start_time ) . '</span>';
			}
		}

		if ( ! empty( $show['categories'] ) ) {
			$event_categories = get_the_terms( $event_id, 'sc_event_category' );

			if ( $event_categories ) {
				$categories = wp_list_pluck( $event_categories, 'name' );
				echo '<span class="sc_event_categories">' . esc_html( join( $categories, ', ' ) ) . '</span>';
			}
		}

		if ( ! empty( $show['link'] ) ) {
			echo '<a href="' . esc_url( get_permalink( $event_id ) ) . '" class="sc_event_link">';
			echo esc_html__( 'Read More', 'pfmc-feature-set' );
			echo '</a>';
		}

		do_action( 'sc_after_event_list_item', $event_id );

		echo '<br class="clear"></li>';
	}

	// Close the list.
	echo '</ul>';

	// Reset post data - we'll be looping through our own.
	wp_reset_postdata();

	do_action( 'sc_after_events_list' );

	// Return the current buffer and delete it.
	return ob_get_clean();
}

/**
 * Filter events query variables and maybe add the taxonomy and term.
 *
 * This filter is necessary to ensure events queries are cached using the
 * taxonomy and term they are queried by.
 *
 * This is forked from Sugar Calendar Lite and updated to add rudimentary support
 * for querying events by custom taxonomy terms.
 *
 * @since 2.0.0
 *
 * @param object|Query $query The current query being adjusted.
 */
function sugar_calendar_pre_get_events_by_taxonomy( $query ) {

	$available_taxonomies = get_custom_calendar_taxonomies();

	foreach ( $available_taxonomies as $taxonomy ) {
		if ( isset( $query->query_var_originals[ $taxonomy ] ) ) {
			$query->set_query_var( $taxonomy, $query->query_var_originals[ $taxonomy ] );
		}
	}
}

/**
 * Filter events queries and maybe JOIN by taxonomy term relationships
 *
 * This is hard-coded (for now) to provide back-compat with the built-in
 * post-type & taxonomy. It can be expanded to support any/all in future versions.
 *
 * This is forked from Sugar Calendar Lite and updated to add rudimentary support
 * for querying events by custom taxonomy terms.
 *
 * @since 2.0.0
 *
 * @param array        $clauses Clauses to be used in a query.
 * @param object|Query $query   The current query being adjusted.
 *
 * @return array Clauses to be used in a query.
 */
function sugar_calendar_join_by_taxonomy_term( $clauses = array(), $query = false ) {

	$available_taxonomies = get_custom_calendar_taxonomies();

	$join_clauses   = array();
	$join_clauses[] = $clauses['join'];

	$where_clauses   = array();
	$where_clauses[] = $clauses['where'];

	$replacement = 1;

	foreach ( $available_taxonomies as $taxonomy ) {
		if ( isset( $query->query_var_originals[ $taxonomy ] ) ) {
			$tax_query = new \WP_Tax_Query(
				array(
					array(
						'taxonomy' => $taxonomy,
						'terms'    => $query->query_var_originals[ $taxonomy ],
						'field'    => 'slug',
					),
				)
			);

			// Get the clauses as provided by WP_Tax_Query.
			$sql_clauses = $tax_query->get_sql( 'sc_e', 'object_id' );

			// JOIN the table as wptr(n) to avoid conflicts with other term queries. This is admittedly kind of ugly,
			// but works around the abilities we have (as I understand them) with the query classes in Sugar Calendar Lite.
			$join_clause    = str_replace( 'wp_term_relationships ON', 'wp_term_relationships AS wptr' . $replacement . ' ON', $sql_clauses['join'] );
			$join_clause    = str_replace( 'wp_term_relationships.', 'wptr' . $replacement . '.', $join_clause );
			$join_clauses[] = $join_clause;

			// As with the JOIN, rename the wp_term_relationship table to wptr(n) in the WHERE clause.
			$where_clauses[] = str_replace( 'wp_term_relationships', 'wptr' . $replacement, $sql_clauses['where'] );

			// Increment (n) as used in the wptr(n) table name aliases.
			$replacement++;
		}
	}

	// Bring all of the clauses back together as their expected strings.
	$clauses['join']  = implode( '', array_filter( $join_clauses ) );
	$clauses['where'] = implode( '', array_filter( $where_clauses ) );

	return $clauses;
}