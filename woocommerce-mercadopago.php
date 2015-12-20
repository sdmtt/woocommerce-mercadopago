<?php
/**
 * Plugin Name: WooCommerce MercadoPago
 * Plugin URI: https://github.com/claudiosmweb/woocommerce-mercadopago
 * Description: MercadoPago gateway for Woocommerce.
 * Author: Claudio Sanches
 * Author URI: https://claudiosmweb.com/
 * Version: 3.0.0-beta-1
 * License: GPLv2 or later
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /languages/
 *
 * @package WooCommerce_MercadoPago
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
		const VERSION = '3.0.0-beta-1';

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
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				$this->includes();

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
				add_filter( 'woocommerce_cancel_unpaid_order', array( $this, 'stop_cancel_unpaid_orders' ), 10, 2 );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
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
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'woocommerce-mercadopago', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Includes.
		 */
		private function includes() {
			include_once 'includes/class-wc-mercadopago-api.php';
			include_once 'includes/class-wc-mercadopago-gateway.php';

			if ( function_exists( 'wcs_is_subscription' ) ) {
				include_once 'includes/class-wc-mercadopago-subscriptions-gateway.php';
			}
		}

		/**
		 * Add the gateway to WooCommerce.
		 *
		 * @param  array $methods WooCommerce payment methods.
		 *
		 * @return array          Payment methods with MercadoPago.
		 */
		public function add_gateway( $methods ) {
			if ( function_exists( 'wcs_is_subscription' ) ) {
				$methods[] = 'WC_MercadoPago_Subscriptions_Gateway';
			} else {
				$methods[] = 'WC_MercadoPago_Gateway';
			}

			return $methods;
		}

		/**
		 * Stop cancel unpaid MercadoPago orders.
		 *
		 * @param  bool     $cancel If should cancel the order.
		 * @param  WC_Order $order  Order data.
		 *
		 * @return bool
		 */
		public function stop_cancel_unpaid_orders( $cancel, $order ) {
			if ( 'mercadopago' === $order->payment_method ) {
				return false;
			}

			return $cancel;
		}

		/**
		 * Action links.
		 *
		 * @param  array $links Plugin action links.
		 *
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			$plugin_links = array();

			if ( function_exists( 'wcs_is_subscription' ) ) {
				$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_mercadopago_subscriptions_gateway' ) ) . '">' . esc_html__( 'Settings', 'woocommerce-mercadopago' ) . '</a>';
			} else {
				$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_mercadopago_gateway' ) ) . '">' . esc_html__( 'Settings', 'woocommerce-mercadopago' ) . '</a>';
			}

			return array_merge( $plugin_links, $links );
		}

		/**
		 * WooCommerce fallback notice.
		 */
		public function woocommerce_missing_notice() {
			include 'includes/admin/views/html-admin-missing-dependencies.php';
		}
	}

	add_action( 'plugins_loaded', array( 'WC_MercadoPago', 'get_instance' ) );

endif;
