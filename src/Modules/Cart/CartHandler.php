<?php
/**
 * Handler du module Cart
 * Responsabilité : Traitement des événements panier et gestion des notices
 *
 * @package WcCheckoutGuard\Modules\Cart
 */

namespace WcCheckoutGuard\Modules\Cart;

use WcCheckoutGuard\Modules\Logging\LoggingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handler des événements panier
 */
class CartHandler {

	/**
	 * Configuration du handler
	 *
	 * @var array
	 */
	private $config;

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
	 * Constructeur
	 *
	 * @param array          $config    Configuration.
	 * @param CartValidator  $validator Instance du validateur.
	 * @param LoggingManager $logger    Instance du logger.
	 */
	public function __construct( array $config, CartValidator $validator, LoggingManager $logger ) {
		$this->config    = $config;
		$this->validator = $validator;
		$this->logger    = $logger;
	}

	/**
	 * Gère la mise à jour de quantité d'un article
	 *
	 * @param string $cart_item_key Clé de l'article.
	 * @param int    $quantity      Nouvelle quantité.
	 * @param int    $old_quantity  Ancienne quantité.
	 * @param object $cart          Instance du panier.
	 */
	public function handle_cart_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
		$total_qty = $this->get_cart_total_quantity();

		$this->log_cart_event( 'cart_item_quantity_updated', array(
			'cart_item_key' => $cart_item_key,
			'old_quantity'  => $old_quantity,
			'new_quantity'  => $quantity,
			'total_qty'     => $total_qty,
			'product_name'  => $this->get_product_name_from_cart_item( $cart_item_key ),
		) );

		$this->evaluate_cart_and_manage_notices();
	}

	/**
	 * Gère la suppression d'un article du panier
	 *
	 * @param string $cart_item_key Clé de l'article supprimé.
	 * @param object $cart          Instance du panier.
	 */
	public function handle_cart_item_removed( $cart_item_key, $cart ) {
		$total_qty = $this->get_cart_total_quantity();

		$this->log_cart_event( 'cart_item_removed', array(
			'cart_item_key' => $cart_item_key,
			'total_qty'     => $total_qty,
			'product_name'  => $this->get_product_name_from_cart_item( $cart_item_key, $cart ),
		) );

		$this->evaluate_cart_and_manage_notices();
	}

	/**
	 * Gère la restauration d'un article dans le panier
	 *
	 * @param string $cart_item_key Clé de l'article restauré.
	 * @param object $cart          Instance du panier.
	 */
	public function handle_cart_item_restored( $cart_item_key, $cart ) {
		$total_qty = $this->get_cart_total_quantity();

		$this->log_cart_event( 'cart_item_restored', array(
			'cart_item_key' => $cart_item_key,
			'total_qty'     => $total_qty,
			'product_name'  => $this->get_product_name_from_cart_item( $cart_item_key ),
		) );

		$this->evaluate_cart_and_manage_notices();
	}

	/**
	 * Gère la mise à jour générale du panier
	 *
	 * @param object $cart Instance du panier.
	 */
	public function handle_cart_updated( $cart ) {
		$total_qty = $this->get_cart_total_quantity();

		$this->log_cart_event( 'cart_updated', array(
			'total_qty'    => $total_qty,
			'cart_items'   => count( WC()->cart->get_cart() ),
		) );

		$this->evaluate_cart_and_manage_notices();
	}

	/**
	 * Gère la vidange du panier
	 */
	public function handle_cart_emptied() {
		$this->log_cart_event( 'cart_emptied', array(
			'total_qty' => 0,
		) );

		// Nettoyer toutes les notices d'erreur liées à la quantité
		$this->clear_quantity_notices();
	}

	/**
	 * Nettoie les notices si nécessaire au chargement de page
	 */
	public function maybe_cleanup_notices() {
		if ( ! $this->should_cleanup_notices() ) {
			return;
		}

		$this->evaluate_cart_and_manage_notices();
	}

	/**
	 * Vérifie le panier lors de l'affichage de la page panier
	 */
	public function check_cart_on_display() {
		if ( ! $this->validator->is_cart_page() ) {
			return;
		}

		$this->evaluate_cart_and_manage_notices();
	}

	/**
	 * Évalue le panier et gère les notices en conséquence
	 *
	 * @return bool True si le panier est valide, false sinon.
	 */
	public function evaluate_cart_and_manage_notices() {
		$total_qty = $this->get_cart_total_quantity();
		$is_valid  = $this->validator->is_quantity_valid( $total_qty );

		if ( $is_valid ) {
			// Panier valide : nettoyer les notices d'erreur et optionnellement afficher succès
			$this->handle_valid_cart( $total_qty );
		} else {
			// Panier invalide : s'assurer qu'une notice d'erreur est présente
			$this->handle_invalid_cart( $total_qty );
		}

		return $is_valid;
	}

	/**
	 * Gère un panier valide (≤ max_qty)
	 *
	 * @param int $total_qty Quantité totale.
	 */
	private function handle_valid_cart( int $total_qty ) {
		// Nettoyer les notices d'erreur liées à la quantité
		$this->clear_quantity_notices();

		// Optionnellement afficher une notice de succès (seulement sur panier)
		if ( $this->should_show_success_notice() && $total_qty > 0 ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $this->config['success_message'], 'success' );
			}
		}

		$this->log_cart_event( 'cart_notices_cleaned', array(
			'total_qty' => $total_qty,
			'reason'    => 'quantity_valid',
		) );
	}

	/**
	 * Gère un panier invalide (> max_qty)
	 *
	 * @param int $total_qty Quantité totale.
	 */
	private function handle_invalid_cart( int $total_qty ) {
		// Ne pas ajouter de notice d'erreur ici, c'est le rôle du module Checkout
		// Juste logger l'événement
		$this->log_cart_event( 'cart_quantity_invalid', array(
			'total_qty' => $total_qty,
			'max_qty'   => $this->config['max_qty'],
		) );
	}

	/**
	 * Nettoie les notices d'erreur liées à la quantité
	 */
	private function clear_quantity_notices() {
		if ( ! $this->config['enable_notice_cleanup'] || ! function_exists( 'wc_clear_notices' ) ) {
			return;
		}

		// Récupérer toutes les notices
		$notices = wc_get_notices();

		if ( empty( $notices['error'] ) ) {
			return;
		}

		// Filtrer et supprimer les notices liées à la quantité
		$filtered_notices = array();
		$removed_count = 0;

		foreach ( $notices['error'] as $notice ) {
			$notice_text = is_array( $notice ) ? $notice['notice'] : $notice;
			
			// Vérifier si la notice contient des mots-clés liés à notre contrainte
			if ( $this->is_quantity_related_notice( $notice_text ) ) {
				$removed_count++;
				continue; // Ne pas garder cette notice
			}

			$filtered_notices[] = $notice;
		}

		if ( $removed_count > 0 ) {
			// Nettoyer toutes les notices d'erreur et remettre celles qui ne sont pas liées à la quantité
			wc_clear_notices();
			
			// Remettre les notices non liées à la quantité
			foreach ( $filtered_notices as $notice ) {
				$notice_text = is_array( $notice ) ? $notice['notice'] : $notice;
				wc_add_notice( $notice_text, 'error' );
			}

			$this->logger->info( "Notices d'erreur nettoyées", array(
				'removed_count' => $removed_count,
			) );
		}
	}

	/**
	 * Vérifie si une notice est liée à la contrainte de quantité
	 *
	 * @param string $notice_text Texte de la notice.
	 * @return bool
	 */
	private function is_quantity_related_notice( string $notice_text ) {
		$keywords = array(
			'formations',
			'formation à la fois',
			'conformité',
			'supprimer les formations excédentaires',
			'tb_qty_limit',
			'une formation maximum',
		);

		foreach ( $keywords as $keyword ) {
			if ( stripos( $notice_text, $keyword ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Détermine si les notices doivent être nettoyées
	 *
	 * @return bool
	 */
	private function should_cleanup_notices() {
		if ( ! $this->config['enable_notice_cleanup'] ) {
			return false;
		}

		// Nettoyer seulement sur les pages pertinentes
		return $this->validator->is_target_page();
	}

	/**
	 * Détermine si une notice de succès doit être affichée
	 *
	 * @return bool
	 */
	private function should_show_success_notice() {
		// Afficher seulement sur la page panier et si activé
		return $this->validator->is_cart_page() && ! empty( $this->config['success_message'] );
	}

	/**
	 * Log un événement panier
	 *
	 * @param string $event_type Type d'événement.
	 * @param array  $data       Données additionnelles.
	 */
	private function log_cart_event( string $event_type, array $data = array() ) {
		$payload = array_merge( array(
			'event'      => $event_type,
			'time'       => current_time( 'mysql' ),
			'user_id'    => get_current_user_id(),
			'session_id' => ( WC()->session ) ? WC()->session->get_customer_id() : null,
			'page_type'  => $this->get_current_page_type(),
			'referer'    => esc_url_raw( $_SERVER['HTTP_REFERER'] ?? '' ),
		), $data );

		$this->logger->log_json( $payload );
	}

	/**
	 * Récupère la quantité totale du panier
	 *
	 * @return int
	 */
	private function get_cart_total_quantity() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		return (int) WC()->cart->get_cart_contents_count();
	}

	/**
	 * Récupère le nom d'un produit depuis une clé d'article panier
	 *
	 * @param string $cart_item_key Clé de l'article.
	 * @param object $cart          Instance du panier (optionnel).
	 * @return string
	 */
	private function get_product_name_from_cart_item( string $cart_item_key, $cart = null ) {
		if ( ! $cart ) {
			$cart = WC()->cart;
		}

		if ( ! $cart ) {
			return 'Produit inconnu';
		}

		$cart_contents = $cart->get_cart();
		if ( isset( $cart_contents[ $cart_item_key ]['data'] ) ) {
			$product = $cart_contents[ $cart_item_key ]['data'];
			if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
				return $product->get_name();
			}
		}

		return 'Produit';
	}

	/**
	 * Récupère le type de page actuelle
	 *
	 * @return string
	 */
	private function get_current_page_type() {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return 'cart';
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return 'checkout';
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return 'shop';
		}

		return 'other';
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
