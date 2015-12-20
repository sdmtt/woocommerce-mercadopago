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
	 * Process payments.
	 *
	 * @param  int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( wcs_order_contains_subscription( $order_id ) ) {
			$order = wc_get_order( $order_id );
			$valid = $this->validate_order( $order );
			$url   = '';

			if ( $valid ) {
				$url = $this->api->get_user_payment_url( $order, true );
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
	public function validate_order( $order ) {
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
}
