<?php
defined( 'ABSPATH' ) || exit;

/**
 * Vlastní typ příspěvku "skola" a taxonomie pro filtrování.
 */
class Skoly_Post_Types {

	public static function register(): void {
		register_post_type(
			'skola',
			array(
				'labels'       => array(
					'name'          => 'Školy',
					'singular_name' => 'Škola',
					'add_new_item'  => 'Přidat školu',
					'edit_item'     => 'Upravit školu',
					'all_items'     => 'Všechny školy',
				),
				'public'       => true,
				'has_archive'  => 'skoly',
				'rewrite'      => array( 'slug' => 'skola' ),
				'menu_icon'    => 'dashicons-welcome-learn-more',
				'supports'     => array( 'title', 'custom-fields' ),
				'show_in_rest' => true,
			)
		);

		// slug => [množné číslo, jednotné číslo, hierarchická?]
		$taxonomies = array(
			'kraj'       => array( 'Kraje', 'Kraj', false ),
			'okres'      => array( 'Okresy', 'Okres', false ),
			'obor'       => array( 'Obory', 'Obor', true ), // rodič = skupina oborů
			'zrizovatel' => array( 'Zřizovatelé', 'Zřizovatel', false ),
			'typ_studia' => array( 'Typy studia', 'Typ studia', false ),
		);

		foreach ( $taxonomies as $slug => list( $plural, $singular, $hierarchical ) ) {
			register_taxonomy(
				$slug,
				'skola',
				array(
					'labels'            => array(
						'name'          => $plural,
						'singular_name' => $singular,
					),
					'hierarchical'      => $hierarchical,
					'public'            => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => array( 'slug' => str_replace( '_', '-', $slug ) ),
				)
			);
		}
	}
}
