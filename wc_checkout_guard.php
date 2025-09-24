<?php
/**
 * Plugin Name: WooCommerce checkout guard
 * Plugin URI: https://github.com/SrGabrysh/wc_checkout_guard
 * Description: Plugin WooCommerce limitant les commandes à 1 formation maximum. Redirige le checkout vers le panier si >1 article et bloque la validation. Journalise les visites de /commander/ avec rotation automatique des logs. Interface admin intégrée pour consulter les logs en temps réel.
 * Version: 1.0.0
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

// Sécurité : Empêcher l'accès direct.
defined( 'ABSPATH' ) || exit;

// Constantes du plugin.
define( 'WC_CHECKOUT_GUARD_VERSION', '1.0.0' );
define( 'WC_CHECKOUT_GUARD_PLUGIN_FILE', __FILE__ );
define( 'WC_CHECKOUT_GUARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_CHECKOUT_GUARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Chargement de Composer.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Initialisation du plugin.
add_action(
	'plugins_loaded',
	function() {
		if ( class_exists( '\\WcCheckoutGuard\\Core\\Plugin' ) ) {
			\\WcCheckoutGuard\\Core\\Plugin::get_instance();
		}
	}
);

// Hook d'activation.
register_activation_hook( __FILE__, array( 'WcCheckoutGuard\\Core\\Activator', 'run' ) );

// Hook de désactivation.
register_deactivation_hook( __FILE__, array( 'WcCheckoutGuard\\Core\\Deactivator', 'run' ) );
