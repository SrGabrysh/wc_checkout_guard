<?php
/**
 * Classe d'activation du plugin WooCommerce checkout guard
 *
 * @package WcCheckoutGuard
 */

defined( 'ABSPATH' ) || exit;

namespace WcCheckoutGuard\Core;

/**
 * Classe d'activation du plugin
 */
class Activator {

	/**
	 * Actions à effectuer lors de l'activation du plugin
	 */
	public static function run() {
		// Créer les options par défaut.
		add_option( 'wc_checkout_guard_version', '1.0.0' );
		add_option( 'wc_checkout_guard_settings', array() );

		// Planifier les tâches CRON si nécessaire.
		if ( ! wp_next_scheduled( 'wc_checkout_guard_daily_task' ) ) {
			wp_schedule_event( time(), 'daily', 'wc_checkout_guard_daily_task' );
		}

		// Flush des règles de réécriture.
		flush_rewrite_rules();

		// Log d'activation.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[wc_checkout_guard] Plugin activé avec succès.' );
		}
	}
}
