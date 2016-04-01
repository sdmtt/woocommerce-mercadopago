<?php
/**
 * WooCommerce MercadoPago Gateway class
 *
 * @package WooCommerce_MercadoPago/Classes/Gateway
 * @since   3.0.0
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MercadoPago payment method.
 */
class WC_MercadoPago_Gateway extends WC_Payment_Gateway {

	/**
	 * Initialize payment gateway actions.
	 */
	public function __construct() {
		// Standards.
		$this->id              = 'mercadopago';
		$this->icon            = apply_filters( 'woocommerce_mercadopago_icon', plugins_url( 'images/mercadopago.png', plugin_dir_path( __FILE__ ) ) );
		$this->has_fields      = false;
		$this->method_title    = __( 'MercadoPago', 'woocommerce-mercadopago' );
		$this->supports        = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'gateway_scheduled_payments',
		);

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
		$this->sandbox        = $this->get_option( 'sandbox' );
		$this->debug          = $this->get_option( 'debug' );

		// Active logs.
		if ( 'yes' == $this->debug ) {
			$this->log = new WC_Logger();
		}

		$this->api = new WC_Mercadopago_API( $this );

		// Actions.
		add_action( 'woocommerce_api_wc_mercadopago_gateway', array( $this, 'ipn_handler' ) );
		add_action( 'woocommerce_mercadopago_change_order_status', array( $this, 'change_order_status' ), 10, 2 );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_subscription_cancelled_' . $this->id, array( $this, 'cancel_subscription' ) );
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
		$available = parent::is_available() &&
					! empty( $this->client_id ) &&
					! empty( $this->client_secret ) &&
					in_array( get_woocommerce_currency(), $this->api->get_supported_currencies() );

		return $available;
	}

	/**
	 * Initialise gateway settings.
	 */
	public function init_form_fields() {
		$api_secret_locale = sprintf(
			'<a href="https://www.mercadopago.com/mla/herramientas/aplicaciones" target="_blank">%s</a>, <a href="https://www.mercadopago.com/mlb/ferramentas/aplicacoes" target="_blank">%s</a>, <a href="https://www.mercadopago.com/mco/ferramentas/aplicacoes" target="_blank">%s</a>, <a href="https://www.mercadopago.com/mlm/herramientas/aplicaciones" target="_blank">%s</a> %s <a href="https://www.mercadopago.com/mlv/herramientas/aplicaciones" target="_blank">%s</a>',
			__( 'Argentine', 'woocommerce-mercadopago' ),
			__( 'Brazil', 'woocommerce-mercadopago' ),
			__( 'Colombia', 'woocommerce-mercadopago' ),
			__( 'Mexico', 'woocommerce-mercadopago' ),
			__( 'or', 'woocommerce-mercadopago' ),
			__( 'Venezuela', 'woocommerce-mercadopago' )
		);

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-mercadopago' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable MercadoPago', 'woocommerce-mercadopago' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-mercadopago' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-mercadopago' ),
				'desc_tip'    => true,
				'default'     => __( 'MercadoPago', 'woocommerce-mercadopago' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-mercadopago' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-mercadopago' ),
				'default'     => __( 'Pay via MercadoPago', 'woocommerce-mercadopago' ),
			),
			'client_id' => array(
				'title'             => __( 'MercadoPago Client_id', 'woocommerce-mercadopago' ),
				'type'              => 'text',
				'description'       => __( 'Please enter your MercadoPago Client_id.', 'woocommerce-mercadopago' ) . ' ' . sprintf( __( 'You can to get this information in MercadoPago from %s.', 'woocommerce-mercadopago' ), $api_secret_locale ),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'client_secret' => array(
				'title'             => __( 'MercadoPago Client_secret', 'woocommerce-mercadopago' ),
				'type'              => 'text',
				'description'       => __( 'Please enter your MercadoPago Client_secret.', 'woocommerce-mercadopago' ) . ' ' . sprintf( __( 'You can to get this information in MercadoPago from %s.', 'woocommerce-mercadopago' ), $api_secret_locale ),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'invoice_prefix' => array(
				'title'       => __( 'Invoice Prefix', 'woocommerce-mercadopago' ),
				'type'        => 'text',
				'description' => __( 'Please enter a prefix for your invoice numbers. If you use your MercadoPago account for multiple stores ensure this prefix is unqiue as MercadoPago will not allow orders with the same invoice number.', 'woocommerce-mercadopago' ),
				'desc_tip'    => true,
				'default'     => 'WC-',
			),
			'method' => array(
				'title'       => __( 'Integration method', 'woocommerce-mercadopago' ),
				'type'        => 'select',
				'description' => __( 'Choose how the customer will interact with the MercadoPago. Modal Window (Inside your store) Redirect (Client goes to MercadoPago).', 'woocommerce-mercadopago' ),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => 'redirect',
				'options'     => array(
					'modal'    => __( 'Modal Window', 'woocommerce-mercadopago' ),
					'redirect' => __( 'Redirect', 'woocommerce-mercadopago' ),
				),
			),
			'testing' => array(
				'title'       => __( 'Gateway Testing', 'woocommerce-mercadopago' ),
				'type'        => 'title',
				'description' => '',
			),
			'sandbox' => array(
				'title'       => __( 'MercadoPago Sandbox', 'woocommerce-mercadopago' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable MercadoPago sandbox', 'woocommerce-mercadopago' ),
				'default'     => 'no',
				'description' => __( 'MercadoPago sandbox can be used to test payments.', 'woocommerce-mercadopago' ),
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-mercadopago' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce-mercadopago' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log MercadoPago events, such as API requests. You can find the log in %s', 'woocommerce-mercadopago' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-mercadopago' ) . '</a>' ),
			),
		);
	}

	/**
	 * Admin page.
	 */
	public function admin_options() {
		include 'admin/views/html-admin-page.php';
	}

	/**
	 * Validate order for subscriptions.
	 * MercadoPago have a few limitations, we can just process one subscription at a time.
	 *
	 * @param  WC_Order $order Order data.
	 *
	 * @return bool
	 */
	public function validate_subscription_order( $order ) {
		$valid = true;

		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item['qty'] ) {
				$product = $order->get_product_from_item( $order_item );
				if ( ! in_array( $product->product_type, array( 'subscription', 'variable-subscription' ) ) ) {
					wc_add_notice( '<strong>' . esc_html( $this->title ) . ': </strong>' . __( 'Only can process one subscription at a time without other products in the cart. Please remove any other products before continue.', 'woocommerce-mercadopago' ), 'error' );

					$valid = false;
					break;
				}
			}
		}

		if ( $valid && 0 < WC_Subscriptions_Order::get_sign_up_fee( $order ) ) {
			wc_add_notice( '<strong>' . esc_html( $this->title ) . ': </strong>' . __( 'Unable to process signatures with sign-up fees.', 'woocommerce-mercadopago' ), 'error' );
			$valid = false;
		}

		return $valid;
	}

	/**
	 * Cancel subscription.
	 *
	 * @param WC_Subscription $subscription Subscription data.
	 */
	public function cancel_subscription( $subscription ) {
		if ( $id = $subscription->order->mercadopago_payment_id ) {
			$this->api->cancel_subscription( $id );
		}
	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function receipt_page( $order_id ) {
		$order        = wc_get_order( $order_id );
		$subscription = false;

		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->id ) ) {
			$subscription = true;
		}

		$url = $this->api->get_user_payment_url( $order, $subscription );

		include 'views/html-modal-payment.php';
	}

	/**
	 * Process subscription payment.
	 *
	 * @param  WC_Order $order Order Data.
	 *
	 * @return array
	 */
	protected function process_subscription_payment( $order ) {
		$url = '';

		if ( $this->validate_subscription_order( $order ) ) {
			if ( 'redirect' == $this->method ) {
				$url = $this->api->get_user_payment_url( $order, true );
			} else {
				$url = $order->get_checkout_payment_url( true );
			}
		}

		return array(
			'result'   => '' !== $url ? 'success' : 'fail',
			'redirect' => $url,
		);
	}

	/**
	 * Process regular payment.
	 *
	 * @param  WC_Order $order Order Data.
	 *
	 * @return array
	 */
	protected function process_regular_payment( $order ) {
		if ( 'redirect' == $this->method ) {
			$url = $this->api->get_user_payment_url( $order );
		} else {
			$url = $order->get_checkout_payment_url( true );
		}

		return array(
			'result'   => '' !== $url ? 'success' : 'fail',
			'redirect' => $url,
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->id ) ) {
			return $this->process_subscription_payment( $order );
		} else {
			return $this->process_regular_payment( $order );
		}
	}

	/**
	 * IPN handler.
	 */
	public function ipn_handler() {
		@ob_clean();

		if ( $data = $this->api->get_payment_data( $_GET ) ) {
			header( 'HTTP/1.1 200 OK' );

			$topic = isset( $_GET['topic'] ) ? sanitize_text_field( wp_unslash( $_GET['topic'] ) ) : '';
			do_action( 'woocommerce_mercadopago_change_order_status', $data, $topic );

			// Deprecated since 3.0.0.
			do_action( 'valid_mercadopago_ipn_request', $data );
		} else {
			wp_die( esc_html__( 'MercadoPago Request Unauthorized', 'woocommerce-mercadopago' ), esc_html__( 'MercadoPago Request Unauthorized', 'woocommerce-mercadopago' ), array( 'response' => 200 ) );
		}
	}

	/**
	 * Change order status
	 *
	 * @param array $purchase_data MercadoPago purchase data.
	 * @param array $topic         MercadoPago notification topic.
	 */
	public function change_order_status( $purchase_data, $topic ) {
		if ( 'payment' !== $topic ) {
			return;
		}

		$data = $purchase_data->collection;

		if ( empty( $data->external_reference ) ) {
			return;
		}

		$order_id = intval( str_replace( $this->invoice_prefix, '', $data->external_reference ) );
		$order    = wc_get_order( $order_id );

		// Checks whether the invoice number matches the order.
		// If true processes the payment.
		if ( $order->id !== $order_id ) {
			return;
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, 'Payment status for order ' . $order->get_order_number() . ': ' . $data->status );
		}

		// Check if is a subscription order.
		$is_subscription = false;
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->id ) ) {
			$is_subscription = true;
		}

		switch ( $data->status ) {
			case 'pending' :
				$order->add_order_note( __( 'MercadoPago: The user has not completed the payment process yet.', 'woocommerce-mercadopago' ) );

				break;
			case 'approved' :
				if ( ! empty( $data->payer->email ) ) {
					add_post_meta(
						$order->id,
						__( 'Payer email', 'woocommerce-mercadopago' ),
						sanitize_text_field( $data->payer->email ),
						true
					);
				}
				if ( ! empty( $data->payment_type ) ) {
					add_post_meta(
						$order->id,
						__( 'Payment type', 'woocommerce-mercadopago' ),
						sanitize_text_field( $data->payment_type ),
						true
					);
				}

				add_post_meta( $this->id, '_transaction_id', sanitize_text_field( $data->id ), true );

				// Payment completed.
				if ( $is_subscription ) {
					WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
				} else if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ) ) ) {
					$order->payment_complete();
				}

				$order->add_order_note( __( 'MercadoPago: Payment approved.', 'woocommerce-mercadopago' ) );

				break;
			case 'in_process' :
				$order->update_status( 'on-hold', __( 'MercadoPago: Payment under review.', 'woocommerce-mercadopago' ) );

				break;
			case 'rejected' :
				if ( $is_subscription ) {
					WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
				} else {
					$order->update_status( 'failed' );
				}

				$order->add_order_note( __( 'MercadoPago: The payment was declined. The user can try again.', 'woocommerce-mercadopago' ) );

				break;
			case 'refunded' :
				if ( $is_subscription ) {
					WC_Subscriptions_Manager::cancel_subscriptions_for_order( $order );
				} else {
					$order->update_status( 'refunded' );
				}

				$order->add_order_note( __( 'MercadoPago: The payment was refunded.', 'woocommerce-mercadopago' ) );

				break;
			case 'cancelled' :
				if ( $is_subscription ) {
					WC_Subscriptions_Manager::cancel_subscriptions_for_order( $order );
				} else {
					$order->update_status( 'cancelled' );
				}

				$order->add_order_note( __( 'MercadoPago: Payment canceled.', 'woocommerce-mercadopago' ) );

				break;
			case 'in_mediation' :
				$order->add_order_note( __( 'MercadoPago: It started a dispute for payment.', 'woocommerce-mercadopago' ) );

				break;
			case 'charged_back' :
				if ( $is_subscription ) {
					WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
				} else {
					$order->update_status( 'failed' );
				}

				$order->add_order_note( __( 'MercadoPago: Payment refused because a credit card chargeback.', 'woocommerce-mercadopago' ) );

				break;

			default :
				// No action xD.
				break;
		}
	}
}
