<?php
/**
 * WooCommerce MercadoPago API class
 *
 * @package WooCommerce_MercadoPago/Classes/API
 * @since   3.0.0
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MercadoPago Checkout API class.
 */
class WC_Mercadopago_API {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.mercadopago.com/';

	/**
	 * Gateway class.
	 *
	 * @var WC_MercadoPago_Gateway
	 */
	protected $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_MercadoPago_Gateway $gateway Gateway class.
	 */
	public function __construct( $gateway = null ) {
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
	 * @param  string $credentials Access token.
	 * @param  bool $subscription Subscription order.
	 *
	 * @return string
	 */
	public function get_checkout_url( $credentials = '', $subscription = false ) {
		$endpoint = $subscription ? 'preapproval' : 'checkout/preferences';

		return $this->get_api_url() . $endpoint . '?access_token=' . $credentials;
	}

	/**
	 * Get IPN URL.
	 *
	 * @param string $id          Purchase ID.
	 * @param string $credentials Access token.
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
	 * Get supported currencies.
	 *
	 * @return array
	 */
	public function get_supported_currencies() {
		return apply_filters( 'woocommerce_mercadopago_supported_currencies', array(
			'ARS',
			'BOB',
			'BRL',
			'CLF',
			'CLP',
			'COP',
			'CRC',
			'CUC',
			'DOP',
			'EUR',
			'GTQ',
			'HNL',
			'MXN',
			'NIO',
			'PAB',
			'PEN',
			'PYG',
			'USD',
			'UYU',
			'VEF',
		) );
	}

	protected function format_date( $date ) {
		return date( 'Y-m-d\TH:i:s.ZP', strtotime( $date ) );
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
				'Content-Type' => 'application/json;charset=UTF-8',
			),
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
	 * Get purchase args.
	 *
	 * @param  WC_Order $order Order data.
	 *
	 * @return array
	 */
	protected function get_purchase_args( $order ) {
		$args = array(
			'back_urls' => array(
				'success' => esc_url( $this->gateway->get_return_url( $order ) ),
				'failure' => str_replace( '&amp;', '&', $order->get_cancel_order_url() ),
				'pending' => esc_url( $this->gateway->get_return_url( $order ) ),
			),
			'auto_return' => 'approved',
			'payer' => array(
				'name'    => $order->billing_first_name,
				'surname' => $order->billing_last_name,
				'email'   => $order->billing_email,
			),
			'external_reference' => $this->gateway->invoice_prefix . $order->id,
			'items' => array(
				array(
					'quantity'    => 1,
					'unit_price'  => $order->get_total(),
					'currency_id' => $order->get_order_currency(),
					'category_id' => 'others', // Generic category ID.
				),
			),
		);

		// Cart Contents.
		$item_names = array();
		if ( 0 < count( $order->get_items() ) ) {
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

		// Deprecated since 3.0.0.
		$args = apply_filters( 'woocommerce_mercadopago_args', $args, $order );

		return apply_filters( 'woocommerce_mercadopago_purchase_args', $args, $order );
	}

	/**
	 * Get subscription payment args.
	 *
	 * @param  WC_Order $order Order ID.
	 *
	 * @return array
	 */
	protected function get_subscription_args( $order ) {
		$subscriptions = wcs_get_subscriptions_for_order( $order->id );
		$subscription  = current( $subscriptions );

		if ( ! in_array( $subscription->billing_period, array( 'day', 'month' ) ) ) {
			wc_add_notice( '<strong>' . esc_html( $this->gateway->title ) . ': </strong>' . __( 'Process only daily or monthly subscriptions.', 'woocommerce-mercadopago' ), 'error' );
			return array();
		}

		$args = array(
			'payer_email'        => $subscription->billing_email,
			'back_url'           => esc_url( $this->gateway->get_return_url( $order ) ),
			'reason'             => sprintf( __( '%s - Subscription for order #%s', 'woocommerce' ), esc_html( get_bloginfo( 'name', 'display' ) ), $order->get_order_number() ),
			'external_reference' => $this->gateway->invoice_prefix . $order->id,
			'auto_recurring'     => array(
				'frequency'          => (int) $subscription->billing_interval,
				'frequency_type'     => $subscription->billing_period . 's',
				'transaction_amount' => (float) $subscription->get_total(),
				'currency_id'        => $subscription->get_order_currency(),
			)
		);

		if ( $start_date = $subscription->calculate_date( 'trial_end' ) ) {
			$args['auto_recurring']['start_date'] = $this->format_date( $start_date );
		}
		if ( $end_date   = $subscription->calculate_date( 'end' ) ) {
			$args['auto_recurring']['end_date'] = $this->format_date( $end_date );
		}

		return apply_filters( 'woocommerce_mercadopago_subscription_args', $args, $order, $subscription );
	}

	/**
	 * Generate the payment args.
	 *
	 * @param  WC_Order $order        Order data.
	 * @param  bool     $subscription Subscription order.
	 *
	 * @return array
	 */
	protected function get_payment_args( $order, $subscription = false ) {
		if ( $subscription ) {
			return $this->get_subscription_args( $order );
		}

		return $this->get_purchase_args( $order );
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
			'client_id'     => $this->gateway->client_id,
			'client_secret' => $this->gateway->client_secret,
			'grant_type'    => 'client_credentials',
		) );
		$headers = array(
			'Content-Type' => 'application/x-www-form-urlencoded',
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
	 * @param  WC_Order $order        Order data.
	 * @param  bool     $subscription Subscription order.
	 *
	 * @return string
	 */
	public function get_user_payment_url( $order, $subscription = false ) {
		$data = json_encode( $this->get_payment_args( $order, $subscription ) );

		if ( empty( $data ) ) {
			return '';
		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Payment arguments for order ' . $order->get_order_number() . ': ' . print_r( $data, true ) );
		}

		$credentials = $this->get_client_credentials();
		$url         = $this->get_checkout_url( $credentials, $subscription );
		$response    = $this->do_request( $url, 'POST', $data );

		if ( ! is_wp_error( $response ) && 201 == $response['response']['code'] && ( 0 == strcmp( $response['response']['message'], 'Created' ) ) ) {
			$payment_data = json_decode( $response['body'] );

			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Payment link generated from MercadoPago successfully!' );
			}

			if ( 'yes' == $this->gateway->sandbox ) {
				return $payment_data->sandbox_init_point;
			} else {
				return $payment_data->init_point;
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
		if ( ! isset( $data['id'], $data['topic'] ) ) {
			return array();
		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Checking IPN request...' );
		}

		$data = wp_unslash( $data );

		$id          = sanitize_text_field( $data['id'] );
		$credentials = $this->get_client_credentials();
		$url         = $this->get_ipn_url( $id, $credentials );
		$response    = $this->do_request( $url, 'GET' );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'IPN Response: ' . print_r( $response, true ) );
		}

		// Check to see if the request was valid.
		if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Received valid IPN response from MercadoPago' );
			}

			return json_decode( $response['body'] );
		} else {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Received invalid IPN response from MercadoPago.' );
			}
		}

		return array();
	}
}
