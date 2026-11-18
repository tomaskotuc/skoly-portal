<?php
defined( 'ABSPATH' ) || exit;

/**
 * Import středních škol z datové sady Rejstříku škol a školských zařízení (JSON-LD) jednoho kraje.
 *
 * Struktura zdroje (zkráceně):
 * { "datumVystupu": "...", "list": [ právnický subjekt, ... ] }
 * právnický subjekt: redIzo, uplnyNazev, kraj, adresa{...}, typZrizovatele, skolyAZarizeni[ ... ]
 * škola/zařízení: izo, uplnyNazev, druh, mistaVyuky[ {adresa{obec, okres, ...}} ], obory[ {kod, nazev, ...} ]
 */
class Skoly_Importer {

	/**
	 * Kódy druhu školy (číselník AKDT), které považujeme za střední školu.
	 * TODO: ověřit v číselníku AKDT (předpoklad: střední škola = druh začínající "C").
	 */
	const DRUH_STREDNI_SKOLA_PREFIX = 'C';

	/**
	 * Typ zřizovatele (číselník BAZS). Hodnoty 1, 2, 5, 6, 7 odpovídají datům JMK,
	 * ostatní doplnit z číselníku BAZS.
	 */
	const ZRIZOVATELE = array(
		'1' => 'MŠMT',
		'2' => 'Obec',
		'5' => 'Soukromý zřizovatel',
		'6' => 'Církev',
		'7' => 'Kraj',
	);

	public function run( string $url ): array {
		$result = array(
			'time'    => current_time( 'mysql' ),
			'status'  => 'error',
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'message' => '',
		);

		if ( '' === $url ) {
			$result['message'] = 'Není nastavena URL zdrojové datové sady.';
			return $result;
		}

		if ( ! function_exists( 'update_field' ) ) {
			$result['message'] = 'Chybí aktivní plugin Secure Custom Fields, do kterého import ukládá údaje škol.';
			return $result;
		}

		// Soubor kraje má desítky MB, potřebujeme víc paměti a času.
		wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 600 );
		}

		// 1. Stažení do dočasného souboru. Při chybě končíme a data na webu zůstanou beze změny.
		$file = $this->download( $url );
		if ( is_wp_error( $file ) ) {
			$result['message'] = 'Stažení selhalo: ' . $file->get_error_message();
			return $result;
		}

		// 2. Načtení a kontrola JSON.
		$data = $this->load_json( $file );
		wp_delete_file( $file );
		if ( is_wp_error( $data ) ) {
			$result['message'] = $data->get_error_message();
			return $result;
		}

		// 3. Výběr středních škol.
		$schools = $this->extract_schools( $data['list'] );
		unset( $data );
		if ( empty( $schools ) ) {
			$result['message'] = 'V datech nebyla nalezena žádná střední škola. Nic nebylo změněno.';
			return $result;
		}

		// 4. Uložení: aktualizace podle RED_IZO, jinak vytvoření nové školy.
		foreach ( $schools as $school ) {
			$status = $this->save_school( $school );
			$result[ $status ]++;
		}

		$result['status']  = 'ok';
		$result['message'] = sprintf( 'Import dokončen, zpracováno %d středních škol.', count( $schools ) );
		return $result;
	}

	/**
	 * @return string|WP_Error Cesta k dočasnému souboru.
	 */
	private function download( string $url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		return download_url( $url, 300 );
	}

	/**
	 * Poznámka: json_decode načte celý soubor do paměti. Pro větší kraje
	 * může být později potřeba streamovací parser.
	 *
	 * @return array|WP_Error
	 */
	private function load_json( string $file ) {
		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $raw || '' === $raw ) {
			return new WP_Error( 'skoly_json', 'Stažený soubor je prázdný.' );
		}

		$data = json_decode( $raw, true );
		unset( $raw );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'skoly_json', 'Soubor se nepodařilo načíst jako JSON: ' . json_last_error_msg() );
		}
		if ( empty( $data['list'] ) || ! is_array( $data['list'] ) ) {
			return new WP_Error( 'skoly_json', 'Soubor nemá očekávanou strukturu (chybí seznam "list").' );
		}

		if ( ! empty( $data['datumVystupu'] ) ) {
			update_option( 'skoly_import_datum_dat', sanitize_text_field( $data['datumVystupu'] ) );
		}
		return $data;
	}

	/**
	 * Z právnických subjektů vybere ty, které mají součást typu střední škola.
	 * Osobní údaje (ředitel, statutární orgány, zřizovatelé – fyzické osoby, e-maily) se nepřebírají.
	 */
	private function extract_schools( array $subjects ): array {
		$schools = array();

		foreach ( $subjects as $subject ) {
			if ( empty( $subject['redIzo'] ) || empty( $subject['skolyAZarizeni'] ) ) {
				continue;
			}

			$mista = array();
			$obory = array();

			foreach ( $subject['skolyAZarizeni'] as $soucast ) {
				$druh = (string) ( $soucast['druh'] ?? '' );
				if ( 0 !== strpos( $druh, self::DRUH_STREDNI_SKOLA_PREFIX ) ) {
					continue; // MŠ, ZŠ, jídelny, družiny… přeskakujeme
				}

				foreach ( $soucast['mistaVyuky'] ?? array() as $misto ) {
					$adresa  = $misto['adresa'] ?? array();
					$mista[] = array(
						'adresa' => $this->format_address( $adresa ),
						'obec'   => (string) ( $adresa['obec'] ?? '' ),
						'okres'  => (string) ( $adresa['okres'] ?? '' ), // u míst výuky je to název okresu
					);
				}

				foreach ( $soucast['obory'] ?? array() as $obor ) {
					if ( empty( $obor['kod'] ) ) {
						continue;
					}
					$forma = (string) ( $obor['formaVzdelavani'] ?? '' ); // kód RAFS, převod doplnit
					$delka = (string) ( $obor['delkaVzdelavani'] ?? '' ); // kód RADS, převod doplnit

					// Klíč = kód + forma + délka: stejný obor může mít víc variant (denní/dálková, různá délka).
					$obory[ $obor['kod'] . '|' . $forma . '|' . $delka ] = array(
						'kod'      => (string) $obor['kod'],
						'nazev'    => (string) ( $obor['nazev'] ?? '' ),
						'forma'    => $forma,
						'delka'    => $delka,
						'dobihaji' => ! empty( $obor['dobihajiciObor'] ),
					);
				}
			}

			if ( empty( $mista ) && empty( $obory ) ) {
				continue; // subjekt nemá střední školu
			}

			$typ_zriz  = (string) ( $subject['typZrizovatele'] ?? '' );
			$schools[] = array(
				'red_izo'    => (string) $subject['redIzo'],
				'nazev'      => (string) ( $subject['uplnyNazev'] ?? '' ),
				'adresa'     => $this->format_address( $subject['adresa'] ?? array() ),
				'kraj'       => (string) ( $subject['kraj'] ?? '' ),
				'zrizovatel' => self::ZRIZOVATELE[ $typ_zriz ] ?? ( '' !== $typ_zriz ? 'Kód ' . $typ_zriz : '' ),
				'mista'      => $mista,
				'obory'      => array_values( $obory ),
			);
		}

		return $schools;
	}

	/**
	 * Sestaví adresu, např. "Šrámkova 432/14, Lesná, 638 00 Brno" nebo "č.p. 135, 664 01 Kanice".
	 */
	private function format_address( array $a ): string {
		$cislo = (string) ( $a['cisloDomovni'] ?? '' );
		if ( ! empty( $a['cisloOrientacni'] ) ) {
			$cislo .= '/' . $a['cisloOrientacni'] . ( $a['dodatekOrientacnihoCisla'] ?? '' );
		}

		$ulice = ! empty( $a['ulice'] ) ? trim( $a['ulice'] . ' ' . $cislo ) : trim( ( $a['typCislaDomovniho'] ?? 'č.p.' ) . ' ' . $cislo );
		$parts = array( $ulice );

		if ( ! empty( $a['castObce'] ) && ( $a['castObce'] ?? '' ) !== ( $a['obec'] ?? '' ) ) {
			$parts[] = $a['castObce'];
		}
		$parts[] = trim( ( $a['psc'] ?? '' ) . ' ' . ( $a['obec'] ?? '' ) );

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Typ studia podle písmene v kódu oboru, např. 18-20-M/01 → maturitní.
	 */
	private function typ_studia( string $kod ): string {
		if ( ! preg_match( '/^\d{2}-\d{2}-([A-Z])\/(\d)/', $kod, $m ) ) {
			return '';
		}
		switch ( $m[1] ) {
			case 'K':
			case 'M':
				return 'Maturitní';
			case 'L':
				return '5' === $m[2] ? 'Nástavbové' : 'Maturitní';
			case 'H':
			case 'E':
				return 'Učební';
			default:
				return 'Ostatní';
		}
	}

	/**
	 * @return string 'created' | 'updated' | 'skipped'
	 */
	private function save_school( array $school ): string {
		if ( '' === $school['red_izo'] || '' === $school['nazev'] ) {
			return 'skipped';
		}

		$existing = get_posts(
			array(
				'post_type'      => 'skola',
				'post_status'    => 'any',
				'meta_key'       => 'red_izo',
				'meta_value'     => $school['red_izo'],
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);

		$postarr = array(
			'post_type'   => 'skola',
			'post_status' => 'publish',
			'post_title'  => $school['nazev'],
		);

		if ( $existing ) {
			$postarr['ID'] = $existing[0];
			$post_id       = wp_update_post( $postarr, true );
			$status        = 'updated';
		} else {
			$post_id = wp_insert_post( $postarr, true );
			$status  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			return 'skipped';
		}

		// Pole SCF ukládáme podle klíče pole (viz Skoly_Fields), repeatery přijímají pole řádků.
		update_field( 'field_skola_red_izo', $school['red_izo'], $post_id );
		update_field( 'field_skola_adresa_sidla', $school['adresa'], $post_id );
		update_field( 'field_skola_mista_vyuky', $school['mista'], $post_id );
		update_field( 'field_skola_obory', $school['obory'], $post_id );
		update_field( 'field_skola_datum_aktualizace', current_time( 'Ymd' ), $post_id ); // date_picker ukládá Ymd

		// Taxonomie: kraj, všechny okresy míst výuky, zřizovatel, obory se skupinou, typ studia.
		wp_set_object_terms( $post_id, array_filter( array( $school['kraj'] ) ), 'kraj' );
		wp_set_object_terms( $post_id, array_values( array_unique( array_filter( array_column( $school['mista'], 'okres' ) ) ) ), 'okres' );
		wp_set_object_terms( $post_id, array_filter( array( $school['zrizovatel'] ) ), 'zrizovatel' );

		$obor_ids = array();
		$typy     = array();
		foreach ( $school['obory'] as $obor ) {
			$skupina_id = $this->ensure_term( 'Skupina ' . substr( $obor['kod'], 0, 2 ), 'skupina-' . substr( $obor['kod'], 0, 2 ), 'obor' ); // název skupiny doplnit z číselníku
			$obor_id    = $this->ensure_term( $obor['nazev'] ?: $obor['kod'], sanitize_title( $obor['kod'] ), 'obor', $skupina_id );
			if ( $obor_id ) {
				$obor_ids[] = $obor_id;
			}
			$typ = $this->typ_studia( $obor['kod'] );
			if ( $typ ) {
				$typy[] = $typ;
			}
		}
		wp_set_object_terms( $post_id, array_values( array_unique( $obor_ids ) ), 'obor' ); // varianty oboru sdílí jeden term
		wp_set_object_terms( $post_id, array_values( array_unique( $typy ) ), 'typ_studia' );

		return $status;
	}

	/**
	 * Najde term podle slugu, nebo ho vytvoří. Vrací ID termu (0 při chybě).
	 */
	private function ensure_term( string $name, string $slug, string $taxonomy, int $parent = 0 ): int {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$new = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'parent' => $parent ) );
		return is_wp_error( $new ) ? 0 : (int) $new['term_id'];
	}
}
