<?php
/**
 * Handler du module Logging
 * Responsabilité : Traitement des opérations de logging
 *
 * @package WcCheckoutGuard\Modules\Logging
 */

namespace WcCheckoutGuard\Modules\Logging;

use DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Handler des opérations de logging
 */
class LoggingHandler {

	/**
	 * Configuration du handler
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Instance du validateur
	 *
	 * @var LoggingValidator
	 */
	private $validator;

	/**
	 * Chemin complet du fichier de log
	 *
	 * @var string
	 */
	private $log_file_path;

	/**
	 * Constructeur
	 *
	 * @param array            $config    Configuration.
	 * @param LoggingValidator $validator Instance du validateur.
	 */
	public function __construct( array $config, LoggingValidator $validator ) {
		$this->config    = $config;
		$this->validator = $validator;
		$this->init_log_path();
	}

	/**
	 * Initialise le chemin du fichier de log
	 */
	private function init_log_path() {
		$log_dir = $this->config['log_base_path'] . $this->config['log_dir_name'];
		$this->log_file_path = $log_dir . '/' . $this->config['log_filename'];
	}

	/**
	 * Sécurise le répertoire des logs
	 */
	public function ensure_log_dir_secure() {
		$log_dir = dirname( $this->log_file_path );

		// Créer le répertoire si nécessaire
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Créer le fichier .htaccess pour bloquer l'accès web
		$htaccess_file = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "Deny from all\n";
			$this->write_file( $htaccess_file, $htaccess_content );
		}

		// Créer le fichier index.php pour plus de sécurité
		$index_file = $log_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			$index_content = "<?php // Silence is golden.\n";
			$this->write_file( $index_file, $index_content );
		}
	}

	/**
	 * Écrit une entrée de log
	 *
	 * @param array $data Données à logger.
	 * @return bool
	 */
	public function write_log_entry( array $data ) {
		$this->ensure_log_dir_secure();

		// Préparer les données avec timestamp
		$log_entry = array(
			'time' => current_time( 'mysql' ),
			'data' => $this->sanitize_log_data( $data ),
		);

		// Encoder en JSON
		$json_line = json_encode( $log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . PHP_EOL;

		// Vérifier si le répertoire est accessible en écriture
		$log_dir = dirname( $this->log_file_path );
		if ( ! is_dir( $log_dir ) || ! is_writable( $log_dir ) ) {
			$this->fallback_log( $json_line );
			return false;
		}

		// Rotation si le fichier est trop volumineux
		if ( $this->should_rotate_log() ) {
			$this->rotate_log_file();
		}

		// Écrire dans le fichier
		$result = $this->write_file( $this->log_file_path, $json_line, FILE_APPEND | LOCK_EX );

		if ( false === $result ) {
			$this->fallback_log( $json_line );
			return false;
		}

		return true;
	}

	/**
	 * Vérifie si le log doit être tourné
	 *
	 * @return bool
	 */
	private function should_rotate_log() {
		return file_exists( $this->log_file_path ) && filesize( $this->log_file_path ) > $this->config['max_log_size'];
	}

	/**
	 * Effectue la rotation du fichier de log
	 *
	 * @return bool
	 */
	public function rotate_log_file() {
		if ( ! file_exists( $this->log_file_path ) ) {
			return false;
		}

		$rotation_name = $this->log_file_path . '.' . date( 'Ymd_His' );
		$result = rename( $this->log_file_path, $rotation_name );

		if ( $result ) {
			$this->purge_old_rotations();
		}

		return $result;
	}

	/**
	 * Purge les anciens fichiers de rotation
	 *
	 * @return int Nombre de fichiers supprimés.
	 */
	public function purge_old_rotations() {
		$log_dir = dirname( $this->log_file_path );
		$basename = basename( $this->log_file_path );
		$deleted_count = 0;

		$files = @scandir( $log_dir );
		if ( ! $files ) {
			return 0;
		}

		foreach ( $files as $file ) {
			if ( strpos( $file, $basename . '.' ) !== 0 ) {
				continue;
			}

			$timestamp_part = substr( $file, -15 );
			$datetime = DateTime::createFromFormat( 'Ymd_His', $timestamp_part );

			if ( $datetime && ( time() - $datetime->getTimestamp() ) > ( $this->config['purge_keep_days'] * DAY_IN_SECONDS ) ) {
				if ( @unlink( $log_dir . '/' . $file ) ) {
					$deleted_count++;
				}
			}
		}

		return $deleted_count;
	}

	/**
	 * Lit les dernières lignes d'un fichier (tail)
	 *
	 * @param string $filepath Chemin du fichier.
	 * @param int    $lines    Nombre de lignes.
	 * @param int    $buffer   Taille du buffer.
	 * @return string
	 */
	public function tail_file( string $filepath, int $lines = 200, int $buffer = 4096 ) {
		$file_handle = @fopen( $filepath, 'rb' );
		if ( ! $file_handle ) {
			return 'Impossible d\'ouvrir le fichier.';
		}

		fseek( $file_handle, 0, SEEK_END );
		$position = ftell( $file_handle );
		$data = '';
		$line_count = 0;

		while ( $position > 0 && $line_count <= $lines ) {
			$read_size = ( $position - $buffer ) >= 0 ? $buffer : $position;
			$position -= $read_size;
			fseek( $file_handle, $position, SEEK_SET );
			$chunk = fread( $file_handle, $read_size );
			$data = $chunk . $data;
			$line_count = substr_count( $data, "\n" );
		}

		fclose( $file_handle );

		$lines_array = explode( "\n", rtrim( $data, "\n" ) );
		$tail_lines = array_slice( $lines_array, -$lines );

		return implode( "\n", $tail_lines );
	}

	/**
	 * Sanitise les données de log
	 *
	 * @param array $data Données à sanitiser.
	 * @return array
	 */
	private function sanitize_log_data( array $data ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			if ( $key === 'ip' || $key === 'ip_hash' ) {
				$sanitized[ $key ] = (string) $value;
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = array_map( function( $item ) {
					return is_scalar( $item ) ? sanitize_text_field( (string) $item ) : $item;
				}, $value );
			} else {
				$sanitized[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Log de fallback via le système WooCommerce
	 *
	 * @param string $message Message à logger.
	 */
	private function fallback_log( string $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $message, array( 'source' => 'wc_checkout_guard' ) );
		}
	}

	/**
	 * Écrit dans un fichier de manière sécurisée
	 *
	 * @param string $filepath Chemin du fichier.
	 * @param string $content  Contenu à écrire.
	 * @param int    $flags    Flags pour file_put_contents.
	 * @return int|false
	 */
	private function write_file( string $filepath, string $content, int $flags = 0 ) {
		return @file_put_contents( $filepath, $content, $flags );
	}

	/**
	 * Récupère le chemin du fichier de log
	 *
	 * @return string
	 */
	public function get_log_file_path() {
		return $this->log_file_path;
	}

	/**
	 * Vérifie si le fichier de log existe
	 *
	 * @return bool
	 */
	public function log_file_exists() {
		return file_exists( $this->log_file_path );
	}

	/**
	 * Récupère la taille du fichier de log
	 *
	 * @return int
	 */
	public function get_log_file_size() {
		return $this->log_file_exists() ? filesize( $this->log_file_path ) : 0;
	}

	/**
	 * Met à jour la configuration
	 *
	 * @param array $config Nouvelle configuration.
	 */
	public function update_config( array $config ) {
		$this->config = $config;
		$this->init_log_path();
	}
}
