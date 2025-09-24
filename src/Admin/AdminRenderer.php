<?php
/**
 * Renderer du module Admin
 * Responsabilité : Rendu des pages et interfaces d'administration
 *
 * @package WcCheckoutGuard\Admin
 */

namespace WcCheckoutGuard\Admin;

use WcCheckoutGuard\Modules\Logging\LoggingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renderer des pages d'administration
 */
class AdminRenderer {

	/**
	 * Configuration du renderer
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
	 * Rend la page principale des logs
	 */
	public function render_logs_page() {
		if ( ! current_user_can( $this->config['capability'] ) ) {
			wp_die( __( 'Vous n\'avez pas les permissions suffisantes pour accéder à cette page.' ) );
		}

		// Traitement des actions
		$this->handle_page_actions();

		// Paramètres d'affichage
		$lines_to_show = $this->get_lines_parameter();
		$auto_refresh  = $this->get_refresh_parameter();

		// Début du rendu
		echo '<div class="wrap wc-checkout-guard-logs">';
		
		$this->render_page_header();
		$this->render_log_stats();
		$this->render_log_controls( $lines_to_show, $auto_refresh );
		$this->render_log_content( $lines_to_show );
		
		echo '</div>';
	}

	/**
	 * Traite les actions de la page
	 */
	private function handle_page_actions() {
		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_GET['action'] );

		switch ( $action ) {
			case 'download':
				$this->handle_download_action();
				break;
				
			case 'rotate':
				$this->handle_rotate_action();
				break;
				
			case 'purge':
				$this->handle_purge_action();
				break;
		}
	}

	/**
	 * Gère l'action de téléchargement
	 */
	private function handle_download_action() {
		$log_file = $this->logger->get_log_file_path();
		
		if ( ! $this->logger->log_file_exists() ) {
			wp_die( __( 'Le fichier de log n\'existe pas.' ) );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . basename( $log_file ) . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );
		
		readfile( $log_file );
		exit;
	}

	/**
	 * Gère l'action de rotation
	 */
	private function handle_rotate_action() {
		if ( $this->logger->force_rotation() ) {
			$this->add_admin_notice( 'Rotation du fichier de log effectuée avec succès.', 'success' );
		} else {
			$this->add_admin_notice( 'Erreur lors de la rotation du fichier de log.', 'error' );
		}
	}

	/**
	 * Gère l'action de purge
	 */
	private function handle_purge_action() {
		$deleted_count = $this->logger->purge_old_logs();
		
		if ( $deleted_count > 0 ) {
			$this->add_admin_notice( 
				sprintf( '%d anciens fichiers de log ont été supprimés.', $deleted_count ), 
				'success' 
			);
		} else {
			$this->add_admin_notice( 'Aucun ancien fichier de log à supprimer.', 'info' );
		}
	}

	/**
	 * Récupère le paramètre de nombre de lignes
	 *
	 * @return int
	 */
	private function get_lines_parameter() {
		$lines = isset( $_GET['lines'] ) ? (int) $_GET['lines'] : $this->config['default_lines'];
		return max( 10, min( $lines, $this->config['max_lines'] ) );
	}

	/**
	 * Récupère le paramètre d'auto-refresh
	 *
	 * @return int
	 */
	private function get_refresh_parameter() {
		$refresh = isset( $_GET['refresh'] ) ? (int) $_GET['refresh'] : 0;
		return max( 0, min( $refresh, 60 ) );
	}

	/**
	 * Rend l'en-tête de la page
	 */
	private function render_page_header() {
		echo '<h1>' . esc_html( $this->config['page_title'] ) . '</h1>';
		echo '<p>Consultation des logs de limitation checkout WooCommerce</p>';
		
		// Meta refresh si auto-refresh activé
		$auto_refresh = $this->get_refresh_parameter();
		if ( $auto_refresh > 0 ) {
			echo '<meta http-equiv="refresh" content="' . esc_attr( $auto_refresh ) . '">';
		}
	}

	/**
	 * Rend les statistiques des logs
	 */
	private function render_log_stats() {
		$stats = $this->logger->get_stats();
		
		echo '<div class="log-stats">';
		
		echo '<div class="stat-box">';
		echo '<div class="stat-value">' . ( $stats['log_file_exists'] ? 'Actif' : 'Inactif' ) . '</div>';
		echo '<div class="stat-label">État des logs</div>';
		echo '</div>';
		
		echo '<div class="stat-box">';
		echo '<div class="stat-value">' . size_format( $stats['log_file_size'] ) . '</div>';
		echo '<div class="stat-label">Taille du fichier</div>';
		echo '</div>';
		
		echo '<div class="stat-box">';
		echo '<div class="stat-value">' . size_format( $stats['max_log_size'] ) . '</div>';
		echo '<div class="stat-label">Taille max</div>';
		echo '</div>';
		
		echo '<div class="stat-box">';
		echo '<div class="stat-value">' . $stats['purge_keep_days'] . ' jours</div>';
		echo '<div class="stat-label">Rétention</div>';
		echo '</div>';
		
		echo '</div>';
	}

	/**
	 * Rend les contrôles des logs
	 *
	 * @param int $lines_to_show Nombre de lignes à afficher.
	 * @param int $auto_refresh  Intervalle d'auto-refresh.
	 */
	private function render_log_controls( $lines_to_show, $auto_refresh ) {
		echo '<div class="log-controls">';
		
		echo '<p><strong>Fichier :</strong> <code>' . esc_html( $this->logger->get_log_file_path() ) . '</code></p>';
		
		// Contrôles de nombre de lignes
		echo '<p><strong>Affichage :</strong> ';
		$line_options = array( 50, 100, 200, 500, 1000 );
		foreach ( $line_options as $option ) {
			$class = ( $lines_to_show === $option ) ? 'current' : '';
			echo '<a href="' . esc_url( add_query_arg( 'lines', $option ) ) . '" class="' . $class . '">' . $option . '</a> ';
		}
		echo '</p>';
		
		// Contrôles d'auto-refresh
		echo '<p><strong>Auto-refresh :</strong> ';
		$refresh_options = array(
			0  => 'Off',
			5  => '5s',
			10 => '10s',
			30 => '30s',
		);
		foreach ( $refresh_options as $seconds => $label ) {
			$class = ( $auto_refresh === $seconds ) ? 'current' : '';
			$url = $seconds > 0 ? add_query_arg( 'refresh', $seconds ) : remove_query_arg( 'refresh' );
			echo '<a href="' . esc_url( $url ) . '" class="' . $class . '">' . $label . '</a> ';
		}
		echo '</p>';
		
		// Actions
		echo '<p><strong>Actions :</strong> ';
		echo '<a href="' . esc_url( add_query_arg( 'action', 'download' ) ) . '" class="button">Télécharger</a> ';
		echo '<a href="' . esc_url( add_query_arg( 'action', 'rotate' ) ) . '" class="button">Forcer rotation</a> ';
		echo '<a href="' . esc_url( add_query_arg( 'action', 'purge' ) ) . '" class="button">Purger anciens</a>';
		echo '</p>';
		
		echo '</div>';
	}

	/**
	 * Rend le contenu des logs
	 *
	 * @param int $lines_to_show Nombre de lignes à afficher.
	 */
	private function render_log_content( $lines_to_show ) {
		if ( ! $this->logger->log_file_exists() ) {
			echo '<div class="notice notice-warning"><p>Le fichier de log n\'existe pas encore. Il sera créé lors du premier événement.</p></div>';
			return;
		}

		$log_content = $this->logger->get_tail_log( $lines_to_show );
		
		echo '<div class="log-viewer">';
		echo esc_html( $log_content );
		echo '</div>';
	}

	/**
	 * Ajoute une notice d'administration
	 *
	 * @param string $message Message à afficher.
	 * @param string $type    Type de notice (success, error, warning, info).
	 */
	private function add_admin_notice( $message, $type = 'info' ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible">';
		echo '<p>' . esc_html( $message ) . '</p>';
		echo '</div>';
	}

	/**
	 * Met à jour la configuration
	 *
	 * @param array $config Nouvelle configuration.
	 */
	public function update_config( array $config ) {
		$this->config = $config;
	}
}
