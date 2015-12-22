<?php
/**
 * Modal payment template.
 *
 * @package WooCommerce_MercadoPago/Templates
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<?php if ( $url ) : ?>

	<p><?php _e( 'Thank you for your order, please click the button below to pay with MercadoPago.', 'woocommerce-mercadopago' ); ?></p>

	<a id="submit-payment" href="<?php echo esc_url( $url ); ?>" name="MP-Checkout" class="button alt" mp-mode="modal"><?php _e( 'Pay via MercadoPago', 'woocommerce-mercadopago' ); ?></a> <a class="button cancel" href="<?php echo esc_url( $order->get_cancel_order_url() ); ?>"><?php _e( 'Cancel order &amp; restore cart', 'woocommerce-mercadopago' ); ?></a>

	<script type="text/javascript" src="<?php echo esc_url( $this->api->get_modal_js_url() ); ?>"></script>

	<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>

<?php else : ?>

	<p><?php _e( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woocommerce-mercadopago' ); ?></p>

	<a class="button cancel" href="<?php echo esc_url( $order->get_cancel_order_url() ); ?>"><?php _e( 'Click to try again', 'woocommerce-mercadopago' ); ?></a>

<?php endif; ?>
