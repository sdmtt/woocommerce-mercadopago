<?php
/**
 * Plugin Name: WooCommerce MercadoPago
 * Plugin URI: https://github.com/claudiosmweb/woocommerce-mercadopago
 * Description: MercadoPago gateway for Woocommerce.
 * Author: claudiosanches
 * Author URI: http://claudiosmweb.com/
 * Version: 1.9.0
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
	 * @since 1.9.0
	 *
	 * @var   string
	 */
	const VERSION = '1.9.0';

	/**
	 * Integration id.
	 *
	 * @since 1.9.0
	 *
	 * @var   string
	 */
	protected static $gateway_id = 'mercadopago';

	/**
	 * Plugin slug.
	 *
	 * @since 1.9.0
	 *
	 * @var   string
	 */
	protected static $plugin_slug = 'woocommerce-mercadopago';

	/**
	 * Instance of this class.
	 *
	 * @since 1.9.0
	 *
	 * @var   object
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
	 * @since  1.9.0
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
	 * Return the plugin slug.
	 *
	 * @since  1.9.0
	 *
	 * @return string Plugin slug variable.
	 */
	public static function get_plugin_slug() {
		return self::$plugin_slug;
	}

	/**
	 * Return the gateway id/slug.
	 *
	 * @since  1.9.0
	 *
	 * @return string Gateway id/slug variable.
	 */
	public static function get_gateway_id() {
		return self::$gateway_id;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since  1.9.0
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$domain = self::$plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @version 1.9.0
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
	 * @version 1.9.0
	 *
	 * @return  string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce MercadoPago Gateway depends on the last version of %s to work!', self::$plugin_slug ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">' . __( 'WooCommerce', self::$plugin_slug ) . '</a>' ) . '</p></div>';
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
