<?php
/**
 * Gestionnaire principal du module Cart
 * Responsabilité : Orchestration des fonctionnalités de gestion panier
 *
 * @package WcCheckoutGuard\Modules\Cart
 */

namespace WcCheckoutGuard\Modules\Cart;

use WcCheckoutGuard\Modules\Logging\LoggingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Gestionnaire du module Cart
 * Orchestre la gestion des événements panier et nettoyage des notices
 */
class CartManager {

	/**
	 * Instance du handler de cart
	 *
	 * @var CartHandler
	 */
	private $handler;

	/**
	 * Instance du validateur
	 *
	 * @var CartValidator
	 */
	private $validator;

	/**
	 * Instance du logger
	 *
	 * @var LoggingManager
	 */
	private $logger;

	/**
	 * Configuration du module
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
			'max_qty'              => 1,
			'enable_notice_cleanup' => true,
			'success_message'      => 'Panier mis à jour, vous pouvez passer au paiement.',
			'target_pages'         => array( 'cart', 'checkout' ),
			'monitored_events'     => array(
				'cart_item_quantity_updated',
				'cart_item_removed',
				'cart_item_restored',
				'cart_updated',
				'cart_emptied',
			),
		);
	}

	/**
	 * Initialise les composants du module
	 */
	private function init_components() {
		$this->validator = new CartValidator( $this->config );
		$this->handler   = new CartHandler( $this->config, $this->validator, $this->logger );
	}

	/**
	 * Initialise les hooks WordPress/WooCommerce
	 */
	private function init_hooks() {
		// Hooks pour surveiller les événements panier
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this->handler, 'handle_cart_quantity_update' ), 10, 4 );
		add_action( 'woocommerce_cart_item_removed', array( $this->handler, 'handle_cart_item_removed' ), 10, 2 );
		add_action( 'woocommerce_cart_item_restored', array( $this->handler, 'handle_cart_item_restored' ), 10, 2 );
		add_action( 'woocommerce_update_cart_action_cart_updated', array( $this->handler, 'handle_cart_updated' ), 10, 1 );
		add_action( 'woocommerce_cart_emptied', array( $this->handler, 'handle_cart_emptied' ), 10 );

		// Hook pour nettoyer les notices au chargement des pages pertinentes
		add_action( 'template_redirect', array( $this->handler, 'maybe_cleanup_notices' ), 15 );

		// Hook pour vérifier et afficher les notices sur le panier
		add_action( 'woocommerce_before_cart', array( $this->handler, 'check_cart_on_display' ), 10 );
	}

	/**
	 * Force la vérification du panier et le nettoyage des notices
	 */
	public function force_cart_check() {
		return $this->handler->evaluate_cart_and_manage_notices();
	}

	/**
	 * Récupère les statistiques du module
	 *
	 * @return array
	 */
	public function get_stats() {
		if ( ! $this->is_active() ) {
			return array();
		}

		return array(
			'current_cart_qty'     => $this->get_current_cart_quantity(),
			'is_cart_valid'        => $this->is_cart_quantity_valid(),
			'notices_cleanup_enabled' => $this->config['enable_notice_cleanup'],
			'monitored_events'     => count( $this->config['monitored_events'] ),
		);
	}

	/**
	 * Vérifie si le module est actif
	 *
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'WC' ) && class_exists( 'WooCommerce' );
	}

	/**
	 * Récupère la quantité actuelle du panier
	 *
	 * @return int
	 */
	private function get_current_cart_quantity() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		return (int) WC()->cart->get_cart_contents_count();
	}

	/**
	 * Vérifie si la quantité du panier est valide
	 *
	 * @return bool
	 */
	private function is_cart_quantity_valid() {
		return $this->validator->is_quantity_valid( $this->get_current_cart_quantity() );
	}

	/**
	 * Met à jour la configuration
	 *
	 * @param array $new_config Nouvelle configuration.
	 */
	public function update_config( array $new_config ) {
		$this->config = array_merge( $this->config, $new_config );
		
		// Mettre à jour les composants avec la nouvelle config
		$this->validator->update_config( $this->config );
		$this->handler->update_config( $this->config );
	}

	/**
	 * Récupère la configuration du module
	 *
	 * @return array
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Active ou désactive le nettoyage automatique des notices
	 *
	 * @param bool $enable État souhaité.
	 */
	public function set_notice_cleanup( bool $enable ) {
		$this->config['enable_notice_cleanup'] = $enable;
		$this->handler->update_config( $this->config );
	}

	/**
	 * Récupère les événements panier surveillés
	 *
	 * @return array
	 */
	public function get_monitored_events() {
		return $this->config['monitored_events'];
	}

	/**
	 * Ajoute un événement à surveiller
	 *
	 * @param string $event_name Nom de l'événement.
	 */
	public function add_monitored_event( string $event_name ) {
		if ( ! in_array( $event_name, $this->config['monitored_events'], true ) ) {
			$this->config['monitored_events'][] = $event_name;
			$this->handler->update_config( $this->config );
		}
	}

	/**
	 * Supprime un événement surveillé
	 *
	 * @param string $event_name Nom de l'événement.
	 */
	public function remove_monitored_event( string $event_name ) {
		$key = array_search( $event_name, $this->config['monitored_events'], true );
		if ( false !== $key ) {
			unset( $this->config['monitored_events'][ $key ] );
			$this->config['monitored_events'] = array_values( $this->config['monitored_events'] );
			$this->handler->update_config( $this->config );
		}
	}
}
