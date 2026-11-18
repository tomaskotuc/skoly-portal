<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pole školy přes Secure Custom Fields (API kompatibilní s ACF).
 * Skupina je registrovaná v kódu, aby byla verzovaná spolu s pluginem.
 * Názvy polí odpovídají meta klíčům, které plní importér.
 */
class Skoly_Fields {

	const GROUP = 'group_skola_rejstrik';

	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => self::GROUP,
				'title'    => 'Údaje z Rejstříku škol',
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'skola',
						),
					),
				),
				'position' => 'normal',
				'fields'   => array(
					array(
						'key'     => 'field_skola_info',
						'label'   => '',
						'name'    => '',
						'type'    => 'message',
						'message' =>'Údaje se plní automaticky z Rejstříku škol. Ruční změny přepíše další import.',
					),
					array(
						'key'   => 'field_skola_red_izo',
						'label' => 'RED_IZO',
						'name'  => 'red_izo',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_skola_adresa_sidla',
						'label' => 'Adresa sídla',
						'name'  => 'adresa_sidla',
						'type'  => 'text',
					),
					array(
						'key'            => 'field_skola_datum_aktualizace',
						'label'          => 'Datum aktualizace',
						'name'           => 'datum_aktualizace',
						'type'           => 'date_picker',
						'display_format' => 'j. n. Y',
						'return_format'  => 'j. n. Y',
					),
					array(
						'key'          => 'field_skola_mista_vyuky',
						'label'        => 'Místa výuky',
						'name'         => 'mista_vyuky',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Přidat místo výuky',
						'sub_fields'   => array(
							self::sub( 'field_skola_misto_adresa', 'Adresa', 'adresa' ),
							self::sub( 'field_skola_misto_obec', 'Obec', 'obec' ),
							self::sub( 'field_skola_misto_okres', 'Okres', 'okres' ),
						),
					),
					array(
						'key'          => 'field_skola_obory',
						'label'        => 'Obory',
						'name'         => 'obory',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Přidat obor',
						'sub_fields'   => array(
							self::sub( 'field_skola_obor_kod', 'Kód', 'kod' ),
							self::sub( 'field_skola_obor_nazev', 'Název', 'nazev' ),
							self::sub( 'field_skola_obor_forma', 'Forma (RAFS)', 'forma' ),
							self::sub( 'field_skola_obor_delka', 'Délka (RADS)', 'delka' ),
							array(
								'key'   => 'field_skola_obor_dobihaji',
								'label' => 'Dobíhající',
								'name'  => 'dobihaji',
								'type'  => 'true_false',
								'ui'    => 1,
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Textové podpole repeateru.
	 */
	private static function sub( string $key, string $label, string $name ): array {
		return array(
			'key'   => $key,
			'label' => $label,
			'name'  => $name,
			'type'  => 'text',
		);
	}
}
