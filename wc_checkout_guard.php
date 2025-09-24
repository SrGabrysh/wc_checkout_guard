<?php
/**
 * Plugin Name: WooCommerce Checkout Guard
 * Plugin URI: https://github.com/tb-web/wc_checkout_guard
 * Description: Plugin WooCommerce limitant les commandes à 1 formation maximum. Redirige le checkout vers le panier si >1 article et bloque la validation. Journalise les visites de /commander/ avec rotation automatique des logs. Interface admin intégrée pour consulter les logs en temps réel.
 * Version: 1.0.1
 * Author: TB-Web
 * Author URI: https://tb-web.fr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc_checkout_guard
 * Domain Path: /languages
 * Requires at least: 6.6
 * Tested up to: 6.7
 * Requires PHP: 8.1
 * Network: false
 */

declare( strict_types=1 );

// Sécurité : Empêcher l'accès direct
defined( 'ABSPATH' ) || exit;

// Constantes du plugin
define( 'WC_CHECKOUT_GUARD_VERSION', '1.0.1' );
define( 'WC_CHECKOUT_GUARD_PLUGIN_FILE', __FILE__ );
define( 'WC_CHECKOUT_GUARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_CHECKOUT_GUARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload Composer
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Hooks d'activation/désactivation
register_activation_hook( __FILE__, array( 'WcCheckoutGuard\\Core\\Activator', 'run' ) );
register_deactivation_hook( __FILE__, array( 'WcCheckoutGuard\\Core\\Deactivator', 'run' ) );

// Initialisation du plugin
add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'wc_checkout_guard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	
	if ( class_exists( 'WcCheckoutGuard\\Core\\Plugin' ) ) {
		WcCheckoutGuard\Core\Plugin::get_instance();
	}
} );
