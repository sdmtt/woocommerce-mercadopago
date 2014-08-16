<?php
/**
 * Plugin Name: WooCommerce MercadoPago
 * Plugin URI: https://github.com/claudiosmweb/woocommerce-mercadopago
 * Description: MercadoPago gateway for Woocommerce.
 * Author: claudiosanches
 * Author URI: http://claudiosmweb.com/
 * Version: 2.0.0
 * License: GPLv2 or later
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_MercadoPago' ) ) :

/**
 * WooCommerce MercadoPago main class.
 */
class WC_MercadoPago {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '2.0.0';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Checks with WooCommerce is installed.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			// Include the WC_MercadoPago_Gateway class.
			include_once 'includes/class-wc-mercadopago-gateway.php';

			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-mercadopago' );

		load_textdomain( 'woocommerce-mercadopago', trailingslashit( WP_LANG_DIR ) . 'woocommerce-mercadopago/woocommerce-mercadopago-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-mercadopago', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param   array $methods WooCommerce payment methods.
	 *
	 * @return  array          Payment methods with MercadoPago.
	 */
	public function add_gateway( $methods ) {
		$methods[] = 'WC_MercadoPago_Gateway';

		return $methods;
	}

	/**
	 * WooCommerce fallback notice.
	 *
	 * @return  string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce MercadoPago Gateway depends on the last version of %s to work!', 'woocommerce-mercadopago' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">' . __( 'WooCommerce', 'woocommerce-mercadopago' ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Backwards compatibility with version prior to 2.1.
	 *
	 * @return object Returns the main instance of WooCommerce class.
	 */
	public static function woocommerce_instance() {
		if ( function_exists( 'WC' ) ) {
			return WC();
		} else {
			global $woocommerce;
			return $woocommerce;
		}
	}
}

add_action( 'plugins_loaded', array( 'WC_MercadoPago', 'get_instance' ), 0 );

/**
 * Adds support to legacy IPN.
 *
 * @return void
 */
function wcmercadopago_legacy_ipn() {
	if ( isset( $_GET['topic'] ) && ! isset( $_GET['wc-api'] ) ) {
		$woocommerce = WC_MercadoPago::woocommerce_instance();
		$woocommerce->payment_gateways();

		do_action( 'woocommerce_api_wc_mercadopago_gateway' );
	}
}

add_action( 'init', 'wcmercadopago_legacy_ipn' );

endif;
