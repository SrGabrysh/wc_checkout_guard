<?php
/**
 * Validateur du module Checkout
 * Responsabilité : Validation des règles de limitation checkout
 *
 * @package WcCheckoutGuard\Modules\Checkout
 */

namespace WcCheckoutGuard\Modules\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Validateur des règles checkout
 */
class CheckoutValidator {

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
	 * Valide si la quantité respecte la limite
	 *
	 * @param int $quantity Quantité à valider.
	 * @return bool
	 */
	public function is_quantity_valid( int $quantity ) {
		return $quantity <= $this->config['max_qty'];
	}

	/**
	 * Valide si WooCommerce est disponible
	 *
	 * @return bool
	 */
	public function is_woocommerce_available() {
		return function_exists( 'WC' ) && class_exists( 'WooCommerce' );
	}

	/**
	 * Valide si le panier existe et est accessible
	 *
	 * @return bool
	 */
	public function is_cart_available() {
		return $this->is_woocommerce_available() && WC()->cart !== null;
	}

	/**
	 * Valide si la page courante est la page checkout configurée
	 *
	 * @return bool
	 */
	public function is_checkout_page() {
		if ( ! function_exists( 'is_page' ) ) {
			return false;
		}

		return is_page( $this->config['checkout_page'] );
	}

	/**
	 * Valide si la page courante est la page checkout WooCommerce
	 *
	 * @return bool
	 */
	public function is_wc_checkout_page() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return false;
		}

		return is_checkout();
	}

	/**
	 * Valide si la requête est une requête REST ou AJAX
	 *
	 * @return bool
	 */
	public function is_rest_or_ajax_request() {
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$is_ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();

		return $is_rest || $is_ajax;
	}

	/**
	 * Valide si l'environnement est admin
	 *
	 * @return bool
	 */
	public function is_admin_context() {
		return is_admin();
	}

	/**
	 * Valide la configuration du module
	 *
	 * @return bool
	 */
	public function is_config_valid() {
		$required_keys = array( 'max_qty', 'checkout_page', 'cart_url', 'error_message' );

		foreach ( $required_keys as $key ) {
			if ( ! isset( $this->config[ $key ] ) ) {
				return false;
			}
		}

		// Valider que max_qty est un entier positif
		if ( ! is_int( $this->config['max_qty'] ) || $this->config['max_qty'] < 1 ) {
			return false;
		}

		// Valider que les strings ne sont pas vides
		$string_keys = array( 'checkout_page', 'cart_url', 'error_message' );
		foreach ( $string_keys as $key ) {
			if ( empty( $this->config[ $key ] ) || ! is_string( $this->config[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Valide les données de log avant enregistrement
	 *
	 * @param array $log_data Données à valider.
	 * @return bool
	 */
	public function is_log_data_valid( array $log_data ) {
		// Vérifier que les clés essentielles sont présentes
		$required_keys = array( 'event', 'time' );

		foreach ( $required_keys as $key ) {
			if ( ! isset( $log_data[ $key ] ) || empty( $log_data[ $key ] ) ) {
				return false;
			}
		}

		// Valider le format de l'event
		if ( ! is_string( $log_data['event'] ) || strlen( $log_data['event'] ) > 100 ) {
			return false;
		}

		// Valider le timestamp
		if ( ! is_string( $log_data['time'] ) ) {
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

	/**
	 * Récupère la limite de quantité configurée
	 *
	 * @return int
	 */
	public function get_max_quantity() {
		return $this->config['max_qty'];
	}
}
