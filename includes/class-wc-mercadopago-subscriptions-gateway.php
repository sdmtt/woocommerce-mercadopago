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
	 * Initialize subscription actions.
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'woocommerce_subscription_cancelled_' . $this->id, array( $this, 'cancel_subscription' ) );
	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );
		$url   = $this->api->get_user_payment_url( $order, true );

		include 'views/html-modal-payment.php';
	}

	/**
	 * Process payments.
	 *
	 * @param  int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( wcs_order_contains_subscription( $order_id ) ) {
			$order = wc_get_order( $order_id );
			$valid = $this->validate_subscription_order( $order );
			$url   = '';

			if ( $valid ) {
				// Redirect or modal window integration.
				if ( 'redirect' == $this->method ) {
					$url = $this->api->get_user_payment_url( $order, true );
				} else {
					$url = $order->get_checkout_payment_url( true );
				}
			}

			return array(
				'result'   => '' !== $url ? 'success' : 'fail',
				'redirect' => $url,
			);
		} else {
			return parent::process_payment( $order_id );
		}
	}

	/**
	 * Validate order for subscriptions.
	 * MercadoPago have a few limitations, we can just process one subscription at a time.
	 *
	 * @param  WC_Order $order Order data.
	 *
	 * @return bool
	 */
	public function validate_subscription_order( $order ) {
		$valid = true;

		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item['qty'] ) {
				$product = $order->get_product_from_item( $order_item );
				if ( ! in_array( $product->product_type, array( 'subscription', 'variable-subscription' ) ) ) {
					wc_add_notice( '<strong>' . esc_html( $this->title ) . ': </strong>' . __( 'Only can process one subscription at a time without other products within the card. Please remove any others products before continue.', 'woocommerce-mercadopago' ), 'error' );

					$valid = false;
					break;
				}
			}
		}

		if ( $valid && 0 < WC_Subscriptions_Order::get_sign_up_fee( $order ) ) {
			wc_add_notice( '<strong>' . esc_html( $this->title ) . ': </strong>' . __( 'Unable to process signatures with sign-up fees.', 'woocommerce-mercadopago' ), 'error' );
			$valid = false;
		}

		return $valid;
	}

	/**
	 * Cancel subscription.
	 *
	 * @param WC_Subscription $subscription Subscription data.
	 */
	public function cancel_subscription( $subscription ) {
		if ( $id = $subscription->order->mercadopago_payment_id ) {
			$this->api->cancel_subscription( $id );
		}
	}
}
