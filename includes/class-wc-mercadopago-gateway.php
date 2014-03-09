<?php
/**
 * WC MercadoPago Gateway Class.
 *
 * Built the MercadoPago method.
 */
class WC_MercadoPago_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 *
	 * @return void
	 */
	public function __construct() {

		// Standards.
		$this->id              = WC_MercadoPago::get_gateway_id();
		$this->plugin_slug     = WC_MercadoPago::get_plugin_slug();
		$this->icon            = apply_filters( 'woocommerce_mercadopago_icon', plugins_url( 'images/mercadopago.png', plugin_dir_path( __FILE__ ) ) );
		$this->has_fields      = false;
		$this->method_title    = __( 'MercadoPago', $this->plugin_slug );

		// API URLs.
		$this->payment_url     = 'https://api.mercadolibre.com/checkout/preferences?access_token=';
		$this->ipn_url         = 'https://api.mercadolibre.com/collections/notifications/';
		$this->sandbox_ipn_url = 'https://api.mercadolibre.com/sandbox/collections/notifications/';
		$this->oauth_token     = 'https://api.mercadolibre.com/oauth/token';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->client_id      = $this->get_option( 'client_id' );
		$this->client_secret  = $this->get_option( 'client_secret' );
		$this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->method         = $this->get_option( 'method', 'modal' );
		$this->sandbox        = $this->get_option( 'sandbox', false );
		$this->debug          = $this->get_option( 'debug' );

		// Actions.
		add_action( 'woocommerce_api_wc_mercadopago_gateway', array( $this, 'check_ipn_response' ) );
		add_action( 'valid_mercadopago_ipn_request', array( $this, 'successful_request' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'wp_head', array( $this, 'css' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Checks if client_id is not empty.
		if ( empty( $this->client_id ) ) {
			add_action( 'admin_notices', array( $this, 'client_id_missing_message' ) );
		}

		// Checks if client_secret is not empty.
		if ( empty( $this->client_secret ) ) {
			add_action( 'admin_notices', array( $this, 'client_secret_missing_message' ) );
		}

		// Checks that the currency is supported
		if ( ! $this->using_supported_currency() ) {
			add_action( 'admin_notices', array( $this, 'currency_not_supported_message' ) );
		}

		// Active logs.
		if ( 'yes' == $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = WC_MercadoPago::woocommerce_instance()->logger();
			}
		}
	}

	/**
	 * Fix cURL to works with MercadoPago.
	 *
	 * @param  $handle cURL handle.
	 *
	 * @return void
	 */
	public function fix_curl_to_mercadopago( $handle ) {
		curl_setopt( $handle, CURLOPT_SSLVERSION, 3 );
	}

	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	protected function using_supported_currency() {
		return in_array( get_woocommerce_currency(), array( 'ARS', 'BRL', 'MXN', 'USD', 'VEF' ) );
	}

	/**
	 * Returns a value indicating the the Gateway is available or not. It's called
	 * automatically by WooCommerce before allowing customers to use the gateway
	 * for payment.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Test if is valid for use.
		$available = ( 'yes' == $this->settings['enabled'] ) &&
					! empty( $this->client_id ) &&
					! empty( $this->client_secret ) &&
					$this->using_supported_currency();

		return $available;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {

		$api_secret_locale = sprintf( '<a href="https://www.mercadopago.com/mla/herramientas/aplicaciones" target="_blank">%1$s</a>, <a href="https://www.mercadopago.com/mlb/ferramentas/aplicacoes" target="_blank">%2$s</a>, <a href="https://www.mercadopago.com/mlm/herramientas/aplicaciones" target="_blank">%3$s</a> %5$s <a href="https://www.mercadopago.com/mlv/herramientas/aplicaciones" target="_blank">%4$s</a>', __( 'Argentine', $this->plugin_slug ), __( 'Brazil', $this->plugin_slug ), __( 'Mexico', $this->plugin_slug ), __( 'Venezuela', $this->plugin_slug ), __( 'or', $this->plugin_slug ) );

		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', $this->plugin_slug ),
				'type' => 'checkbox',
				'label' => __( 'Enable MercadoPago standard', $this->plugin_slug ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', $this->plugin_slug ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', $this->plugin_slug ),
				'desc_tip' => true,
				'default' => __( 'MercadoPago', $this->plugin_slug )
			),
			'description' => array(
				'title' => __( 'Description', $this->plugin_slug ),
				'type' => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', $this->plugin_slug ),
				'default' => __( 'Pay via MercadoPago', $this->plugin_slug )
			),
			'client_id' => array(
				'title' => __( 'MercadoPago Client_id', $this->plugin_slug ),
				'type' => 'text',
				'description' => __( 'Please enter your MercadoPago Client_id.', $this->plugin_slug ) . ' ' . sprintf( __( 'You can to get this information in MercadoPago from %s.', $this->plugin_slug ), $api_secret_locale ),
				'default' => ''
			),
			'client_secret' => array(
				'title' => __( 'MercadoPago Client_secret', $this->plugin_slug ),
				'type' => 'text',
				'description' => __( 'Please enter your MercadoPago Client_secret.', $this->plugin_slug ) . ' ' . sprintf( __( 'You can to get this information in MercadoPago from %s.', $this->plugin_slug ), $api_secret_locale ),
				'default' => ''
			),
			'invoice_prefix' => array(
				'title' => __( 'Invoice Prefix', $this->plugin_slug ),
				'type' => 'text',
				'description' => __( 'Please enter a prefix for your invoice numbers. If you use your MercadoPago account for multiple stores ensure this prefix is unqiue as MercadoPago will not allow orders with the same invoice number.', $this->plugin_slug ),
				'desc_tip' => true,
				'default' => 'WC-'
			),
			'method' => array(
				'title' => __( 'Integration method', $this->plugin_slug ),
				'type' => 'select',
				'description' => __( 'Choose how the customer will interact with the MercadoPago. Modal Window (Inside your store) Redirect (Client goes to MercadoPago).', $this->plugin_slug ),
				'desc_tip' => true,
				'default' => 'modal',
				'options' => array(
					'modal' => __( 'Modal Window', $this->plugin_slug ),
					'redirect' => __( 'Redirect', $this->plugin_slug ),
				)
			),
			'testing' => array(
				'title' => __( 'Gateway Testing', $this->plugin_slug ),
				'type' => 'title',
				'description' => '',
			),
			'sandbox' => array(
				'title' => __( 'MercadoPago Sandbox', $this->plugin_slug ),
				'type' => 'checkbox',
				'label' => __( 'Enable MercadoPago sandbox', $this->plugin_slug ),
				'default' => 'no',
				'description' => __( 'MercadoPago sandbox can be used to test payments.', $this->plugin_slug ),
			),
			'debug' => array(
				'title' => __( 'Debug Log', $this->plugin_slug ),
				'type' => 'checkbox',
				'label' => __( 'Enable logging', $this->plugin_slug ),
				'default' => 'no',
				'description' => sprintf( __( 'Log MercadoPago events, such as API requests, inside %s', $this->plugin_slug ), '<code>woocommerce/logs/' . $this->id . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.txt</code>' ),
			)
		);
	}

	/**
	 * Generate the payment arguments.
	 *
	 * @param  object $order Order data.
	 *
	 * @return array         Payment arguments.
	 */
	public function get_payment_args( $order ) {

		$args = array(
			'back_urls' => array(
				'success' => esc_url( $this->get_return_url( $order ) ),
				'failure' => esc_url( $order->get_cancel_order_url() ),
				'pending' => esc_url( $this->get_return_url( $order ) )
			),
			'payer' => array(
				'name'    => $order->billing_first_name,
				'surname' => $order->billing_last_name,
				'email'   => $order->billing_email
			),
			'external_reference' => $this->invoice_prefix . $order->id,
			'items' => array(
				array(
					'quantity'    => 1,
					'unit_price'  => (float) $order->order_total,
					'currency_id' => get_woocommerce_currency(),
					// 'picture_url' => 'https://www.mercadopago.com/org-img/MP3/home/logomp3.gif'
				)
			)
		);

		// Cart Contents.
		$item_names = array();

		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item['qty'] ) {
					$item_names[] = $item['name'] . ' x ' . $item['qty'];
				}
			}
		}

		$args['items'][0]['title'] = sprintf( __( 'Order %s', $this->plugin_slug ), $order->get_order_number() ) . ' - ' . implode( ', ', $item_names );

		// Shipping Cost item.
		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
			$shipping_total = $order->get_total_shipping();
		} else {
			$shipping_total = $order->get_shipping();
		}

		if ( $shipping_total > 0 ) {
			$args['items'][0]['title'] .= ', ' . __( 'Shipping via', $this->plugin_slug ) . ' ' . ucwords( $order->shipping_method_title );
		}

		$args = apply_filters( 'woocommerce_mercadopago_args', $args, $order );

		return $args;
	}

	/**
	 * Generate the MercadoPago payment url.
	 *
	 * @param  object $order Order Object.
	 *
	 * @return string        MercadoPago payment url.
	 */
	protected function get_mercadopago_url( $order ) {

		$args = json_encode( $this->get_payment_args( $order ) );

		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, 'Payment arguments for order ' . $order->get_order_number() . ': ' . print_r( $this->get_payment_args( $order ), true ) );
		}

		$url = $this->payment_url . $this->get_client_credentials();

		$params = array(
			'body'          => $args,
			'sslverify'     => false,
			'timeout'       => 60,
			'headers'       => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json;charset=UTF-8'
			)
		);

		add_action( 'http_api_curl', array( $this, 'fix_curl_to_mercadopago' ) );
		$response = wp_remote_post( $url, $params );
		remove_action( 'http_api_curl', array( $this, 'fix_curl_to_mercadopago' ) );

		if ( ! is_wp_error( $response ) && $response['response']['code'] == 201 && ( strcmp( $response['response']['message'], 'Created' ) == 0 ) ) {
			$checkout_info = json_decode( $response['body'] );

			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, 'Payment link generated with success from MercadoPago' );
			}

			if ( 'yes' == $this->sandbox ) {
				return esc_url( $checkout_info->sandbox_init_point );
			} else {
				return esc_url( $checkout_info->init_point );
			}

		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, 'Generate payment error response: ' . print_r( $response, true ) );
			}
		}

		return false;
	}

	/**
	 * Generate the form.
	 *
	 * @param int     $order_id Order ID.
	 *
	 * @return string           Payment form.
	 */
	public function generate_form( $order_id ) {

		$order = new WC_Order( $order_id );
		$url = $this->get_mercadopago_url( $order );

		if ( $url ) {

			// Display checkout.
			$html = '<p>' . __( 'Thank you for your order, please click the button below to pay with MercadoPago.', $this->plugin_slug ) . '</p>';

			$html .= '<a id="submit-payment" href="' . $url . '" name="MP-Checkout" class="button alt" mp-mode="modal">' . __( 'Pay via MercadoPago', $this->plugin_slug ) . '</a> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', $this->plugin_slug ) . '</a>';

			// Add MercadoPago JS.
			$html .= '<script type="text/javascript">(function(){function $MPBR_load(){window.$MPBR_loaded !== true && (function(){var s = document.createElement("script");s.type = "text/javascript";s.async = true;s.src = ("https:"==document.location.protocol?"https://www.mercadopago.com/org-img/jsapi/mptools/buttons/":"http://mp-tools.mlstatic.com/buttons/")+"render.js";var x = document.getElementsByTagName("script")[0];x.parentNode.insertBefore(s, x);window.$MPBR_loaded = true;})();}window.$MPBR_loaded !== true ? (window.attachEvent ? window.attachEvent("onload", $MPBR_load) : window.addEventListener("load", $MPBR_load, false)) : null;})();</script>';

			return $html;
		} else {
			// Display message if a problem occurs.
			$html = '<p>' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', $this->plugin_slug ) . '</p>';

			$html .= '<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Click to try again', $this->plugin_slug ) . '</a>';

			return $html;
		}
	}

	/**
	 * Fix MercadoPago CSS.
	 *
	 * @return string Styles.
	 */
	public function css() {
		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
			$page_id = wc_get_page_id( 'checkout' );
		} else {
			$page_id = woocommerce_get_page_id( 'checkout' );
		}

		if ( is_page( $page_id ) ) {
			echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>' . PHP_EOL;
		}
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int    $order_id Order ID.
	 *
	 * @return array           Redirect.
	 */
	public function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		// Redirect or modal window integration.
		if ( 'redirect' == $this->method ) {
			return array(
				'result'    => 'success',
				'redirect'  => $this->get_mercadopago_url( $order )
			);
		} else {
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
				return array(
					'result'   => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				);
			} else {
				return array(
					'result'   => 'success',
					'redirect' => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
				);
			}
		}
	}

	/**
	 * Output for the order received page.
	 *
	 * @return void
	 */
	public function receipt_page( $order ) {
		echo $this->generate_form( $order );
	}

	/**
	 * Get cliente token.
	 *
	 * @return mixed Sucesse return the token and error return null.
	 */
	protected function get_client_credentials() {
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, 'Getting client credentials...' );
		}

		// Set postdata.
		$postdata = 'grant_type=client_credentials';
		$postdata .= '&client_id=' . $this->client_id;
		$postdata .= '&client_secret=' . $this->client_secret;

		// Built wp_remote_post params.
		$params = array(
			'body'          => $postdata,
			'sslverify'     => false,
			'timeout'       => 60,
			'headers'       => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded'
			)
		);

		add_action( 'http_api_curl', array( $this, 'fix_curl_to_mercadopago' ) );
		$response = wp_remote_post( $this->oauth_token, $params );
		remove_action( 'http_api_curl', array( $this, 'fix_curl_to_mercadopago' ) );

		// Check to see if the request was valid and return the token.
		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && ( strcmp( $response['response']['message'], 'OK' ) == 0 ) ) {

			$token = json_decode( $response['body'] );

			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, 'Received valid response from MercadoPago' );
			}

			return $token->access_token;
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, 'Received invalid response from MercadoPago. Error response: ' . print_r( $response, true ) );
			}
		}

		return null;
	}

	/**
	 * Check IPN.
	 *
	 * @param  array $data MercadoPago post data.
	 *
	 * @return mixed       False or posted response.
	 */
	public function check_ipn_request_is_valid( $data ) {

		if ( ! isset( $data['id'] ) ) {
			return false;
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, 'Checking IPN request...' );
		}

		if ( 'yes' == $this->sandbox ) {
			$ipn_url = $this->sandbox_ipn_url;
		} else {
			$ipn_url = $this->ipn_url;
		}

		$url = $ipn_url . $data['id'] . '?access_token=' . $this->get_client_credentials();

		// Send back post vars.
		$params = array(
			'sslverify' => false,
			'timeout'   => 60,
			'headers'   => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json;charset=UTF-8'
			)
		);

		// GET a response.
		add_action( 'http_api_curl', array( $this, 'fix_curl_to_mercadopago' ) );
		$response = wp_remote_get( $url, $params );
		remove_action( 'http_api_curl', array( $this, 'fix_curl_to_mercadopago' ) );

		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, 'IPN Response: ' . print_r( $response, true ) );
		}

		// Check to see if the request was valid.
		if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] ) {

			$body = json_decode( $response['body'] );

			$this->log->add( $this->id, 'Received valid IPN response from MercadoPago' );

			return $body;
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, 'Received invalid IPN response from MercadoPago.' );
			}
		}

		return false;
	}

	/**
	 * Check API Response.
	 *
	 * @return void
	 */
	public function check_ipn_response() {
		@ob_clean();

		$data = $this->check_ipn_request_is_valid( $_GET );

		if ( $data ) {
			header( 'HTTP/1.1 200 OK' );
			do_action( 'valid_mercadopago_ipn_request', $data );
		} else {
			wp_die( __( 'MercadoPago Request Failure', $this->plugin_slug ) );
		}
	}

	/**
	 * Successful Payment!
	 *
	 * @param array $posted MercadoPago post data.
	 *
	 * @return void
	 */
	public function successful_request( $posted ) {

		$data = $posted->collection;
		$order_key = $data->external_reference;

		if ( ! empty( $order_key ) ) {
			$order_id = (int) str_replace( $this->invoice_prefix, '', $order_key );

			$order = new WC_Order( $order_id );

			// Checks whether the invoice number matches the order.
			// If true processes the payment.
			if ( $order->id === $order_id ) {

				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, 'Payment status from order ' . $order->get_order_number() . ': ' . $data->status );
				}

				switch ( $data->status ) {
					case 'approved':

						// Order details.
						if ( ! empty( $data->id ) ) {
							update_post_meta(
								$order_id,
								__( 'MercadoPago Transaction ID', $this->plugin_slug ),
								$data->id
							);
						}
						if ( ! empty( $data->payer->email ) ) {
							update_post_meta(
								$order_id,
								__( 'Payer email', $this->plugin_slug ),
								$data->payer->email
							);
						}
						if ( ! empty( $data->payment_type ) ) {
							update_post_meta(
								$order_id,
								__( 'Payment type', $this->plugin_slug ),
								$data->payment_type
							);
						}

						// Payment completed.
						$order->add_order_note( __( 'MercadoPago: Payment approved.', $this->plugin_slug ) );
						$order->payment_complete();

						break;
					case 'pending':
						$order->add_order_note( __( 'MercadoPago: The user has not completed the payment process yet.', $this->plugin_slug ) );

						break;
					case 'in_process':
						$order->update_status( 'on-hold', __( 'MercadoPago: Payment under review.', $this->plugin_slug ) );

						break;
					case 'rejected':
						$order->add_order_note( __( 'MercadoPago: The payment was declined. The user can try again.', $this->plugin_slug ) );

						break;
					case 'refunded':
						$order->update_status( 'refunded', __( 'MercadoPago: The payment was returned to the user.', $this->plugin_slug ) );

						break;
					case 'cancelled':
						$order->update_status( 'cancelled', __( 'MercadoPago: Payment canceled.', $this->plugin_slug ) );

						break;
					case 'in_mediation':
						$order->add_order_note( __( 'MercadoPago: It started a dispute for payment.', $this->plugin_slug ) );

						break;

					default:
						// No action xD.
						break;
				}
			}
		}
	}

	/**
	 * Gets the admin url.
	 *
	 * @return string
	 */
	protected function admin_url() {
		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_mercadopago_gateway' );
		}

		return admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_MercadoPago_Gateway' );
	}

	/**
	 * Adds error message when not configured the client_id.
	 *
	 * @return string Error Mensage.
	 */
	public function client_id_missing_message() {
		echo '<div class="error"><p><strong>' . __( 'MercadoPago Disabled', $this->plugin_slug ) . '</strong>: ' . sprintf( __( 'You should inform your Client_id. %s', $this->plugin_slug ), '<a href="' . $this->admin_url() . '">' . __( 'Click here to configure!', $this->plugin_slug ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Adds error message when not configured the client_secret.
	 *
	 * @return string Error Mensage.
	 */
	public function client_secret_missing_message() {
		echo '<div class="error"><p><strong>' . __( 'MercadoPago Disabled', $this->plugin_slug ) . '</strong>: ' . sprintf( __( 'You should inform your Client_secret. %s', $this->plugin_slug ), '<a href="' . $this->admin_url() . '">' . __( 'Click here to configure!', $this->plugin_slug ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Adds error message when an unsupported currency is used.
	 *
	 * @return string
	 */
	public function currency_not_supported_message() {
		echo '<div class="error"><p><strong>' . __( 'MercadoPago Disabled', $this->plugin_slug ) . '</strong>: ' . sprintf( __( 'Currency <code>%s</code> is not supported. Please make sure that you use one of the following supported currencies: ARS, BRL, MXN, USD or VEF.', $this->plugin_slug ), get_woocommerce_currency() ) . '</p></div>';
	}
}
