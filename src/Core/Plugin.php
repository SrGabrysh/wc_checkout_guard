<?php
/**
 * Classe principale du plugin WooCommerce checkout guard
 * Responsabilité : Orchestration des modules et initialisation
 *
 * @package WcCheckoutGuard\Core
 */

namespace WcCheckoutGuard\Core;

use WcCheckoutGuard\Modules\Checkout\CheckoutManager;
use WcCheckoutGuard\Modules\Cart\CartManager;
use WcCheckoutGuard\Modules\Logging\LoggingManager;
use WcCheckoutGuard\Admin\AdminManager;

defined( 'ABSPATH' ) || exit;

/**
 * Classe principale du plugin
 * Orchestre l'initialisation et la coordination des modules
 */
class Plugin {

	/**
	 * Instance unique (Singleton)
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Version du plugin
	 */
	const VERSION = '1.1.0';

	/**
	 * Modules du plugin
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Instance du logger global
	 *
	 * @var LoggingManager
	 */
	private $logger;

	/**
	 * Configuration globale du plugin
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructeur privé (Singleton)
	 */
	private function __construct() {
		$this->config = $this->get_default_config();
		$this->init();
	}

	/**
	 * Récupère l'instance unique
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Configuration par défaut du plugin
	 *
	 * @return array
	 */
	private function get_default_config() {
		return array(
			'enable_checkout_guard' => true,
			'enable_cart_events'    => true,
			'enable_logging'        => true,
			'enable_admin'          => true,
			'debug_mode'            => false,
		);
	}

	/**
	 * Initialisation du plugin
	 */
	private function init() {
		// Vérification des prérequis
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Hooks WordPress de base
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );

		// Initialisation des modules core
		$this->init_core_modules();

		// Initialisation des modules fonctionnels
		$this->init_functional_modules();

		// Initialisation du module admin (si nécessaire)
		$this->init_admin_module();
	}

	/**
	 * Vérification des prérequis
	 *
	 * @return bool
	 */
	private function check_requirements() {
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
	 * Initialise les modules core (logging, etc.)
	 */
	private function init_core_modules() {
		// Logger (toujours en premier)
		if ( $this->is_module_enabled( 'logging' ) ) {
			$this->logger = new LoggingManager();
			$this->modules['logging'] = $this->logger;
		}
	}

	/**
	 * Initialise les modules fonctionnels
	 */
	private function init_functional_modules() {
		// Module checkout guard
		if ( $this->is_module_enabled( 'checkout_guard' ) && $this->is_woocommerce_active() ) {
			$this->modules['checkout'] = new CheckoutManager( $this->logger );
		}

		// Module cart events (gestion des événements panier)
		if ( $this->is_module_enabled( 'cart_events' ) && $this->is_woocommerce_active() ) {
			$this->modules['cart'] = new CartManager( $this->logger );
		}
	}

	/**
	 * Initialise le module admin
	 */
	private function init_admin_module() {
		if ( $this->is_module_enabled( 'admin' ) && is_admin() ) {
			$this->modules['admin'] = new AdminManager( $this->logger );
		}
	}

	/**
	 * Hook init de WordPress
	 */
	public function on_init() {
		// Chargement des traductions
		load_plugin_textdomain(
			'wc_checkout_guard',
			false,
			dirname( plugin_basename( WC_CHECKOUT_GUARD_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Hook plugins_loaded de WordPress
	 */
	public function on_plugins_loaded() {
		// Vérification finale de WooCommerce après chargement des plugins
		if ( ! $this->is_woocommerce_active() ) {
			if ( $this->is_module_enabled( 'checkout_guard' ) ) {
				$this->logger->warning( 'WooCommerce non détecté - Module checkout désactivé' );
			}
			if ( $this->is_module_enabled( 'cart_events' ) ) {
				$this->logger->warning( 'WooCommerce non détecté - Module cart events désactivé' );
			}
		}
	}

	/**
	 * Vérifie si un module est activé
	 *
	 * @param string $module_name Nom du module.
	 * @return bool
	 */
	private function is_module_enabled( string $module_name ) {
		$key = 'enable_' . $module_name;
		return isset( $this->config[ $key ] ) && $this->config[ $key ];
	}

	/**
	 * Vérifie si WooCommerce est actif
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return function_exists( 'WC' ) && class_exists( 'WooCommerce' );
	}

	/**
	 * Récupère un module spécifique
	 *
	 * @param string $module_name Nom du module.
	 * @return mixed|null
	 */
	public function get_module( string $module_name ) {
		return isset( $this->modules[ $module_name ] ) ? $this->modules[ $module_name ] : null;
	}

	/**
	 * Récupère tous les modules
	 *
	 * @return array
	 */
	public function get_modules() {
		return $this->modules;
	}

	/**
	 * Récupère le logger global
	 *
	 * @return LoggingManager|null
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Met à jour la configuration
	 *
	 * @param array $new_config Nouvelle configuration.
	 */
	public function update_config( array $new_config ) {
		$this->config = array_merge( $this->config, $new_config );
	}

	/**
	 * Récupère la configuration
	 *
	 * @return array
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Récupère la version du plugin
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}

	/**
	 * Récupère les informations du plugin
	 *
	 * @return array
	 */
	public function get_plugin_info() {
		return array(
			'version'        => self::VERSION,
			'modules_count'  => count( $this->modules ),
			'active_modules' => array_keys( $this->modules ),
			'wc_active'      => $this->is_woocommerce_active(),
			'config'         => $this->config,
		);
	}
}
