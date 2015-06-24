<?php
/**
 * WC MercadoPago Gateway Class.
 *
 * Built the MercadoPago method.
 */
class WC_MercadoPago_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// Standards.
		$this->id              = 'mercadopago';
		$this->icon            = apply_filters( 'woocommerce_mercadopago_icon', plugins_url( 'images/mercadopago.png', plugin_dir_path( __FILE__ ) ) );
		$this->has_fields      = false;
		$this->method_title    = __( 'MercadoPago', 'woocommerce-mercadopago' );

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
		add_action( 'woocommerce_api_wc_mercadopago_gateway', array( $this, 'check_ipn_response' ) );
		add_action( 'woocommerce_mercadopago_change_order_status', array( $this, 'change_order_status' ) );
		add_action( 'wp_head', array( $this, 'css' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-mercadopago' ) . '</a>';
		}

		return '<code>woocommerce/logs/' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.txt</code>';
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
				'label'   => __( 'Enable MercadoPago standard', 'woocommerce-mercadopago' ),
				'default' => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-mercadopago' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-mercadopago' ),
				'desc_tip'    => true,
				'default'     => __( 'MercadoPago', 'woocommerce-mercadopago' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-mercadopago' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-mercadopago' ),
				'default'     => __( 'Pay via MercadoPago', 'woocommerce-mercadopago' )
			),
			'client_id' => array(
				'title'             => __( 'MercadoPago Client_id', 'woocommerce-mercadopago' ),
				'type'              => 'text',
				'description'       => __( 'Please enter your MercadoPago Client_id.', 'woocommerce-mercadopago' ) . ' ' . sprintf( __( 'You can to get this information in MercadoPago from %s.', 'woocommerce-mercadopago' ), $api_secret_locale ),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required'
				)
			),
			'client_secret' => array(
				'title'             => __( 'MercadoPago Client_secret', 'woocommerce-mercadopago' ),
				'type'              => 'text',
				'description'       => __( 'Please enter your MercadoPago Client_secret.', 'woocommerce-mercadopago' ) . ' ' . sprintf( __( 'You can to get this information in MercadoPago from %s.', 'woocommerce-mercadopago' ), $api_secret_locale ),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required'
				)
			),
			'invoice_prefix' => array(
				'title'       => __( 'Invoice Prefix', 'woocommerce-mercadopago' ),
				'type'        => 'text',
				'description' => __( 'Please enter a prefix for your invoice numbers. If you use your MercadoPago account for multiple stores ensure this prefix is unqiue as MercadoPago will not allow orders with the same invoice number.', 'woocommerce-mercadopago' ),
				'desc_tip'    => true,
				'default'     => 'WC-'
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
				)
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
				'description' => sprintf( __( 'Log MercadoPago events, such as API requests. You can find the log in %s', 'woocommerce-mercadopago' ), $this->get_log_view() ),
			)
		);
	}

	/**
	 * Admin page.
	 */
	public function admin_options() {
		include 'views/html-admin-page.php';
	}

	/**
	 * Fix MercadoPago CSS.
	 *
	 * @return string Styles.
	 */
	public function css() {
		if ( is_page( wc_get_page_id( 'checkout' ) ) ) {
			echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>' . PHP_EOL;
		}
	}

	/**
	 * Generate the form.
	 *
	 * @param int     $order_id Order ID.
	 *
	 * @return string           Payment form.
	 */
	public function get_modal_buttons( $order_id ) {
		$order = new WC_Order( $order_id );
		$url   = $this->api->get_user_payment_url( $order );

		ob_start();
		include 'views/html-modal-payment.php';
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	/**
	 * Output for the order received page.
	 */
	public function receipt_page( $order ) {
		echo $this->get_modal_buttons( $order );
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
			$url = $this->api->get_user_payment_url( $order );
		} else {
			$url = $order->get_checkout_payment_url( true );
		}

		return array(
			'result'   => '' !== $url ? 'success' : 'fail',
			'redirect' => $url
		);
	}

	/**
	 * Check API Response.
	 */
	public function check_ipn_response() {
		@ob_clean();

		if ( $data = $this->api->get_payment_data( $_GET ) ) {
			header( 'HTTP/1.1 200 OK' );
			do_action( 'valid_mercadopago_ipn_request', $data ); // Deprecated since 3.0.0
			do_action( 'woocommerce_mercadopago_change_order_status', $data );
		} else {
			wp_die( __( 'MercadoPago Request Failure', 'woocommerce-mercadopago' ) );
		}
	}

	/**
	 * Change order status
	 *
	 * @param array $posted MercadoPago post data.
	 */
	public function change_order_status( $posted ) {
		$data      = $posted->collection;
		$_order_id = $data->external_reference;

		if ( ! empty( $_order_id ) ) {
			$order_id = intval( str_replace( $this->invoice_prefix, '', $_order_id ) );
			$order    = new WC_Order( $order_id );

			// Checks whether the invoice number matches the order.
			// If true processes the payment.
			if ( $order->id === $order_id ) {

				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, 'Payment status from order ' . $order->get_order_number() . ': ' . $data->status );
				}

				switch ( $data->status ) {
					case 'approved' :

						// Order details.
						if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1.12', '<=' ) ) {
							// Save the transaction ID has custom field just for old versions.
							if ( ! empty( $data->id ) ) {
								add_post_meta(
									$order_id,
									__( 'MercadoPago Transaction ID', 'woocommerce-mercadopago' ),
									sanitize_text_field( $data->id ),
									true
								);
							}
						}
						if ( ! empty( $data->payer->email ) ) {
							add_post_meta(
								$order_id,
								__( 'Payer email', 'woocommerce-mercadopago' ),
								sanitize_text_field( $data->payer->email ),
								true
							);
						}
						if ( ! empty( $data->payment_type ) ) {
							add_post_meta(
								$order_id,
								__( 'Payment type', 'woocommerce-mercadopago' ),
								sanitize_text_field( $data->payment_type ),
								true
							);
						}

						// For WooCommerce 2.2 or later.
						add_post_meta( $order->id, '_transaction_id', sanitize_text_field( $data->id ), true );

						// Payment completed.
						if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ) ) ) {
							$order->add_order_note( __( 'MercadoPago: Payment approved.', 'woocommerce-mercadopago' ) );
							$order->payment_complete();
						}

						break;
					case 'pending' :
						$order->add_order_note( __( 'MercadoPago: The user has not completed the payment process yet.', 'woocommerce-mercadopago' ) );

						break;
					case 'in_process' :
						$order->update_status( 'on-hold', __( 'MercadoPago: Payment under review.', 'woocommerce-mercadopago' ) );

						break;
					case 'rejected' :
						$order->update_status( 'failed', __( 'MercadoPago: The payment was declined. The user can try again.', 'woocommerce-mercadopago' ) );

						break;
					case 'refunded' :
						$order->update_status( 'refunded', __( 'MercadoPago: The payment was returned to the user.', 'woocommerce-mercadopago' ) );

						break;
					case 'cancelled' :
						$order->update_status( 'cancelled', __( 'MercadoPago: Payment canceled.', 'woocommerce-mercadopago' ) );

						break;
					case 'in_mediation' :
						$order->add_order_note( __( 'MercadoPago: It started a dispute for payment.', 'woocommerce-mercadopago' ) );

						break;

					default :
						// No action xD.
						break;
				}
			}
		}
	}
}
