<?php
/**
 * Gestionnaire de désactivation du plugin
 * Responsabilité : Actions à effectuer lors de la désactivation
 *
 * @package WcCheckoutGuard\Core
 */

namespace WcCheckoutGuard\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Gestionnaire de désactivation
 */
class Deactivator {

	/**
	 * Actions à effectuer lors de la désactivation
	 */
	public static function run() {
		// Nettoyer les tâches cron planifiées
		self::clear_scheduled_hooks();

		// Flush des règles de réécriture
		flush_rewrite_rules();

		// Log de la désactivation
		self::log_deactivation();
	}

	/**
	 * Nettoie les tâches cron planifiées
	 */
	private static function clear_scheduled_hooks() {
		// Nettoyer les hooks cron spécifiques au plugin
		$cron_hooks = array(
			'wc_checkout_guard_log_cleanup',
			'wc_checkout_guard_purge_logs',
		);

		foreach ( $cron_hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Log la désactivation du plugin
	 */
	private static function log_deactivation() {
		// Créer une entrée de log simple pour la désactivation
		$uploads = wp_get_upload_dir();
		$log_file = trailingslashit( $uploads['basedir'] ) . 'tb-logs/wc_checkout_guard.log';

		if ( is_writable( dirname( $log_file ) ) ) {
			$log_entry = array(
				'time' => current_time( 'mysql' ),
				'data' => array(
					'event'   => 'plugin_deactivated',
					'user_id' => get_current_user_id(),
					'ip_hash' => self::hash_ip( $_SERVER['REMOTE_ADDR'] ?? '' ),
				),
			);

			$json_line = json_encode( $log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
			file_put_contents( $log_file, $json_line, FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Hash une adresse IP de manière sécurisée
	 *
	 * @param string $ip Adresse IP.
	 * @return string|null
	 */
	private static function hash_ip( $ip ) {
		$ip = trim( (string) $ip );
		if ( $ip === '' ) {
			return null;
		}

		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}
}
