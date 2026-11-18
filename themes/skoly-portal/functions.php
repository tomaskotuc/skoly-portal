<?php
defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'skoly-portal', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	}
);

/**
 * Výpis škol: řazení podle názvu, 20 na stránku.
 * Filtry fungují přes dotazové proměnné taxonomií (?okres=blansko&typ_studia=maturitni),
 * které WordPress zpracuje sám a kombinuje je jako "a zároveň".
 */
add_action(
	'pre_get_posts',
	function ( WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( 'skola' ) || $query->is_tax( skoly_portal_filters() ) ) {
			$query->set( 'post_type', 'skola' );
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
			$query->set( 'posts_per_page', 20 );
		}
	}
);

/**
 * Taxonomie, podle kterých lze filtrovat: slug => popisek.
 */
function skoly_portal_filters(): array {
	return array(
		'kraj'       => 'Kraj',
		'okres'      => 'Okres',
		'obor'       => 'Skupina oborů',
		'zrizovatel' => 'Zřizovatel',
		'typ_studia' => 'Typ studia',
	);
}

/**
 * Názvy termů školy v taxonomii, oddělené čárkou.
 */
function skoly_portal_terms( int $post_id, string $taxonomy ): string {
	$names = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
	return is_wp_error( $names ) ? '' : implode( ', ', $names );
}
