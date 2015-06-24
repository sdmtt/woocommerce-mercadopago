<?php
/**
 * Admin View: Notice - Currency not supported.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="error">
	<p><strong><?php _e( 'WooCommerce MercadoPago Disabled', 'cielo-woocommerce' ); ?></strong>: <?php printf( __( 'Currency <code>%s</code> is not supported. Please make sure that you use one of the following supported currencies: %s.', 'cielo-woocommerce' ), get_woocommerce_currency(), '<code>' . implode( '</code>, <code>', $this->api->get_supported_currencies() ) . '</code>' ); ?>
	</p>
</div>
