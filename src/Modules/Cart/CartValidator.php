<?php
/**
 * Validateur du module Cart
 * Responsabilité : Validation des règles et contextes panier
 *
 * @package WcCheckoutGuard\Modules\Cart
 */

namespace WcCheckoutGuard\Modules\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Validateur des règles panier
 */
class CartValidator {

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
	 * Vérifie si WooCommerce est disponible
	 *
	 * @return bool
	 */
	public function is_woocommerce_available() {
		return function_exists( 'WC' ) && class_exists( 'WooCommerce' );
	}

	/**
	 * Vérifie si le panier existe et est accessible
	 *
	 * @return bool
	 */
	public function is_cart_available() {
		return $this->is_woocommerce_available() && WC()->cart !== null;
	}

	/**
	 * Vérifie si la page courante est la page panier
	 *
	 * @return bool
	 */
	public function is_cart_page() {
		if ( ! function_exists( 'is_cart' ) ) {
			return false;
		}

		return is_cart();
	}

	/**
	 * Vérifie si la page courante est la page checkout
	 *
	 * @return bool
	 */
	public function is_checkout_page() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return false;
		}

		return is_checkout();
	}

	/**
	 * Vérifie si la page courante est une page cible pour les actions
	 *
	 * @return bool
	 */
	public function is_target_page() {
		$target_pages = $this->config['target_pages'] ?? array();

		foreach ( $target_pages as $page ) {
			switch ( $page ) {
				case 'cart':
					if ( $this->is_cart_page() ) {
						return true;
					}
					break;
				case 'checkout':
					if ( $this->is_checkout_page() ) {
						return true;
					}
					break;
			}
		}

		return false;
	}

	/**
	 * Vérifie si la requête est une requête AJAX
	 *
	 * @return bool
	 */
	public function is_ajax_request() {
		return function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();
	}

	/**
	 * Vérifie si la requête est une requête REST
	 *
	 * @return bool
	 */
	public function is_rest_request() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Vérifie si l'environnement est admin
	 *
	 * @return bool
	 */
	public function is_admin_context() {
		return is_admin();
	}

	/**
	 * Vérifie si un événement doit être surveillé
	 *
	 * @param string $event_name Nom de l'événement.
	 * @return bool
	 */
	public function should_monitor_event( string $event_name ) {
		return in_array( $event_name, $this->config['monitored_events'] ?? array(), true );
	}

	/**
	 * Valide les données d'un événement panier
	 *
	 * @param array $event_data Données de l'événement.
	 * @return bool
	 */
	public function is_cart_event_valid( array $event_data ) {
		// Vérifier que les clés essentielles sont présentes
		$required_keys = array( 'event', 'total_qty' );

		foreach ( $required_keys as $key ) {
			if ( ! isset( $event_data[ $key ] ) ) {
				return false;
			}
		}

		// Valider le type d'événement
		if ( ! is_string( $event_data['event'] ) || empty( trim( $event_data['event'] ) ) ) {
			return false;
		}

		// Valider la quantité totale
		if ( ! is_int( $event_data['total_qty'] ) || $event_data['total_qty'] < 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Valide une clé d'article panier
	 *
	 * @param string $cart_item_key Clé à valider.
	 * @return bool
	 */
	public function is_valid_cart_item_key( string $cart_item_key ) {
		// Une clé d'article panier doit être une string non vide
		if ( empty( trim( $cart_item_key ) ) ) {
			return false;
		}

		// Vérifier la longueur (les clés WooCommerce sont généralement des hash de 32 caractères)
		if ( strlen( $cart_item_key ) > 100 ) {
			return false;
		}

		// Vérifier les caractères (alphanumériques principalement)
		return preg_match( '/^[a-zA-Z0-9_-]+$/', $cart_item_key );
	}

	/**
	 * Valide une quantité d'article
	 *
	 * @param mixed $quantity Quantité à valider.
	 * @return bool
	 */
	public function is_valid_item_quantity( $quantity ) {
		// Doit être un entier positif ou zéro
		return is_int( $quantity ) && $quantity >= 0;
	}

	/**
	 * Valide la configuration du module
	 *
	 * @return bool
	 */
	public function is_config_valid() {
		$required_keys = array( 'max_qty', 'enable_notice_cleanup', 'target_pages', 'monitored_events' );

		foreach ( $required_keys as $key ) {
			if ( ! isset( $this->config[ $key ] ) ) {
				return false;
			}
		}

		// Valider max_qty
		if ( ! is_int( $this->config['max_qty'] ) || $this->config['max_qty'] < 1 ) {
			return false;
		}

		// Valider enable_notice_cleanup
		if ( ! is_bool( $this->config['enable_notice_cleanup'] ) ) {
			return false;
		}

		// Valider target_pages
		if ( ! is_array( $this->config['target_pages'] ) ) {
			return false;
		}

		// Valider monitored_events
		if ( ! is_array( $this->config['monitored_events'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Vérifie si le nettoyage des notices est activé
	 *
	 * @return bool
	 */
	public function is_notice_cleanup_enabled() {
		return ! empty( $this->config['enable_notice_cleanup'] );
	}

	/**
	 * Vérifie si un message de succès doit être affiché
	 *
	 * @return bool
	 */
	public function should_show_success_message() {
		return ! empty( $this->config['success_message'] ) && is_string( $this->config['success_message'] );
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

	/**
	 * Récupère les pages cibles configurées
	 *
	 * @return array
	 */
	public function get_target_pages() {
		return $this->config['target_pages'] ?? array();
	}

	/**
	 * Récupère les événements surveillés
	 *
	 * @return array
	 */
	public function get_monitored_events() {
		return $this->config['monitored_events'] ?? array();
	}
}
