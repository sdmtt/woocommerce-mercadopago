<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Update the main file.
$active_plugins = get_option( 'active_plugins', array() );

foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strstr( $active_plugin, '/wc-mercadopago.php' ) ) {
		$active_plugins[ $key ] = str_replace( '/wc-mercadopago.php', '/woocommerce-mercadopago.php', $active_plugin );
	}
}

update_option( 'active_plugins', $active_plugins );
