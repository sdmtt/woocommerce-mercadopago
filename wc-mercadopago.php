<?php
/**
 * Plugin Name: WooCommerce MercadoPago
 * Plugin URI: https://github.com/claudiosmweb/woocommerce-mercadopago
 * Description: MercadoPago gateway for Woocommerce.
 * Author: claudiosanches
 * Author URI: http://claudiosmweb.com/
 * Version: 1.8.0
 * License: GPLv2 or later
 * Text Domain: woocommerce-mercadopago
 * Domain Path: /languages/
 */

/**
 * WooCommerce fallback notice.
 */
function wcmercadopago_woocommerce_fallback_notice() {
	echo '<div class="error"><p>' . sprintf( __( 'WooCommerce MercadoPago Gateway depends on the last version of %s to work!', 'woocommerce-mercadopago' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
}

/**
 * Load functions.
 */
function wcmercadopago_gateway_load() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', 'wcmercadopago_woocommerce_fallback_notice' );

		return;
	}

	/**
	 * Load textdomain.
	 */
	load_plugin_textdomain( 'woocommerce-mercadopago', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param array $methods Default methods.
	 *
	 * @return array         Methods with MercadoPago gateway.
	 */
	function wcmercadopago_add_gateway( $methods ) {
		$methods[] = 'WC_MercadoPago_Gateway';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'wcmercadopago_add_gateway' );

	// Include the WC_MercadoPago_Gateway class.
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-mercadopago-gateway.php';

}

add_action( 'plugins_loaded', 'wcmercadopago_gateway_load', 0 );

/**
 * Adds support to legacy IPN.
 *
 * @return void
 */
function wcmercadopago_legacy_ipn() {
	if ( isset( $_GET['topic'] ) && ! isset( $_GET['wc-api'] ) ) {
		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
			WC()->payment_gateways();
		} else {
			global $woocommerce;
			$woocommerce->payment_gateways();
		}

		do_action( 'woocommerce_api_wc_mercadopago_gateway' );
	}
}

add_action( 'init', 'wcmercadopago_legacy_ipn' );
