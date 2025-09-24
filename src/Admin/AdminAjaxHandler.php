<?php
/**
 * Handler AJAX du module Admin
 * Responsabilité : Traitement des requêtes AJAX d'administration
 *
 * @package WcCheckoutGuard\Admin
 */

namespace WcCheckoutGuard\Admin;

use WcCheckoutGuard\Modules\Logging\LoggingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handler des requêtes AJAX d'administration
 */
class AdminAjaxHandler {

	/**
	 * Configuration du handler
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Instance du logger
	 *
	 * @var LoggingManager
	 */
	private $logger;

	/**
	 * Constructeur
	 *
	 * @param array          $config Configuration.
	 * @param LoggingManager $logger Instance du logger.
	 */
	public function __construct( array $config, LoggingManager $logger ) {
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Initialise les hooks AJAX
	 */
	public function init_ajax_hooks() {
		// Actions AJAX pour les utilisateurs connectés
		add_action( 'wp_ajax_wc_checkout_guard_get_logs', array( $this, 'ajax_get_logs' ) );
		add_action( 'wp_ajax_wc_checkout_guard_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_wc_checkout_guard_rotate_log', array( $this, 'ajax_rotate_log' ) );
		add_action( 'wp_ajax_wc_checkout_guard_purge_logs', array( $this, 'ajax_purge_logs' ) );
	}

	/**
	 * Récupère les logs via AJAX
	 */
	public function ajax_get_logs() {
		// Vérification des permissions
		if ( ! $this->verify_ajax_permissions() ) {
			wp_send_json_error( 'Permissions insuffisantes' );
		}

		// Vérification du nonce
		if ( ! $this->verify_ajax_nonce( 'get_logs' ) ) {
			wp_send_json_error( 'Token de sécurité invalide' );
		}

		// Paramètres
		$lines = isset( $_POST['lines'] ) ? max( 10, min( (int) $_POST['lines'], $this->config['max_lines'] ) ) : $this->config['default_lines'];

		// Récupération des logs
		if ( ! $this->logger->log_file_exists() ) {
			wp_send_json_success( array(
				'logs'   => 'Aucun log disponible.',
				'exists' => false,
			) );
		}

		$logs = $this->logger->get_tail_log( $lines );

		wp_send_json_success( array(
			'logs'   => $logs,
			'exists' => true,
			'lines'  => $lines,
		) );
	}

	/**
	 * Récupère les statistiques via AJAX
	 */
	public function ajax_get_stats() {
		// Vérification des permissions
		if ( ! $this->verify_ajax_permissions() ) {
			wp_send_json_error( 'Permissions insuffisantes' );
		}

		// Vérification du nonce
		if ( ! $this->verify_ajax_nonce( 'get_stats' ) ) {
			wp_send_json_error( 'Token de sécurité invalide' );
		}

		// Récupération des statistiques
		$stats = $this->logger->get_stats();

		wp_send_json_success( $stats );
	}

	/**
	 * Force la rotation du log via AJAX
	 */
	public function ajax_rotate_log() {
		// Vérification des permissions
		if ( ! $this->verify_ajax_permissions() ) {
			wp_send_json_error( 'Permissions insuffisantes' );
		}

		// Vérification du nonce
		if ( ! $this->verify_ajax_nonce( 'rotate_log' ) ) {
			wp_send_json_error( 'Token de sécurité invalide' );
		}

		// Rotation
		$result = $this->logger->force_rotation();

		if ( $result ) {
			wp_send_json_success( 'Rotation effectuée avec succès' );
		} else {
			wp_send_json_error( 'Erreur lors de la rotation' );
		}
	}

	/**
	 * Purge les anciens logs via AJAX
	 */
	public function ajax_purge_logs() {
		// Vérification des permissions
		if ( ! $this->verify_ajax_permissions() ) {
			wp_send_json_error( 'Permissions insuffisantes' );
		}

		// Vérification du nonce
		if ( ! $this->verify_ajax_nonce( 'purge_logs' ) ) {
			wp_send_json_error( 'Token de sécurité invalide' );
		}

		// Purge
		$deleted_count = $this->logger->purge_old_logs();

		wp_send_json_success( array(
			'deleted_count' => $deleted_count,
			'message'       => sprintf( '%d fichiers supprimés', $deleted_count ),
		) );
	}

	/**
	 * Vérifie les permissions AJAX
	 *
	 * @return bool
	 */
	private function verify_ajax_permissions() {
		return current_user_can( $this->config['capability'] );
	}

	/**
	 * Vérifie le nonce AJAX
	 *
	 * @param string $action Action à vérifier.
	 * @return bool
	 */
	private function verify_ajax_nonce( $action ) {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		return wp_verify_nonce( $nonce, 'wc_checkout_guard_' . $action );
	}

	/**
	 * Génère un nonce pour une action
	 *
	 * @param string $action Action.
	 * @return string
	 */
	public function create_nonce( $action ) {
		return wp_create_nonce( 'wc_checkout_guard_' . $action );
	}

	/**
	 * Récupère les nonces pour toutes les actions
	 *
	 * @return array
	 */
	public function get_all_nonces() {
		return array(
			'get_logs'    => $this->create_nonce( 'get_logs' ),
			'get_stats'   => $this->create_nonce( 'get_stats' ),
			'rotate_log'  => $this->create_nonce( 'rotate_log' ),
			'purge_logs'  => $this->create_nonce( 'purge_logs' ),
		);
	}

	/**
	 * Met à jour la configuration
	 *
	 * @param array $config Nouvelle configuration.
	 */
	public function update_config( array $config ) {
		$this->config = $config;
	}

	/**
	 * Récupère la configuration actuelle
	 *
	 * @return array
	 */
	public function get_config() {
		return $this->config;
	}
}
