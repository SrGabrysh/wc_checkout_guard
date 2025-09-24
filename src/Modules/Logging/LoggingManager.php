<?php
/**
 * Gestionnaire principal du module Logging
 * Responsabilité : Orchestration des fonctionnalités de logging
 *
 * @package WcCheckoutGuard\Modules\Logging
 */

namespace WcCheckoutGuard\Modules\Logging;

defined( 'ABSPATH' ) || exit;

/**
 * Gestionnaire du module Logging
 */
class LoggingManager {

	/**
	 * Instance du handler de logging
	 *
	 * @var LoggingHandler
	 */
	private $handler;

	/**
	 * Instance du validateur
	 *
	 * @var LoggingValidator
	 */
	private $validator;

	/**
	 * Configuration du logging
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->config = $this->get_default_config();
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Configuration par défaut du logging
	 *
	 * @return array
	 */
	private function get_default_config() {
		$uploads = wp_get_upload_dir();

		return array(
			'log_dir_name'    => 'tb-logs',
			'log_filename'    => 'wc_checkout_guard.log',
			'max_log_size'    => 5 * 1024 * 1024, // 5 Mo
			'purge_keep_days' => 14,
			'log_base_path'   => trailingslashit( $uploads['basedir'] ),
		);
	}

	/**
	 * Initialise les composants du module
	 */
	private function init_components() {
		$this->validator = new LoggingValidator( $this->config );
		$this->handler   = new LoggingHandler( $this->config, $this->validator );
	}

	/**
	 * Initialise les hooks WordPress
	 */
	private function init_hooks() {
		add_action( 'init', array( $this->handler, 'ensure_log_dir_secure' ) );
	}

	/**
	 * Log des données JSON
	 *
	 * @param array $data Données à logger.
	 * @return bool
	 */
	public function log_json( array $data ) {
		if ( ! $this->validator->is_log_data_valid( $data ) ) {
			return false;
		}

		return $this->handler->write_log_entry( $data );
	}

	/**
	 * Log un message simple
	 *
	 * @param string $message Message à logger.
	 * @param string $level   Niveau de log (info, warning, error).
	 * @param array  $context Contexte additionnel.
	 * @return bool
	 */
	public function log( string $message, string $level = 'info', array $context = array() ) {
		$data = array(
			'event'   => 'log_message',
			'level'   => $level,
			'message' => $message,
			'context' => $context,
		);

		return $this->log_json( $data );
	}

	/**
	 * Log un message d'information
	 *
	 * @param string $message Message.
	 * @param array  $context Contexte.
	 * @return bool
	 */
	public function info( string $message, array $context = array() ) {
		return $this->log( $message, 'info', $context );
	}

	/**
	 * Log un avertissement
	 *
	 * @param string $message Message.
	 * @param array  $context Contexte.
	 * @return bool
	 */
	public function warning( string $message, array $context = array() ) {
		return $this->log( $message, 'warning', $context );
	}

	/**
	 * Log une erreur
	 *
	 * @param string $message Message.
	 * @param array  $context Contexte.
	 * @return bool
	 */
	public function error( string $message, array $context = array() ) {
		return $this->log( $message, 'error', $context );
	}

	/**
	 * Récupère le chemin du fichier de log principal
	 *
	 * @return string
	 */
	public function get_log_file_path() {
		return $this->handler->get_log_file_path();
	}

	/**
	 * Récupère les dernières lignes du log
	 *
	 * @param int $lines Nombre de lignes à récupérer.
	 * @return string
	 */
	public function get_tail_log( int $lines = 200 ) {
		return $this->handler->tail_file( $this->get_log_file_path(), $lines );
	}

	/**
	 * Vérifie si le fichier de log existe
	 *
	 * @return bool
	 */
	public function log_file_exists() {
		return $this->handler->log_file_exists();
	}

	/**
	 * Récupère la taille du fichier de log
	 *
	 * @return int
	 */
	public function get_log_file_size() {
		return $this->handler->get_log_file_size();
	}

	/**
	 * Force la rotation du log
	 *
	 * @return bool
	 */
	public function force_rotation() {
		return $this->handler->rotate_log_file();
	}

	/**
	 * Purge les anciens fichiers de rotation
	 *
	 * @return int Nombre de fichiers supprimés.
	 */
	public function purge_old_logs() {
		return $this->handler->purge_old_rotations();
	}

	/**
	 * Met à jour la configuration
	 *
	 * @param array $config Nouvelle configuration.
	 */
	public function update_config( array $config ) {
		$this->config = array_merge( $this->config, $config );
		$this->validator->update_config( $this->config );
		$this->handler->update_config( $this->config );
	}

	/**
	 * Récupère la configuration actuelle
	 *
	 * @return array
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Récupère les statistiques du module
	 *
	 * @return array
	 */
	public function get_stats() {
		return array(
			'log_file_exists' => $this->log_file_exists(),
			'log_file_size'   => $this->get_log_file_size(),
			'log_file_path'   => $this->get_log_file_path(),
			'max_log_size'    => $this->config['max_log_size'],
			'purge_keep_days' => $this->config['purge_keep_days'],
		);
	}
}
