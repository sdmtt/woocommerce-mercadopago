<?php
/**
 * Admin View: Notice - Currency not supported.
 *
 * @package WooCommerce_MercadoPago/Admin/Notices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="error">
	<p><strong><?php _e( 'WooCommerce MercadoPago Disabled', 'woocommerce-mercadopago' ); ?></strong>: <?php printf( __( 'Currency %s is not supported. Please make sure that you use one of the following supported currencies: %s.', 'woocommerce-mercadopago' ), '<code>' . get_woocommerce_currency() . '</code>', '<code>' . implode( '</code>, <code>', $this->api->get_supported_currencies() ) . '</code>' ); ?>
	</p>
</div>
