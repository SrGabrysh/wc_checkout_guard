<?php
/**
 * Validateur du module Logging
 * Responsabilité : Validation des données et règles de logging
 *
 * @package WcCheckoutGuard\Modules\Logging
 */

namespace WcCheckoutGuard\Modules\Logging;

defined( 'ABSPATH' ) || exit;

/**
 * Validateur des règles de logging
 */
class LoggingValidator {

	/**
	 * Configuration du validateur
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructeur
	 *
	 * @param array $config Configuration.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Valide les données de log
	 *
	 * @param array $log_data Données à valider.
	 * @return bool
	 */
	public function is_log_data_valid( array $log_data ) {
		// Vérifier que l'array n'est pas vide
		if ( empty( $log_data ) ) {
			return false;
		}

		// Si c'est un log d'événement, vérifier l'event
		if ( isset( $log_data['event'] ) ) {
			return $this->validate_event_log( $log_data );
		}

		// Si c'est un log de message, vérifier le message
		if ( isset( $log_data['message'] ) ) {
			return $this->validate_message_log( $log_data );
		}

		// Accepter les autres types de logs avec validation basique
		return $this->validate_basic_log( $log_data );
	}

	/**
	 * Valide un log d'événement
	 *
	 * @param array $log_data Données du log.
	 * @return bool
	 */
	private function validate_event_log( array $log_data ) {
		// Vérifier l'event
		if ( ! is_string( $log_data['event'] ) || empty( trim( $log_data['event'] ) ) ) {
			return false;
		}

		// Vérifier la longueur de l'event
		if ( strlen( $log_data['event'] ) > 100 ) {
			return false;
		}

		// Valider les events connus
		$valid_events = array(
			'visit_commander',
			'redirect_checkout_to_cart',
			'block_checkout_blocks',
			'block_checkout_legacy',
			'log_message',
		);

		return in_array( $log_data['event'], $valid_events, true );
	}

	/**
	 * Valide un log de message
	 *
	 * @param array $log_data Données du log.
	 * @return bool
	 */
	private function validate_message_log( array $log_data ) {
		// Vérifier le message
		if ( ! is_string( $log_data['message'] ) || empty( trim( $log_data['message'] ) ) ) {
			return false;
		}

		// Vérifier la longueur du message
		if ( strlen( $log_data['message'] ) > 1000 ) {
			return false;
		}

		// Vérifier le niveau si présent
		if ( isset( $log_data['level'] ) ) {
			$valid_levels = array( 'info', 'warning', 'error', 'debug' );
			if ( ! in_array( $log_data['level'], $valid_levels, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validation basique pour autres types de logs
	 *
	 * @param array $log_data Données du log.
	 * @return bool
	 */
	private function validate_basic_log( array $log_data ) {
		// Vérifier qu'il n'y a pas de données dangereuses
		foreach ( $log_data as $key => $value ) {
			if ( ! $this->is_safe_log_key( $key ) ) {
				return false;
			}

			if ( ! $this->is_safe_log_value( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Vérifie si une clé de log est sûre
	 *
	 * @param string $key Clé à vérifier.
	 * @return bool
	 */
	private function is_safe_log_key( $key ) {
		// Vérifier que c'est une string
		if ( ! is_string( $key ) ) {
			return false;
		}

		// Vérifier la longueur
		if ( strlen( $key ) > 50 ) {
			return false;
		}

		// Vérifier les caractères (alphanumériques + underscore)
		return preg_match( '/^[a-zA-Z0-9_]+$/', $key );
	}

	/**
	 * Vérifie si une valeur de log est sûre
	 *
	 * @param mixed $value Valeur à vérifier.
	 * @return bool
	 */
	private function is_safe_log_value( $value ) {
		// Null et bool sont OK
		if ( is_null( $value ) || is_bool( $value ) ) {
			return true;
		}

		// Nombres sont OK
		if ( is_int( $value ) || is_float( $value ) ) {
			return true;
		}

		// Strings avec limite de taille
		if ( is_string( $value ) ) {
			return strlen( $value ) <= 2000;
		}

		// Arrays avec validation récursive
		if ( is_array( $value ) ) {
			return $this->validate_log_array( $value );
		}

		// Objets non autorisés
		return false;
	}

	/**
	 * Valide un array de log
	 *
	 * @param array $array Array à valider.
	 * @return bool
	 */
	private function validate_log_array( array $array ) {
		// Limite du nombre d'éléments
		if ( count( $array ) > 50 ) {
			return false;
		}

		// Validation récursive
		foreach ( $array as $key => $value ) {
			if ( ! $this->is_safe_log_key( (string) $key ) ) {
				return false;
			}

			if ( ! $this->is_safe_log_value( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Valide la configuration du logging
	 *
	 * @return bool
	 */
	public function is_config_valid() {
		$required_keys = array(
			'log_dir_name',
			'log_filename',
			'max_log_size',
			'purge_keep_days',
			'log_base_path',
		);

		foreach ( $required_keys as $key ) {
			if ( ! isset( $this->config[ $key ] ) ) {
				return false;
			}
		}

		// Valider les valeurs spécifiques
		if ( ! is_string( $this->config['log_dir_name'] ) || empty( $this->config['log_dir_name'] ) ) {
			return false;
		}

		if ( ! is_string( $this->config['log_filename'] ) || empty( $this->config['log_filename'] ) ) {
			return false;
		}

		if ( ! is_int( $this->config['max_log_size'] ) || $this->config['max_log_size'] < 1024 ) {
			return false;
		}

		if ( ! is_int( $this->config['purge_keep_days'] ) || $this->config['purge_keep_days'] < 1 ) {
			return false;
		}

		if ( ! is_string( $this->config['log_base_path'] ) || empty( $this->config['log_base_path'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Valide un chemin de fichier
	 *
	 * @param string $filepath Chemin à valider.
	 * @return bool
	 */
	public function is_valid_filepath( string $filepath ) {
		// Vérifier que le chemin n'est pas vide
		if ( empty( trim( $filepath ) ) ) {
			return false;
		}

		// Vérifier qu'il n'y a pas de caractères dangereux
		if ( strpos( $filepath, '..' ) !== false ) {
			return false;
		}

		// Vérifier la longueur
		if ( strlen( $filepath ) > 500 ) {
			return false;
		}

		return true;
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
