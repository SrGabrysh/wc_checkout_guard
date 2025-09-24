<?php
/**
 * Handler du module Checkout
 * Responsabilité : Traitement des actions de contrôle checkout
 *
 * @package WcCheckoutGuard\Modules\Checkout
 */

namespace WcCheckoutGuard\Modules\Checkout;

use WcCheckoutGuard\Modules\Logging\LoggingManager;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handler des fonctionnalités checkout
 */
class CheckoutHandler {

	/**
	 * Configuration du handler
	 *
	 * @var array
	 */
	private $config;

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
	 * Flag pour éviter les doublons d'erreur Store API
	 *
	 * @var bool
	 */
	private $error_added = false;

	/**
	 * Constructeur
	 *
	 * @param array              $config    Configuration.
	 * @param CheckoutValidator  $validator Instance du validateur.
	 * @param LoggingManager     $logger    Instance du logger.
	 */
	public function __construct( array $config, CheckoutValidator $validator, LoggingManager $logger ) {
		$this->config    = $config;
		$this->validator = $validator;
		$this->logger    = $logger;
	}

	/**
	 * Log la visite sur la page /commander/
	 */
	public function log_commander_visit() {
		if ( is_admin() ) {
			return;
		}

		if ( ! function_exists( 'is_page' ) || ! is_page( $this->config['checkout_page'] ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$cart = WC()->cart;
		$cart->get_cart();

		$payload = array(
			'event'      => 'visit_commander',
			'time'       => current_time( 'mysql' ),
			'uri'        => $_SERVER['REQUEST_URI'] ?? null,
			'ip_hash'    => $this->hash_ip( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'user_id'    => get_current_user_id(),
			'user_agent' => substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
			'referer'    => esc_url_raw( $_SERVER['HTTP_REFERER'] ?? '' ),
			'total_qty'  => (int) $cart->get_cart_contents_count(),
			'lines'      => $this->get_cart_lines_snapshot( $cart ),
			'session_id' => ( WC()->session ) ? WC()->session->get_customer_id() : null,
		);

		$this->logger->log_json( $payload );
	}

	/**
	 * Redirige le checkout vers le panier si trop d'articles
	 */
	public function redirect_checkout_if_too_many() {
		if ( is_admin() ) {
			return;
		}

		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		// Ne pas perturber REST/AJAX sur checkout
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		$cart      = WC()->cart;
		$total_qty = (int) $cart->get_cart_contents_count();

		if ( ! $this->validator->is_quantity_valid( $total_qty ) ) {
			$cart_url = $this->get_cart_url();
			$message  = $this->get_error_message( $total_qty );

			if ( function_exists( 'wc_add_notice' ) ) {
				$message_with_link = sprintf(
					'%s <a href="%s">Accéder au panier</a>',
					$message,
					esc_url( $cart_url )
				);
				wc_add_notice( $message_with_link, 'error' );
			}

			$this->logger->log_json( array(
				'event'     => 'redirect_checkout_to_cart',
				'reason'    => 'qty_limit',
				'total_qty' => $total_qty,
			) );

			wp_safe_redirect( $cart_url );
			exit;
		}
	}

	/**
	 * Gère les erreurs pour Checkout Blocks (Store API)
	 *
	 * @param WP_Error $errors  Erreurs existantes.
	 * @param mixed    $request Requête.
	 * @return WP_Error
	 */
	public function blocks_cart_errors( $errors, $request = null ) {
		if ( $this->error_added ) {
			return $errors;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $errors;
		}

		$cart      = WC()->cart;
		$total_qty = (int) $cart->get_cart_contents_count();

		if ( ! $this->validator->is_quantity_valid( $total_qty ) && $errors instanceof WP_Error ) {
			$message = $this->get_error_message( $total_qty );
			$errors->add( 'tb_qty_limit', $message, 400 );
			$this->error_added = true;

			$this->logger->log_json( array(
				'event'     => 'block_checkout_blocks',
				'reason'    => 'qty_limit',
				'total_qty' => $total_qty,
			) );
		}

		return $errors;
	}

	/**
	 * Validation pour l'ancien checkout
	 *
	 * @param array    $data   Données du checkout.
	 * @param WP_Error $errors Erreurs.
	 */
	public function legacy_checkout_validation( $data, $errors ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$total_qty = (int) WC()->cart->get_cart_contents_count();

		if ( ! $this->validator->is_quantity_valid( $total_qty ) && $errors instanceof WP_Error ) {
			$message = $this->get_error_message( $total_qty );
			$errors->add( 'tb_qty_limit', $message );

			$this->logger->log_json( array(
				'event'     => 'block_checkout_legacy',
				'reason'    => 'qty_limit',
				'total_qty' => $total_qty,
			) );
		}
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
	 * Récupère l'URL du panier
	 *
	 * @return string
	 */
	private function get_cart_url() {
		return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' . $this->config['cart_url'] );
	}

	/**
	 * Génère le message d'erreur
	 *
	 * @param int $total_qty Quantité totale.
	 * @return string
	 */
	private function get_error_message( int $total_qty ) {
		return sprintf( $this->config['error_message'], $total_qty );
	}

	/**
	 * Crée un snapshot des lignes du panier
	 *
	 * @param object $cart Instance du panier WooCommerce.
	 * @return array
	 */
	private function get_cart_lines_snapshot( $cart ) {
		$lines = array();

		foreach ( $cart->get_cart() as $item ) {
			$qty  = (int) ( $item['quantity'] ?? 0 );
			$prod = isset( $item['data'] ) && is_object( $item['data'] ) ? $item['data'] : null;
			$name = $prod ? $prod->get_name() : 'Produit';

			$lines[] = array(
				'qty'  => $qty,
				'name' => $name,
			);
		}

		return $lines;
	}

	/**
	 * Hash une adresse IP de manière sécurisée
	 *
	 * @param string $ip Adresse IP.
	 * @return string|null
	 */
	private function hash_ip( $ip ) {
		$ip = trim( (string) $ip );
		if ( $ip === '' ) {
			return null;
		}

		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}
}
