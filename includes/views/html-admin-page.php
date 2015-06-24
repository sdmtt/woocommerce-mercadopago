<?php
/**
 * Admin options screen.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<h3><?php echo $this->method_title; ?></h3>

<?php
	if ( 'yes' == $this->get_option( 'enabled' ) ) {
		if ( ! in_array( get_woocommerce_currency(), $this->api->get_supported_currencies() ) && ! class_exists( 'woocommerce_wpml' ) ) {
			include 'notices/html-notice-currency-not-supported.php';
		}
	}
?>

<?php echo wpautop( $this->method_description ); ?>

<?php if ( apply_filters( 'woocommerce_mercadopago_help_message', true ) ) : ?>
	<div class="updated woocommerce-message">
		<p><?php printf( __( 'Help us keep the %s plugin free making a %s or rate %s on %s. Thank you in advance!', 'woocommerce-mercadopago' ), '<strong>' . __( 'WooCommerce MercadoPago', 'woocommerce-mercadopago' ) . '</strong>', '<a href="http://claudiosmweb.com/doacoes/">' . __( 'donation', 'woocommerce-mercadopago' ) . '</a>', '<a href="https://wordpress.org/support/view/plugin-reviews/woocommerce-mercadopago?filter=5#postform" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>', '<a href="https://wordpress.org/support/view/plugin-reviews/woocommerce-mercadopago?filter=5#postform" target="_blank">' . __( 'WordPress.org', 'woocommerce-mercadopago' ) . '</a>' ); ?></p>
	</div>
<?php endif; ?>

<table class="form-table">
	<?php $this->generate_settings_html(); ?>
</table>
