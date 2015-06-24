<?php
/**
 * Modal payment buttons.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<?php if ( $url ) : ?>

	<p><?php _e( 'Thank you for your order, please click the button below to pay with MercadoPago.', 'woocommerce-mercadopago' ); ?></p>
	<a id="submit-payment" href="<?php esc_url( $url ); ?>" name="MP-Checkout" class="button alt" mp-mode="modal"><?php __( 'Pay via MercadoPago', 'woocommerce-mercadopago' ); ?></a> <a class="button cancel" href="<?php esc_url( $order->get_cancel_order_url() ); ?>"><?php __( 'Cancel order &amp; restore cart', 'woocommerce-mercadopago' ); ?></a>
	<script type="text/javascript">
		(function(){function $MPBR_load(){window.$MPBR_loaded !== true && (function(){var s = document.createElement("script");s.type = "text/javascript";s.async = true; s.src = ("https:"==document.location.protocol?"https://www.mercadopago.com/org-img/jsapi/mptools/buttons/":"http://mp-tools.mlstatic.com/buttons/")+"render.js"; var x = document.getElementsByTagName('script')[0];x.parentNode.insertBefore(s, x);window.$MPBR_loaded = true;})();} window.$MPBR_loaded !== true ? (window.attachEvent ? window.attachEvent('onload', $MPBR_load) : window.addEventListener('load', $MPBR_load, false)) : null;})();
	</script>

<?php else : ?>

	<p><?php __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woocommerce-mercadopago' ); ?></p>
	<a class="button cancel" href="<?php esc_url( $order->get_cancel_order_url() ); ?>"><?php __( 'Click to try again', 'woocommerce-mercadopago' ); ?></a>

<?php endif; ?>
