<?php
/**
 * Plugin Name: WooCommerce MercadoPago
 * Plugin URI: http://claudiosmweb.com/plugins/mercadopago-para-woocommerce/
 * Description: Gateway de pagamento MercadoPago para WooCommerce.
 * Author: claudiosanches
 * Author URI: http://www.claudiosmweb.com/
 * Version: 1.2
 * License: GPLv2 or later
 * Text Domain: wcmercadopago
 * Domain Path: /languages/
 */

/**
 * WooCommerce fallback notice.
 */
function wcmercadopago_woocommerce_fallback_notice() {
    $message = '<div class="error">';
        $message .= '<p>' . __( 'WooCommerce MercadoPago Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> to work!' , 'wcmercadopago' ) . '</p>';
    $message .= '</div>';

    echo $message;
}

/**
 * Load functions.
 */
add_action( 'plugins_loaded', 'wcmercadopago_gateway_load', 0 );

function wcmercadopago_gateway_load() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wcmercadopago_woocommerce_fallback_notice' );

        return;
    }

    /**
     * Load textdomain.
     */
    load_plugin_textdomain( 'wcmercadopago', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    /**
     * Add the gateway to WooCommerce.
     *
     * @access public
     * @param array $methods
     * @return array
     */
    add_filter( 'woocommerce_payment_gateways', 'wcmercadopago_add_gateway' );

    function wcmercadopago_add_gateway( $methods ) {
        $methods[] = 'WC_MercadoPago_Gateway';
        return $methods;
    }

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
            global $woocommerce;

            $this->id             = 'mercadopago';
            $this->icon           = plugins_url( 'images/mercadopago.png', __FILE__ );
            $this->has_fields     = false;
            $this->payment_url    = 'https://api.mercadolibre.com/checkout/preferences?access_token=';
            $this->ipn_url        = 'https://api.mercadolibre.com/collections/notifications/';
            $this->oauth_token    = 'https://api.mercadolibre.com/oauth/token';

            $this->method_title   = __( 'MercadoPago', 'wcmercadopago' );

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables.
            $this->title          = $this->settings['title'];
            $this->description    = $this->settings['description'];
            $this->client_id      = $this->settings['client_id'];
            $this->client_secret  = $this->settings['client_secret'];
            $this->invoice_prefix = !empty( $this->settings['invoice_prefix'] ) ? $this->settings['invoice_prefix'] : 'WC-';
            $this->debug          = $this->settings['debug'];

            // Actions.
            add_action( 'init', array( &$this, 'check_ipn_response' ) );
            add_action( 'valid_mercadopago_ipn_request', array( &$this, 'successful_request' ) );
            add_action( 'woocommerce_receipt_mercadopago', array( &$this, 'receipt_page' ) );
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            add_action( 'wp_head', array( &$this, 'css' ) );

            // Valid for use.
            $this->enabled = ( 'yes' == $this->settings['enabled'] ) && !empty( $this->client_id ) && !empty( $this->client_secret ) && $this->is_valid_for_use();

            // Checks if client_id is not empty.
            $this->client_id == '' ? add_action( 'admin_notices', array( &$this, 'client_id_missing_message' ) ) : '';

            // Checks if client_secret is not empty.
            $this->client_secret == '' ? add_action( 'admin_notices', array( &$this, 'client_secret_missing_message' ) ) : '';

            // Active logs.
            if ( $this->debug == 'yes' ) {
                $this->log = $woocommerce->logger();
            }
        }

        /**
         * Check if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use() {
            if ( !in_array( get_woocommerce_currency() , array( 'ARS', 'BRL' ) ) ) {
                return false;
            }

            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         * @since 1.0.0
         */
        public function admin_options() {

            ?>
            <h3><?php _e( 'MercadoPago standard', 'wcmercadopago' ); ?></h3>
            <p><?php _e( 'MercadoPago standard works by sending the user to MercadoPago to enter their payment information.', 'wcmercadopago' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <?php
        }

        /**
         * Initialise Gateway Settings Form Fields.
         *
         * @return void
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'wcmercadopago' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable MercadoPago standard', 'wcmercadopago' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'wcmercadopago' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'wcmercadopago' ),
                    'default' => __( 'MercadoPago', 'wcmercadopago' )
                ),
                'description' => array(
                    'title' => __( 'Description', 'wcmercadopago' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'wcmercadopago' ),
                    'default' => __( 'Pay via MercadoPago', 'wcmercadopago' )
                ),
                'client_id' => array(
                    'title' => __( 'MercadoPago Client_id', 'wcmercadopago' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your MercadoPago Client_id.', 'wcmercadopago' ) . ' ' . sprintf( __( 'You can to get this information in: %sMercadoPago of Brazil%s or %sMercadoPago of Argentine%s.', 'wcmercadopago' ), '<a href="https://www.mercadopago.com/mlb/ferramentas/aplicacoes" target="_blank">', '</a>', '<a href="https://www.mercadopago.com/mla/herramientas/aplicaciones" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'client_secret' => array(
                    'title' => __( 'MercadoPago Client_secret', 'wcmercadopago' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your MercadoPago Client_secret.', 'wcmercadopago' ) . ' ' . sprintf( __( 'You can to get this information in: %sMercadoPago of Brazil%s or %sMercadoPago of Argentine%s.', 'wcmercadopago' ), '<a href="https://www.mercadopago.com/mlb/ferramentas/aplicacoes" target="_blank">', '</a>', '<a href="https://www.mercadopago.com/mla/herramientas/aplicaciones" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'invoice_prefix' => array(
                    'title' => __( 'Invoice Prefix', 'wcmercadopago' ),
                    'type' => 'text',
                    'description' => __( 'Please enter a prefix for your invoice numbers. If you use your MercadoPago account for multiple stores ensure this prefix is unqiue as MercadoPago will not allow orders with the same invoice number.', 'wcmercadopago' ),
                    'default' => 'WC-'
                ),
                'testing' => array(
                    'title' => __( 'Gateway Testing', 'wcmercadopago' ),
                    'type' => 'title',
                    'description' => '',
                ),
                'debug' => array(
                    'title' => __( 'Debug Log', 'wcmercadopago' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable logging', 'wcmercadopago' ),
                    'default' => 'no',
                    'description' => __( 'Log MercadoPago events, such as API requests, inside <code>woocommerce/logs/mercadopago.txt</code>', 'wcmercadopago'  ),
                )
            );
        }

        /**
         * Generate the args to form.
         *
         * @param  array $order Order data.
         * @return array
         */
        public function get_form_args( $order ) {

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
                        'picture_url' => 'https://www.mercadopago.com/org-img/MP3/home/logomp3.gif'
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

            $args['items'][0]['title'] = sprintf( __( 'Order %s' , 'wcmercadopago' ), $order->get_order_number() ) . ' - ' . implode( ', ', $item_names );

            // Shipping Cost item.
            if ( $order->get_shipping() > 0 ) {
                $args['items'][0]['title'] .= ', ' . __( 'Shipping via', 'wcmercadopago' ) . ' ' . ucwords( $order->shipping_method_title );
            }

            $args = apply_filters( 'woocommerce_mercadopago_args', $args );

            return $args;
        }

        /**
         * Generate the form.
         *
         * @param mixed $order_id
         * @return string
         */
        public function generate_form( $order_id ) {

            $order = new WC_Order( $order_id );

            $args = json_encode( $this->get_form_args( $order ) );

            if ( $this->debug == 'yes' ) {
                $this->log->add( 'mercadopago', 'Payment arguments for order #' . $order_id . ': ' . print_r( $this->get_form_args( $order ), true ) );
            }

            $url = $this->payment_url . $this->get_client_credentials();

            $params = array(
                'body'          => $args,
                'sslverify'     => false,
                'timeout'       => 30,
                'headers'       => array( 'content-type' => 'application/json;charset=UTF-8' )
            );

            $response = wp_remote_post( $url, $params );

            if ( !is_wp_error( $response ) && $response['response']['code'] == 201 && ( strcmp( $response['response']['message'], 'Created' ) == 0 ) ) {

                // Get payment url.
                $checkout_info = json_decode( $response['body'] );

                // Display checkout.
                $html = '<p>' . __( 'Thank you for your order, please click the button below to pay with MercadoPago.', 'wcmercadopago' ) . '</p>';

                $html .= '<a id="submit-payment" href="' . esc_url( $checkout_info->init_point ) . '" name="MP-Checkout" class="button alt" mp-mode="modal">' . __( 'Pay via MercadoPago', 'wcmercadopago' ) . '</a> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'wcmercadopago' ) . '</a>';

                // Add MercadoPago JS.
                $html .= '<script type="text/javascript">(function(){function $MPBR_load(){window.$MPBR_loaded !== true && (function(){var s = document.createElement("script");s.type = "text/javascript";s.async = true;s.src = ("https:"==document.location.protocol?"https://www.mercadopago.com/org-img/jsapi/mptools/buttons/":"http://mp-tools.mlstatic.com/buttons/")+"render.js";var x = document.getElementsByTagName("script")[0];x.parentNode.insertBefore(s, x);window.$MPBR_loaded = true;})();}window.$MPBR_loaded !== true ? (window.attachEvent ? window.attachEvent("onload", $MPBR_load) : window.addEventListener("load", $MPBR_load, false)) : null;})();</script>';

                if ( $this->debug == 'yes') {
                    $this->log->add( 'mercadopago', 'Payment link generated with success from MercadoPago' );
                }

                return $html;

            } else {
                // Display message if a problem occurs.
                $html = '<p>' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'wcmercadopago' ) . '</p>';

                $html .='<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Click to try again', 'wcmercadopago' ) . '</a>';

                if ( $this->debug == 'yes' ) {
                    $this->log->add( 'mercadopago', 'Generate payment error response: ' . print_r( $response, true ) );
                }

                return $html;
            }

        }

        /**
         * Fix MercadoPago CSS.
         *
         * @return string Styles.
         */
        public function css() {
            echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>';
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = new WC_Order( $order_id );

            return array(
                'result'    => 'success',
                'redirect'  => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
            );
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

            if ( $this->debug == 'yes') {
                $this->log->add( 'mercadopago', 'Getting client credentials...' );
            }

            // Set postdata.
            $postdata = 'grant_type=client_credentials';
            $postdata .= '&client_id=' . $this->client_id;
            $postdata .= '&client_secret=' . $this->client_secret;

            // Built wp_remote_post params.
            $params = array(
                'body'          => $postdata,
                'sslverify'     => false,
                'timeout'       => 30
            );

            $response = wp_remote_post( $this->oauth_token, $params );

            // Check to see if the request was valid and return the token.
            if ( !is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && ( strcmp( $response['response']['message'], 'OK' ) == 0 ) ) {

                $token = json_decode( $response['body'] );

                if ( $this->debug == 'yes') {
                    $this->log->add( 'mercadopago', 'Received valid response from MercadoPago' );
                }

                return $token->access_token;
            } else {
                if ( $this->debug == 'yes') {
                    $this->log->add( 'mercadopago', 'Received invalid response from MercadoPago. Error response: ' . print_r( $response, true ) );
                }
            }

            return null;
        }

        /**
         * Check ipn validity.
         *
         * @return mixed
         */
        public function check_ipn_request_is_valid( $data ) {

            if ( $this->debug == 'yes') {
                $this->log->add( 'mercadopago', 'Checking IPN request...' );
            }

            $url = $this->ipn_url . $data['id'] . '?access_token=' . $this->get_client_credentials();

            // Send back post vars.
            $params = array(
                'sslverify'     => false,
                'timeout'       => 30
            );

            // GET a response.
            $response = wp_remote_get( $url, $params );

            if ( $this->debug == 'yes' ) {
                $this->log->add( 'mercadopago', 'IPN Response: ' . print_r( $response, true ) );
            }

            // Check to see if the request was valid.
            if ( !is_wp_error( $response ) && $response['response']['code'] == 200 ) {

                $body = json_decode( $response['body'] );

                $this->log->add( 'mercadopago', 'Received valid IPN response from MercadoPago' );

                return $body;
            } else {
                if ( $this->debug == 'yes' ) {
                    $this->log->add( 'mercadopago', 'Received invalid IPN response from MercadoPago.' );
                }
            }

            return null;
        }

        /**
         * Check API Response.
         *
         * @return void
         */
        public function check_ipn_response() {

            if ( is_admin() ) {
                return;
            }

            if ( isset( $_GET['topic'] ) ) {

                @ob_clean();

                $data = $this->check_ipn_request_is_valid( $_GET );

                if ( $data ) {

                    header( 'HTTP/1.0 200 OK' );

                    do_action( 'valid_mercadopago_ipn_request', $data );

                } else {

                   header( 'HTTP/1.0 404 Not Found' );

                }
            }
        }

        /**
         * Successful Payment!
         *
         * @param array $posted
         * @return void
         */
        public function successful_request( $posted ) {

            $data = $posted->collection;
            $order_key = $data->external_reference;

            if ( !empty( $order_key ) ) {
                $order_id = (int) str_replace( $this->invoice_prefix, '', $order_key );

                $order = new WC_Order( $order_id );

                // Checks whether the invoice number matches the order.
                // If true processes the payment.
                if ( $order->id === $order_id ) {

                    if ( $this->debug == 'yes' ) {
                        $this->log->add( 'mercadopago', 'Payment status from order #' . $order->id . ': ' . $data->status );
                    }

                    switch ( $data->status ) {
                        case 'approved':

                            // Order details.
                            if ( !empty( $data->id ) ) {
                                update_post_meta(
                                    $order_id,
                                    __( 'MercadoPago Transaction ID', 'wcmercadopago' ),
                                    $data->id
                                );
                            }
                            if ( !empty( $data->payer->email ) ) {
                                update_post_meta(
                                    $order_id,
                                    __( 'Payer email', 'wcmercadopago' ),
                                    $data->payer->email
                                );
                            }
                            if ( !empty( $data->payment_type ) ) {
                                update_post_meta(
                                    $order_id,
                                    __( 'Payment type', 'wcmercadopago' ),
                                    $data->payment_type
                                );
                            }

                            // Payment completed.
                            $order->add_order_note( __( 'The payment was approved by MercadoPago.', 'wcmercadopago' ) );
                            $order->payment_complete();

                            break;
                        case 'pending':
                            $order->add_order_note( __( 'The user has not completed the payment process yet.', 'wcmercadopago' ) );

                            break;
                        case 'in_process':
                            $order->update_status( 'on-hold', __( 'Payment under review by MercadoPago.', 'wcmercadopago' ) );

                            break;
                        case 'rejected':
                            $order->add_order_note( __( 'The payment was declined. The user can try again.', 'wcmercadopago' ) );

                            break;
                        case 'refunded':
                            $order->update_status( 'refunded', __( 'The payment was returned to the user.', 'wcmercadopago' ) );

                            break;
                        case 'cancelled':
                            $order->update_status( 'cancelled', __( 'Payment canceled by MercadoPago.', 'wcmercadopago' ) );

                            break;
                        case 'in_mediation':
                            $order->add_order_note( __( 'It started a dispute for payment.', 'wcmercadopago' ) );

                            break;

                        default:
                            // No action xD.
                            break;
                    }
                }
            }
        }

        /**
         * Adds error message when not configured the client_id.
         *
         * @return string Error Mensage.
         */
        public function client_id_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your Client_id in MercadoPago. %sClick here to configure!%s' , 'wcmercadopago' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }

        /**
         * Adds error message when not configured the client_secret.
         *
         * @return string Error Mensage.
         */
        public function client_secret_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your Client_secret in MercadoPago. %sClick here to configure!%s' , 'wcmercadopago' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }

    } // class WC_MercadoPago_Gateway.
} // function wcmercadopago_gateway_load.
