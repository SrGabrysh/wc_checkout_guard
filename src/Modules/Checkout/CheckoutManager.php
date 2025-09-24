<?php
/**
 * Gestionnaire principal du module Checkout
 * Responsabilité : Orchestration des fonctionnalités de contrôle checkout
 *
 * @package WcCheckoutGuard\Modules\Checkout
 */

namespace WcCheckoutGuard\Modules\Checkout;

use WcCheckoutGuard\Modules\Logging\LoggingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Gestionnaire du module Checkout
 * Orchestre la limitation à 1 produit par commande
 */
class CheckoutManager {

	/**
	 * Instance du handler de checkout
	 *
	 * @var CheckoutHandler
	 */
	private $handler;

	/**
	 * Instance du validateur
	 *
	 * @var CheckoutValidator
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
			'max_qty'        => 1,
			'checkout_page'  => 'commander',
			'cart_url'       => 'panier',
			'error_message'  => 'Erreur : Votre panier contient %d formations. Vous ne pouvez commander qu\'une formation à la fois pour des raisons de suivi et de conformité. Veuillez supprimer les formations excédentaires.',
			'notice_key'     => 'tb_qty_limit', // Clé unique pour identifier nos notices
		);
	}

	/**
	 * Initialise les composants du module
	 */
	private function init_components() {
		$this->validator = new CheckoutValidator( $this->config );
		$this->handler   = new CheckoutHandler( $this->config, $this->validator, $this->logger );
	}

	/**
	 * Initialise les hooks WordPress
	 */
	private function init_hooks() {
		// Hook pour logger les visites sur /commander/
		add_action( 'template_redirect', array( $this->handler, 'log_commander_visit' ), 5 );

		// Hook pour rediriger si trop d'articles
		add_action( 'template_redirect', array( $this->handler, 'redirect_checkout_if_too_many' ), 20 );

		// Hooks pour bloquer la validation checkout
		add_filter( 'woocommerce_store_api_cart_errors', array( $this->handler, 'blocks_cart_errors' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this->handler, 'legacy_checkout_validation' ), 10, 2 );
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
	 * Vérifie si le module est actif
	 *
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'WC' ) && class_exists( 'WooCommerce' );
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
			'max_qty_allowed' => $this->config['max_qty'],
			'current_cart_qty' => $this->get_current_cart_quantity(),
			'is_checkout_blocked' => $this->is_checkout_blocked(),
		);
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
	 * Vérifie si le checkout est bloqué
	 *
	 * @return bool
	 */
	private function is_checkout_blocked() {
		return $this->get_current_cart_quantity() > $this->config['max_qty'];
	}
}
