<?php
/**
 * Gestionnaire principal du module Admin
 * Responsabilité : Orchestration des fonctionnalités d'administration
 *
 * @package WcCheckoutGuard\Admin
 */

namespace WcCheckoutGuard\Admin;

use WcCheckoutGuard\Modules\Logging\LoggingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Gestionnaire du module Admin
 */
class AdminManager {

	/**
	 * Instance du renderer
	 *
	 * @var AdminRenderer
	 */
	private $renderer;

	/**
	 * Instance du handler AJAX
	 *
	 * @var AdminAjaxHandler
	 */
	private $ajax_handler;

	/**
	 * Instance du logger
	 *
	 * @var LoggingManager
	 */
	private $logger;

	/**
	 * Configuration du module admin
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructeur
	 *
	 * @param LoggingManager $logger Instance du logger.
	 */
	public function __construct( LoggingManager $logger ) {
		$this->logger = $logger;
		$this->config = $this->get_default_config();
		
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Configuration par défaut
	 *
	 * @return array
	 */
	private function get_default_config() {
		return array(
			'menu_parent'    => 'woocommerce',
			'menu_slug'      => 'wc-checkout-guard-logs',
			'page_title'     => 'WC Checkout Guard - Logs',
			'menu_title'     => 'Checkout Guard',
			'capability'     => 'manage_options',
			'default_lines'  => 200,
			'max_lines'      => 2000,
		);
	}

	/**
	 * Initialise les composants du module
	 */
	private function init_components() {
		$this->renderer     = new AdminRenderer( $this->config, $this->logger );
		$this->ajax_handler = new AdminAjaxHandler( $this->config, $this->logger );
	}

	/**
	 * Initialise les hooks WordPress
	 */
	private function init_hooks() {
		// Menu d'administration
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		
		// Assets d'administration
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// Hooks AJAX
		$this->ajax_handler->init_ajax_hooks();
	}

	/**
	 * Enregistre le menu d'administration
	 */
	public function register_admin_menu() {
		add_submenu_page(
			$this->config['menu_parent'],
			$this->config['page_title'],
			$this->config['menu_title'],
			$this->config['capability'],
			$this->config['menu_slug'],
			array( $this->renderer, 'render_logs_page' )
		);
	}

	/**
	 * Charge les assets d'administration
	 *
	 * @param string $hook_suffix Hook suffix de la page.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Charger uniquement sur notre page
		$page_hook = 'woocommerce_page_' . $this->config['menu_slug'];
		
		if ( $hook_suffix !== $page_hook ) {
			return;
		}

		// CSS personnalisé pour les logs
		$this->enqueue_admin_styles();
		
		// JavaScript pour l'auto-refresh et interactions
		$this->enqueue_admin_scripts();
	}

	/**
	 * Charge les styles d'administration
	 */
	private function enqueue_admin_styles() {
		$css_content = '
			.wc-checkout-guard-logs {
				background: #f9f9f9;
				padding: 20px;
			}
			.wc-checkout-guard-logs .log-viewer {
				background: #1e1e1e;
				color: #ddd;
				padding: 15px;
				border-radius: 8px;
				font-family: "Courier New", monospace;
				font-size: 12px;
				line-height: 1.4;
				max-height: 70vh;
				overflow: auto;
				white-space: pre-wrap;
				word-wrap: break-word;
			}
			.wc-checkout-guard-logs .log-controls {
				margin: 15px 0;
				padding: 15px;
				background: white;
				border: 1px solid #ddd;
				border-radius: 4px;
			}
			.wc-checkout-guard-logs .log-controls a {
				margin-right: 10px;
				text-decoration: none;
			}
			.wc-checkout-guard-logs .log-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 15px;
				margin: 15px 0;
			}
			.wc-checkout-guard-logs .stat-box {
				background: white;
				padding: 15px;
				border: 1px solid #ddd;
				border-radius: 4px;
				text-align: center;
			}
			.wc-checkout-guard-logs .stat-value {
				font-size: 24px;
				font-weight: bold;
				color: #0073aa;
			}
		';

		wp_add_inline_style( 'wp-admin', $css_content );
	}

	/**
	 * Charge les scripts d'administration
	 */
	private function enqueue_admin_scripts() {
		$js_content = '
			jQuery(document).ready(function($) {
				// Auto-refresh si activé
				var refreshInterval = ' . (int) ( $_GET['refresh'] ?? 0 ) . ';
				if (refreshInterval > 0) {
					setTimeout(function() {
						location.reload();
					}, refreshInterval * 1000);
				}
				
				// Scroll automatique vers le bas des logs
				var logViewer = $(".log-viewer");
				if (logViewer.length) {
					logViewer.scrollTop(logViewer[0].scrollHeight);
				}
				
				// Confirmation pour les actions destructives
				$("a[href*=\"action=purge\"]").click(function(e) {
					if (!confirm("Êtes-vous sûr de vouloir purger les anciens logs ?")) {
						e.preventDefault();
					}
				});
			});
		';

		wp_add_inline_script( 'jquery', $js_content );
	}

	/**
	 * Vérifie si l'utilisateur a les permissions requises
	 *
	 * @return bool
	 */
	public function user_can_access() {
		return current_user_can( $this->config['capability'] );
	}

	/**
	 * Récupère les statistiques pour l'admin
	 *
	 * @return array
	 */
	public function get_admin_stats() {
		$log_stats = $this->logger->get_stats();
		
		return array_merge( $log_stats, array(
			'config' => $this->config,
			'user_can_access' => $this->user_can_access(),
		) );
	}

	/**
	 * Met à jour la configuration
	 *
	 * @param array $config Nouvelle configuration.
	 */
	public function update_config( array $config ) {
		$this->config = array_merge( $this->config, $config );
		$this->renderer->update_config( $this->config );
		$this->ajax_handler->update_config( $this->config );
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
	 * Vérifie si le module admin est actif
	 *
	 * @return bool
	 */
	public function is_active() {
		return is_admin() && $this->user_can_access();
	}
}
