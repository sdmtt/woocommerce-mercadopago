<?php
/**
 * Admin help message.
 *
 * @package WooCommerce_MercadoPago/Admin/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( apply_filters( 'woocommerce_mercadopago_help_message', true ) ) : ?>
	<div class="updated woocommerce-message">
		<p><?php echo esc_html( sprintf( __( 'Help us keep the %s plugin free making a donation or rate %s on WordPress.org. Thank you in advance!', 'woocommerce-mercadopago' ), __( 'WooCommerce MercadoPago', 'woocommerce-mercadopago' ), '&#9733;&#9733;&#9733;&#9733;&#9733;' ) ); ?></p>
		<p><a href="http://claudiosmweb.com/doacoes/" target="_blank" class="button button-primary"><?php esc_html_e( 'Make a donation', 'woocommerce-mercadopago' ); ?></a> <a href="https://wordpress.org/support/view/plugin-reviews/woocommerce-mercadopago?filter=5#postform" target="_blank" class="button button-secondary"><?php esc_html_e( 'Make a review', 'woocommerce-mercadopago' ); ?></a></p>
	</div>
<?php endif;
