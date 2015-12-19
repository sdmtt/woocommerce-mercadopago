<?php
/**
 * WooCommerce MercadoPago Subscriptions Gateway class
 *
 * @package WooCommerce_MercadoPago/Classes/Gateway
 * @since   3.0.0
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MercadoPago subscription payment method.
 */
class WC_MercadoPago_Subscriptions_Gateway extends WC_MercadoPago_Gateway {

	/**
	 * Initialize Subscriptions actions.
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			// add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			// add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'update_failing_payment_method' ), 10, 2 );
		}
	}

	public function process_payment( $order_id ) {
		// if () {

		// } else {
			return parent::process_payment( $order_id );
		// }
	}
}
