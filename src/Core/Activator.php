<?php
/**
 * Gestionnaire d'activation du plugin
 * Responsabilité : Actions à effectuer lors de l'activation
 *
 * @package WcCheckoutGuard\Core
 */

namespace WcCheckoutGuard\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Gestionnaire d'activation
 */
class Activator {

	/**
	 * Actions à effectuer lors de l'activation
	 */
	public static function run() {
		// Vérification des prérequis
		if ( ! self::check_requirements() ) {
			self::deactivate_with_message();
			return;
		}

		// Création des options par défaut
		self::create_default_options();

		// Sécurisation du répertoire de logs
		self::secure_logs_directory();

		// Flush des règles de réécriture
		flush_rewrite_rules();
	}

	/**
	 * Vérification des prérequis
	 *
	 * @return bool
	 */
	private static function check_requirements() {
		// Vérifier PHP
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			return false;
		}

		// Vérifier WordPress
		if ( version_compare( get_bloginfo( 'version' ), '6.6', '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Crée les options par défaut
	 */
	private static function create_default_options() {
		$default_options = array(
			'enable_checkout_guard' => true,
			'enable_logging'        => true,
			'enable_admin'          => true,
			'debug_mode'            => false,
			'max_qty'               => 1,
			'checkout_page'         => 'commander',
			'cart_url'              => 'panier',
		);

		add_option( 'wc_checkout_guard_settings', $default_options );
	}

	/**
	 * Sécurise le répertoire de logs
	 */
	private static function secure_logs_directory() {
		$uploads = wp_get_upload_dir();
		$log_dir = trailingslashit( $uploads['basedir'] ) . 'tb-logs';

		// Créer le répertoire si nécessaire
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Créer .htaccess pour bloquer l'accès web
		$htaccess_file = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, "Deny from all\n" );
		}

		// Créer index.php pour plus de sécurité
		$index_file = $log_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php // Silence is golden.\n" );
		}
	}

	/**
	 * Désactive le plugin avec un message d'erreur
	 */
	private static function deactivate_with_message() {
		deactivate_plugins( plugin_basename( WC_CHECKOUT_GUARD_PLUGIN_FILE ) );

		$message = sprintf(
			'Le plugin WooCommerce Checkout Guard nécessite PHP 8.1+ et WordPress 6.6+. Versions actuelles : PHP %s, WordPress %s',
			PHP_VERSION,
			get_bloginfo( 'version' )
		);

		wp_die( $message, 'Prérequis non satisfaits', array( 'back_link' => true ) );
	}
}