<?php
/**
 * Plugin Name: Školy – import z Rejstříku škol
 * Description: Import středních škol z Rejstříku škol a školských zařízení (data.gov.cz).
 * Version:     0.1.0
 * Author:      Tomáš Kotuč
 * Text Domain: skoly-import
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'SKOLY_IMPORT_VERSION', '0.1.0' );
define( 'SKOLY_IMPORT_DIR', plugin_dir_path( __FILE__ ) );

require_once SKOLY_IMPORT_DIR . 'includes/class-post-types.php';
require_once SKOLY_IMPORT_DIR . 'includes/class-fields.php';
require_once SKOLY_IMPORT_DIR . 'includes/class-importer.php';
require_once SKOLY_IMPORT_DIR . 'includes/class-admin.php';

add_action( 'init', array( 'Skoly_Post_Types', 'register' ) );
add_action( 'acf/init', array( 'Skoly_Fields', 'register' ) ); // vyžaduje plugin Secure Custom Fields

if ( is_admin() ) {
	( new Skoly_Admin() )->hooks();
}

// Po aktivaci zaregistrovat typy a přegenerovat permalinky, aby fungovaly adresy /skola/...
register_activation_hook(
	__FILE__,
	function () {
		Skoly_Post_Types::register();
		flush_rewrite_rules();
	}
);
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
