<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MercadoPago Customized Checkout API class.
 */
class WC_Mercadopago_API {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.mercadolibre.com/';

	/**
	 * JS Library URL.
	 *
	 * @var string
	 */
	protected $js_url = 'https://secure.mlstatic.com/org-img/checkout/custom/1.0/checkout.js';

	/**
	 * Gateway class.
	 *
	 * @var WC_MercadoPago_Gateway
	 */
	protected $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_MercadoPago_Gateway $gateway
	 */
	public function __construct( $gateway = null, $method = '' ) {
		$this->gateway = $gateway;
	}

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
	}

	/**
	 * Get Checkout URL.
	 *
	 * @param  string $credentials
	 *
	 * @return string
	 */
	public function get_checkout_url( $credentials = '' ) {
		return $this->get_api_url() . 'checkout/preferences?access_token=' . $credentials;
	}

	/**
	 * Get IPN URL.
	 *
	 * @return string
	 */
	public function get_ipn_url( $id, $credentials = '' ) {
		$sandbox = 'yes' == $this->gateway->sandbox ? 'sandbox/' : '';

		return $this->get_api_url() . $sandbox . 'collections/notifications/' . $id . '?access_token=' . $credentials;
	}

	/**
	 * Get OAuth Token URL.
	 *
	 * @return string
	 */
	public function get_oauth_token_url() {
		return $this->get_api_url() . 'oauth/token';
	}

	/**
	 * Get JS Library URL.
	 *
	 * @return string
	 */
	public function get_js_url() {
		return $this->js_url;
	}

	/**
	 * Get supported currencies.
	 *
	 * @return array
	 */
	public function get_supported_currencies() {
		return apply_filters( 'woocommerce_mercadopago_supported_currencies', array( 'ARS', 'BRL', 'COP', 'MXN', 'USD', 'VEF' ) );
	}

	/**
	 * Do requests in the MercardoPago API.
	 *
	 * @param  string $url     URL.
	 * @param  string $method  Request method (default: POST).
	 * @param  array  $data    Request data (default: array()).
	 * @param  array  $headers Request headers (default: array()).
	 *
	 * @return array           Request response.
	 */
	protected function do_request( $url, $method = 'POST', $data = array(), $headers = array() ) {
		$params = array(
			'method'  => $method,
			'timeout' => 60,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json;charset=UTF-8'
			)
		);

		if ( ! empty( $data ) ) {
			$params['body'] = $data;
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = $headers;
		}

		return wp_safe_remote_post( $url, $params );
	}

	/**
	 * Generate the payment args.
	 *
	 * @param  object $order Order data.
	 *
	 * @return array         Payment data.
	 */
	protected function get_payment_args( $order ) {
		$args = array(
			'back_urls' => array(
				'success' => esc_url( $this->gateway->get_return_url( $order ) ),
				'failure' => str_replace( '&amp;', '&', $order->get_cancel_order_url() ),
				'pending' => esc_url( $this->gateway->get_return_url( $order ) )
			),
			'payer' => array(
				'name'    => $order->billing_first_name,
				'surname' => $order->billing_last_name,
				'email'   => $order->billing_email
			),
			'external_reference' => $this->gateway->invoice_prefix . $order->id,
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

		$args['items'][0]['title'] = sprintf( __( 'Order %s', 'woocommerce-mercadopago' ), $order->get_order_number() ) . ' - ' . implode( ', ', $item_names );

		// Shipping Cost item.
		if ( $order->get_total_shipping() > 0 ) {
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
				$shipping_method = $order->get_shipping_method();
			} else {
				$shipping_method = ucwords( $order->shipping_method_title );
			}

			$args['items'][0]['title'] .= ', ' . __( 'Shipping via', 'woocommerce-mercadopago' ) . ' ' . $shipping_method;
		}

		$args = apply_filters( 'woocommerce_mercadopago_args', $args, $order );

		return $args;
	}

	/**
	 * Get cliente token.
	 *
	 * @return mixed Sucesse return the token and error return null.
	 */
	protected function get_client_credentials() {
		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Getting client credentials...' );
		}

		// Set data.
		$data = build_query( array(
			'grant_type'    => 'client_credentials',
			'client_id'     => $this->gateway->client_id,
			'client_secret' => $this->gateway->client_secret
		) );
		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/x-www-form-urlencoded'
		);

		$response = $this->do_request( $this->get_oauth_token_url(), 'POST', $data, $headers );

		// Check to see if the request was valid and return the token.
		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && ( strcmp( $response['response']['message'], 'OK' ) == 0 ) ) {

			$token = json_decode( $response['body'] );

			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Received valid response from MercadoPago' );
			}

			return $token->access_token;
		} else {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Received invalid response from MercadoPago. Error response: ' . print_r( $response, true ) );
			}
		}

		return null;
	}

	/**
	 * Generate the MercadoPago payment url.
	 *
	 * @param  object $order Order Object.
	 *
	 * @return string        MercadoPago payment url.
	 */
	public function get_user_payment_url( $order ) {
		$data = json_encode( $this->get_payment_args( $order ) );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Payment arguments for order ' . $order->get_order_number() . ': ' . print_r( $data, true ) );
		}

		$credentials = $this->get_client_credentials();
		$url         = $this->get_checkout_url( $credentials );
		$response    = $this->do_request( $url, 'POST', $data );

		if ( ! is_wp_error( $response ) && $response['response']['code'] == 201 && ( strcmp( $response['response']['message'], 'Created' ) == 0 ) ) {
			$payment_data = json_decode( $response['body'] );

			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Payment link generated from MercadoPago successfully!' );
			}

			if ( 'yes' == $this->gateway->sandbox ) {
				return esc_url( $payment_data->sandbox_init_point );
			} else {
				return esc_url( $payment_data->init_point );
			}

		} else {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Generate payment error response: ' . print_r( $response, true ) );
			}
		}

		wc_add_notice( __( 'An error has occurred while processing your order, please try again. Or contact us for assistance.', 'woocommerce-mercadopago' ), 'error' );

		return '';
	}

	/**
	 * Get Payment data.
	 *
	 * @param  array $data MercadoPago post data.
	 *
	 * @return array
	 */
	public function get_payment_data( $data ) {
		if ( ! isset( $data['id'] ) ) {
			return array();
		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Checking IPN request...' );
		}

		$id          = sanitize_text_field( $data['id'] );
		$credentials = $this->get_client_credentials();
		$url         = $this->get_ipn_url( $id, $credentials );
		$response    = $this->do_request( $url, 'POST' );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'IPN Response: ' . print_r( $response, true ) );
		}

		// Check to see if the request was valid.
		if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Received valid IPN response from MercadoPago' );
			}

			$body = json_decode( $response['body'] );

			return $body;
		} else {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Received invalid IPN response from MercadoPago.' );
			}
		}

		return array();
	}
}
