<?php
/*
 *Plugin Name: Payscript CryptoCheckout
 *Description: Enable your WooCommerce store to accept cryptocurrency payments with Payscript.
 *Author:      Payscript
 *Author URI:  https://payscript.io
 *WC requires at least: 5.3
 *Version:     1.0.0
 */

// Exit if accessed directly
if (false === defined('ABSPATH')) {
    exit;
}

/**
 * Define Constants
 */
define( 'PAYSCRIPT_VERSION', '1.0.0' );

// Ensure that WooCommerce is loaded
add_action('plugins_loaded', 'woocommerce_payscript_init');
register_activation_hook(__FILE__, 'woocommerce_payscript_activate');

function woocommerce_payscript_init() {
    if (true === class_exists('WC_Gateway_Payscript')) {
        return;
    }

    if (false === class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Payscript extends WC_Payment_Gateway {

        private $is_initialized = false;
        public static $api_username;

        // Constructor for the WC Gateway.
        public function __construct() {
            // General settings
            $this->id = 'payscript';
            $this->icon = plugin_dir_url(__FILE__) . 'assets/images/payscript.svg';
            $this->has_fields = false;
            $this->order_button_text = __('Proceed to Payscript', 'payscript');
            $this->method_title = 'Payscript';
            $this->method_description = 'Payscript allows you to accept cryptocurrency payments on your WooCommerce store.';

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = "Pay with Crypto";
            $this->description = "You are about to make your payment in crypto via <a target='_blank' href='https://payscript.io/'>Payscript</a>";

            // Define API settings
            $this->api_endpointurl = $this->get_option('api_endpointurl');
            $this->api_privatekey = $this->get_option('api_privatekey');
            $this->api_publickey = $this->get_option('api_publickey');

            // Define debugging & informational settings
            $this->debug_php_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            $this->debug_plugin_version = constant("PAYSCRIPT_VERSION");
            $this->is_initialized = true;
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        }

        //Initialise WC Gateway Settings Form Fields
        public function init_form_fields() {
            $this->form_fields = include 'includes/settings-payscript.php';
        }

        public function __destruct() {
            //do nothing
        }

        // function to start process payment
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            if (false === $order) {
                throw new \Exception('Unable to retrieve the order details for Order ID ' . $order_id . '. Unable to proceed.');
            }
            $order->update_meta_data('_payscript_transaction_id', '');
            $order->update_meta_data('_payscript_address_id', '');
            $order->update_meta_data('_payscript_amount', '');
            $order->update_meta_data('_payscript_rate', '');
            $order->update_meta_data('_payscript_timestamp', '');

            $new_order_statuses = $this->get_option('order_statuses');
            $new_order_status = $new_order_statuses['initiated'];
            $order->update_status($new_order_status);
            if (get_page_by_title('Payscript Payment Status') == NULL) {
                $createPage = array(
                    'post_title' => 'Payscript Payment Status',
                    'post_content' => '',
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_type' => 'page',
                    'post_name' => 'Payscript Payment Status'
                );
                // Insert the post into the database
                wp_insert_post($createPage);
            }

            $thanks_link = get_permalink(get_page_by_title('Payscript Payment Status')) . "?order=" . $order_id . "&step=1&key=" . mt_rand(100000, 999999);
            $response = array(
                'result' => 'success',
                'redirect' => $thanks_link,
            );
            return $response;
        }

        // function to load external scripts
        public function admin_scripts() {
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            if ('woocommerce_page_wc-settings' !== $screen_id) {
                return;
            }
            wp_enqueue_script('woocommerce_payscript_admin', plugin_dir_url(__FILE__) . '/assets/js/payscript-admin' . '.js', array(), WC_VERSION, true);
            wp_localize_script('woocommerce_payscript_admin', 'PayscriptAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'authNonce' => wp_create_nonce('payscript-authorize-nonce'),
                'revokeNonce' => wp_create_nonce('payscript-revoke-nonce')
                )
            );
        }

        // HTML output for form type [order_status]
        public function generate_order_statuses_html() {
            ob_start();

            $fnp_statuses = array(
                'initiated' => 'INITIATED',
                'received' => 'RECEIVED',
                'sent' => 'SENT',
                'confirmed' => 'CONFIRMED',
                'timeout' => 'TIMEOUT',
                'failed' => 'FAILED',
                'error' => 'ERROR',
                'amount_not_matched' => 'AMOUNT NOT MATCHED');

            $defined_statuses = array(
                'initiated' => 'wc-pending',
                'received' => 'wc-processing',
                'sent' => 'wc-processing',
                'confirmed' => 'wc-processing',
                'timeout' => 'wc-failed',
                'failed' => 'wc-failed',
                'error' => 'wc-failed',
                'amount_not_matched' => 'wc-failed');

            $wc_statuses = wc_get_order_statuses();
            $wc_statuses = array('PAYSCRIPT_IGNORE' => '') + $wc_statuses;
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="woocommerce_payscript_status">Order Status <span class="woocommerce-help-tip" data-tip="You can predefine how different invoice status will reflect on your WooCommerce order status"></span></label>
                </th>
                <td class="forminp" id="payscript_order_statuses">
                    <table cellspacing="0" cellpadding="0">
            <?php
            foreach ($fnp_statuses as $fnp_state => $fnp_name) {
                ?>
                    <tr>
                        <td><?php echo esc_html($fnp_name); ?></td>
                        <td>=></td>
                        <td><select name="woocommerce_payscript_order_statuses[<?php echo esc_attr($fnp_state); ?>]">
                <?php
                $order_statuses = get_option('woocommerce_payscript_settings');
                $order_statuses = isset($order_statuses['order_statuses'])?$order_statuses['order_statuses']:'';
                foreach ($wc_statuses as $wc_state => $wc_name) {
                    $current_option = isset($order_statuses[$fnp_state])?$order_statuses[$fnp_state]:'';

                    if (true === empty($current_option)) {
                        $current_option = $defined_statuses[$fnp_state];
                    }

                    if ($current_option === $wc_state) {
                        echo '<option value=' . esc_attr($wc_state) . ' selected>' . esc_attr($wc_name) . '</option>';
                    } else {
                        echo '<option value=' . esc_attr($wc_state) . '>' . esc_attr($wc_name) . '</option>';
                    }
                }
                ?>
                            </select>
                        </td>
                    </tr>
                <?php
                                    }
            ?>
                    </table>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

                    // Validation of Order Status
        public function validate_order_statuses_field()
        {
            $order_statuses = $this->get_option('order_statuses');

            $order_statuses_key = $this->plugin_id . $this->id . '_order_statuses';
            if (isset($_POST[$order_statuses_key])) {
                $order_statuses = $_POST[$order_statuses_key];
            }

            return $order_statuses;
        }

    }

    /**
     * Function verify payscript api.
     * Retrieved payment gateway information.
     *
     * @return array
     */
    function verify_payscript_api($order = null)
    {
        $payment_gateway_id = 'payscript';
        $payment_gateways   = WC_Payment_Gateways::instance();
        $payment_gateway    = $payment_gateways->payment_gateways()[$payment_gateway_id];

        $api_endpoint_url   = $payment_gateway->api_endpointurl;
        $api_private_key    = $payment_gateway->api_privatekey;
        $api_public_key     = $payment_gateway->api_publickey;
        $new_order_status   = $payment_gateway->get_option('order_statuses');

        $thanks_link = '';
        !empty($order) ? $thanks_link = $payment_gateway->get_return_url($order) : '';

        $get_signature      = get_signature($api_public_key, $api_private_key);

        return array(
            'api_endpoint_url'  => $api_endpoint_url,
            'api_private_key'   => $api_private_key,
            'api_public_key'    => $api_public_key,
            'get_signature'     => $get_signature,
            'new_order_status'  => $new_order_status,
            'thanks_link'       => $thanks_link,
        );
    }

    /**
     * Function to get current balance
     *
     * action : call get balance api.
     *
     * @return void
     */
    function ajax_payscript_get_balance()
    {
		$nonce = isset($_POST['authNonce']) ? wc_clean(wp_unslash($_POST['authNonce'])) : '';
		        
		if (!wp_verify_nonce($nonce, 'payscript-authorize-nonce')) {
            die('Unauthorized');
        }
		
        $paymentGatewayInfo = verify_payscript_api();
        $endpoint_url       = $paymentGatewayInfo['api_endpoint_url'] . '/wallet/balance/BTC';

        $args = array(
            'method'    => 'GET',
            'headers'   => array(
                'content-type'      => 'application/json',
                'PS-API-KEY'        => $paymentGatewayInfo['api_public_key'],
                'PS-API-SIGNATURE'  => $paymentGatewayInfo['get_signature']['signature'],
                'PS-API-TIMESTAMP'  => $paymentGatewayInfo['get_signature']['time'],
            ),
            'sslverify' => false,
        );

        $response   = wp_remote_get($endpoint_url, $args);
        $data       = json_decode(wp_remote_retrieve_body($response));

        if (isset($data->status) && $data->status == 200) {
            $responseData = array(
                'message' => 'Your wallet balance is ' . printf("%s", $data->balance) . ' ' . $data->currencySymbol,
                'success' => 1
            );
        } else {
            $responseData = array(
                'message' => isset($data->error->message) ? $data->error->message : "Something went wrong!",
                'success' => 0
            );
        }

        echo json_encode($responseData);
        wp_die();
    }
    add_action('wp_ajax_payscript_get_balance', 'ajax_payscript_get_balance');

    /**
     * Function get list crypto of payscript.
     *
     * @param $type
     * @param $order
     *
     * @return array|string
     */
    function get_crypto_list($type, $order)
    {
        $paymentGatewayInfo = verify_payscript_api();
        $endpoint_url       = $paymentGatewayInfo['api_endpoint_url'] . '/wallet/active';

        $args = array(
            'method'    => 'GET',
            'headers'   => array(
                'content-type'      => 'application/json',
                'PS-API-KEY'        => $paymentGatewayInfo['api_public_key'],
                'PS-API-SIGNATURE'  => $paymentGatewayInfo['get_signature']['signature'],
                'PS-API-TIMESTAMP'  => $paymentGatewayInfo['get_signature']['time'],
            ),
            'sslverify' => false,
        );

        $response   = wp_remote_get($endpoint_url, $args);
        $data       = json_decode(wp_remote_retrieve_body($response));

        if (isset($data->data) && count($data->data) > 0) {
            $response = array('message' => 'Cryptocurrency list.', 'success' => 1, 'data' => $data->data);
        } else {
            $response = array('message' => "Cryptocurrency not available.", 'success' => 0);
        }

        if ($type == "html") {
            $crypto_list = "";
            $crypto_list .= "<div class='col-lg-12 col-md-12 col-sm-12 col-xs-12'><h4 class='ps-cryptosec__txt'><img src=" . plugin_dir_url(__FILE__) . "assets/images/loader_new.gif> Cryptocurrency value updates in every <span id='crypto-timer'>00:10</span> seconds</h4></div>";

            $order_id = $order;
            $order = wc_get_order($order_id);
            global $woocommerce;
            if (isset($data->data) && count($data->data) > 0) {
                foreach ($data->data as $list) {
                    if ($list->status == "ENABLED") {
                        $get_exchange_rate = get_exchange_rate($list->currencySymbol);
                        if (!isset($get_exchange_rate['rate']->error)) {
                            $btc_rate = $get_exchange_rate['rate']->price;
                            $calculate_btc_amount = number_format((1 / $btc_rate) * $order->get_total(), $get_exchange_rate['precision'], '.', ',');
                            $payment_link = get_permalink(get_page_by_title('Payscript Payment Status')) . "?order=" . $order_id . "&step=2&type=" . strtolower($list->currencySymbol) . "&symbol_name=" . $list->currencyName . "&key=" . mt_rand(100000, 999999);
                            $crypto_list .= "<div class='col-lg-12 col-md-12 col-sm-12 col-xs-12 d-inline'>";
                            $crypto_list .= "<a href='$payment_link'><div class='ps-fnp-inner-box'>";

                            $crypto_list .= "<div class='ps-fnp-box-info'>";
                            $crypto_list .= "<img src='" . plugin_dir_url(__FILE__) . "assets/images/" . strtolower($list->currencySymbol) . ".svg'>";
                            $crypto_list .= "<div>";
                            $crypto_list .= "<div class='ps-short_name'>" . $list->currencySymbol . "</div>";
                            $crypto_list .= "<div class='ps-full-name'>" . $list->currencyName . "</div>";
                            $crypto_list .= "</div>";
                            $crypto_list .= "<div class='ps-cryptocurrency__details'>";
                            $crypto_list .= "<div id='cryptotag_" . strtolower($list->currencySymbol) . "' class='ps-cryptocurrency_loop' data-order_id='$order_id' data-crypto='" . strtolower($list->currencySymbol) . "' data-amount='" . $calculate_btc_amount . "'>" . $calculate_btc_amount . "</div>";
                            $crypto_list .= "<div class='ps-full-name ps-full_name_value'>$" . $order->get_total() . " " . get_woocommerce_currency() . "</div>";
                            $crypto_list .= "</div>";
                            $crypto_list .= "</div>";
                            $crypto_list .= "<div class='clear'></div></div></a>";
                            $crypto_list .= "</div>";
                        }
                    }
                }
            } else {
                $crypto_list .= "<div class='col-lg-12 col-md-12 col-sm-12 col-xs-12'>";
                $crypto_list .= "<div class='ps-fnp-inner-box'>";
                $crypto_list .= "List Not Found";
                $crypto_list .= "</div>";
                $crypto_list .= "</div>";
            }
            return $crypto_list;
        }
        return $response;
    }

    /**
     * Function get exchange rate.
     *
     * @param string $symbol
     *
     * @return array|int[]
     */
    function get_exchange_rate(string $symbol = "BTC")
    {
        global $woocommerce;
        $currency = get_woocommerce_currency();
		
        $paymentGatewayInfo = verify_payscript_api();
        $endpoint_url       = $paymentGatewayInfo['api_endpoint_url'] . '/price/ticker?symbol=' . $symbol . '&amount=1&convertTo=' . get_woocommerce_currency();

        $args = array(
            'method'    => 'GET',
            'headers'   => array(
                'content-type'      => 'application/json',
                'PS-API-KEY'        => $paymentGatewayInfo['api_public_key'],
                'PS-API-SIGNATURE'  => $paymentGatewayInfo['get_signature']['signature'],
                'PS-API-TIMESTAMP'  => $paymentGatewayInfo['get_signature']['time'],
            ),
            'sslverify' => false,
        );

        $response   = wp_remote_get($endpoint_url, $args);
        $data       = json_decode(wp_remote_retrieve_body($response));

        if (isset($data->$symbol->$currency)) {
            $response = array(
                'success'   => 1,
                'rate'      => $data->$symbol->$currency,
                'precision' => isset($data->$symbol->precision) ? $data->$symbol->precision : 0
            );
        } else {
            $response = array('success' => 0);
        }

        return $response;
    }

    /**
     * Function check balance.
     *
     * @param $order_amount
     * @param $symbol
     *
     * @return array
     */
    function check_balance($order_amount, $symbol)
    {	
        $paymentGatewayInfo = verify_payscript_api();
        $endpoint_url       = $paymentGatewayInfo['api_endpoint_url'] . '/wallet/balance/' . $symbol;

        $args = array(
            'method'    => 'GET',
            'headers'   => array(
                'content-type'      => 'application/json',
                'PS-API-KEY'        => $paymentGatewayInfo['api_public_key'],
                'PS-API-SIGNATURE'  => $paymentGatewayInfo['get_signature']['signature'],
                'PS-API-TIMESTAMP'  => $paymentGatewayInfo['get_signature']['time'],
            ),
            'sslverify' => false,
        );

        $response   = wp_remote_get($endpoint_url, $args);
        $data       = json_decode(wp_remote_retrieve_body($response));

        if (isset($data->data->status) && $data->data->status == "ENABLED") {
            if ($order_amount > $data->data->balance) {
                $response = array('message' => 'You have insufficient balance in your wallet', 'success' => 0);
            } else {
                $response = array('message' => 'You may now proceed', 'success' => 1);
            }
        } else {
            $response = array('message' => isset($data->message) ? $data->message : "Something went wrong!", 'success' => 0);
        }

        return $response;
    }

    /**
     * Function get session payscript.
     *
     * @param $symbol
     * @return array|int[]
     */
    function get_session($symbol)
    {
        $paymentGatewayInfo = verify_payscript_api();
        $endpoint_url       = $paymentGatewayInfo['api_endpoint_url'] . '/transaction/session';

        $args = array(
            'method'    => 'POST',
            'headers'   => array(
                'content-type'      => 'application/json',
                'PS-API-KEY'        => $paymentGatewayInfo['api_public_key'],
                'PS-API-SIGNATURE'  => $paymentGatewayInfo['get_signature']['signature'],
                'PS-API-TIMESTAMP'  => $paymentGatewayInfo['get_signature']['time'],
            ),
            'sslverify' => false,
            'body'      => json_encode(array('symbol' => strtoupper($symbol)))
        );

        $response   = wp_remote_post($endpoint_url, $args);
        $data       = json_decode(wp_remote_retrieve_body($response));

        if (isset($data->data->procToken)) {
            $response = array('success' => 1, 'proctoken' => $data->data->procToken);
        } else {
            $response = array('success' => 0);
        }

        return $response;
    }

    /**
     * Function do transaction.
     *
     * @param $proctoken
     * @param $order_id
     * @param $calculate_btc_amount
     * @param $btc_rate
     * @param $symbol
     *
     * @return array
     */
    function do_transaction($proctoken, $order_id, $calculate_btc_amount, $btc_rate, $symbol)
    {		
        global $woocommerce;
        $order = wc_get_order($order_id);

        $paymentGatewayInfo = verify_payscript_api();
        $endpoint_url = $paymentGatewayInfo['api_endpoint_url'] . '/transaction';

        $post_fields = array('amount' => $calculate_btc_amount,
            'baseCurrency' => get_woocommerce_currency(),
            'buyerName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'buyeraddress1' => $order->get_billing_address_1(),
            'buyeraddress2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'country' => $order->get_billing_country(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'procToken' => $proctoken,
            'remark' => 'Order number ' . $order->get_order_number(),
            'state' => $order->get_billing_state(),
            'zip' => $order->get_billing_postcode(),
            'paymentSpeed' => 'SLOW',
            'transactionType' => 'SALE',
            'currency' => strtoupper($symbol)
        );

        $args = array(
            'method'    => 'POST',
            'headers'   => array(
                'content-type'      => 'application/json',
                'PS-API-KEY'        => $paymentGatewayInfo['api_public_key'],
                'PS-API-SIGNATURE'  => $paymentGatewayInfo['get_signature']['signature'],
                'PS-API-TIMESTAMP'  => $paymentGatewayInfo['get_signature']['time'],
            ),
            'sslverify' => false,
            'body'      => json_encode($post_fields)
        );

        $response   = wp_remote_post($endpoint_url, $args);
        $data       = json_decode(wp_remote_retrieve_body($response));

        if (isset($data->status)) {
            $note = "";
            if (isset($data->data->note)) {
                $note = $data->data->note;
            }
            $new_order_statuses = $paymentGatewayInfo['new_order_status'];

            WC()->cart->empty_cart();

            //add transaction id to order
            $order->update_meta_data('_payscript_transaction_id', $data->data->id);
            $order->update_meta_data('_payscript_address_id', $data->data->session->address);
            $order->update_meta_data('_payscript_amount', $calculate_btc_amount);
            $order->update_meta_data('_payscript_rate', $btc_rate);
            $order->update_meta_data('_payscript_timestamp', $paymentGatewayInfo['get_signature']['normal_time']);
            if ($data->data->status == "INITIATED") {
                $order->update_status($new_order_statuses['initiated']);
                $order->save();
                $response = array(
                    'result' => 'success',
                    'normal_time' => $paymentGatewayInfo['get_signature']['normal_time'],
                    '_payscript_transaction_id' => $data->data->id,
                    '_payscript_address_id' => $data->data->session->address,
                    '_payscript_amount' => $calculate_btc_amount,
                    '_payscript_rate' => $btc_rate,
                    'note' => $note
                );
            } else {
                $order->update_status($new_order_statuses['error']);
                $order->save();
                WC()->cart->empty_cart();
                $response = array(
                    'result' => 'success',
                    'normal_time' => $paymentGatewayInfo['get_signature']['normal_time'],
                    '_payscript_transaction_id' => $data->data->id,
                    '_payscript_address_id' => $data->data->session->address,
                    '_payscript_amount' => $calculate_btc_amount,
                    '_payscript_rate' => $btc_rate,
                    'note' => $note
                );
            }
        } else {
            $response = array(
                'result' => 'success',
                'normal_time' => $paymentGatewayInfo['get_signature']['normal_time']
            );
        }

        return $response;
    }

    // function to generate signature
    function get_signature($public_key, $secret_key)
    {
        $time = round(microtime(true) * 1000);
        $normal_time = time();
        $string = $public_key . $time;
        $signature = hash_hmac('sha512', $string, $secret_key);
        return array('time' => $time, 'signature' => $signature, 'normal_time' => $normal_time);
    }

    add_filter('woocommerce_payment_gateways', 'wc_add_payscript');

    // function to add method in woocommerce payment page
    function wc_add_payscript($methods)
    {
        $methods[] = 'WC_Gateway_Payscript';
        return $methods;
    }

    //thank you page modification
    add_action("the_content", 'action_woocommerce_thankyou_payscript', 10, 1);
	add_filter( 'wp_kses_allowed_html', 'action_woocommerce_thankyou_payscript', 10 , 1);

    function action_woocommerce_thankyou_payscript($content)
    {
        if (is_page('Payscript Payment Status')) {
            if (!isset($_GET['order'])) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit();
            }
            ob_start();
            if (isset($_GET['step']) && $_GET['step'] == 1) {
                $order = wc_get_order($_GET['order']);
                $order_ipaddress = $order->get_customer_ip_address();
                $my_ip_address = WC_Geolocation::get_ip_address();
                if ($order_ipaddress != $my_ip_address) {
                    $general_message = file_get_contents(plugin_dir_path(__FILE__) . 'templates/generalMessage.tpl');
                    $message = "<div class='alert alert-danger'>Unable to process your order.</div>";
                    $general_message = str_replace('{$message}', $message, $general_message);
                    return $general_message;
                }
                //calling cryptocurrency list api
                $get_list_crypto = get_crypto_list('html', $_GET['order']);
                $payment_list = file_get_contents(plugin_dir_path(__FILE__) . 'templates/paymentList.tpl');
                $payment_list = str_replace('{$cryptoList}', $get_list_crypto, $payment_list);
				return $payment_list;
            } else if (isset($_GET['step']) && $_GET['step'] == 2) {
                global $woocommerce;
                $order = wc_get_order($_GET['order']);
                $order_ipaddress = $order->get_customer_ip_address();
                $my_ip_address = WC_Geolocation::get_ip_address();
                if ($order_ipaddress != $my_ip_address) {
                    $general_message = file_get_contents(plugin_dir_path(__FILE__) . 'templates/generalMessage.tpl');
                    $message = "<div class='alert alert-danger'>Unable to process your order.</div>";
                    $general_message = str_replace('{$message}', $message, $general_message);
                    return $general_message;
                }
                //fetch cryptocurrency type and process payment
                $symbol = isset($_GET['type']) ? wc_clean(wp_unslash($_GET['type'])) : '';
                $symbolName = isset($_GET['symbol_name']) ? wc_clean(wp_unslash($_GET['symbol_name'])) : '';

                $get_exchange_rate = get_exchange_rate(strtoupper($symbol));
                if ($get_exchange_rate['success'] == 0) {
                    $general_message = file_get_contents(plugin_dir_path(__FILE__) . 'templates/generalMessage.tpl');
                    $message = "<div class='alert alert-danger'>Unable to retrieve exchange rate. Unable to proceed.</div>";
                    $general_message = str_replace('{$message}', $message, $general_message);
                    return $general_message;
                }
                //1 btc rate
                $btc_rate = $get_exchange_rate['rate']->price;
                $calculate_btc_amount = number_format((1 / $btc_rate) * $order->get_total(), $get_exchange_rate['precision'], '.', ',');

                //get session | proctoken
                $get_session = get_session($symbol);
                if ($get_session['success'] == 0) {
                    $general_message = file_get_contents(plugin_dir_path(__FILE__) . 'templates/generalMessage.tpl');
                    $message = "<div class='alert alert-danger'>Unable to retrieve proctoken. Unable to proceed.</div>";
                    $general_message = str_replace('{$message}', $message, $general_message);
                    return $general_message;
                }
                $proctoken = $get_session['proctoken'];
                //do transaction
                $btc_address = "";
                if ($btc_address == "") {
                    $get_session_response = do_transaction($proctoken, $_GET['order'], $calculate_btc_amount, $btc_rate, $symbol);
                    if ($get_session_response['result'] == "success") {
                        $btc_address = $get_session_response['_payscript_address_id'];
                        $order_id = $_GET['order'];
                        $btc_rate = $get_session_response['_payscript_rate'];
                        $btc_amount = $get_session_response['_payscript_amount'];
                        $btc_transaction_id = $get_session_response['_payscript_transaction_id'];
                        $btc_timestamp = $get_session_response['normal_time'];
                        $note = $get_session_response['note'];
                        $final_remainig_secs = 900 - (time() - (int)$btc_timestamp);
                        $formated_remaing_min = date("i", $final_remainig_secs);
                        $formated_remaing_sec = date("s", $final_remainig_secs);
                        $formated_remaining_time = sprintf("%02d", $formated_remaing_min) . ":" . sprintf("%02d", $formated_remaing_sec);
                        if ($formated_remaing_min < 0) {
                            $general_message = file_get_contents(plugin_dir_path(__FILE__) . 'templates/generalMessage.tpl');
                            $message = "<div class='alert alert-danger'>Payment process Timeout. Please try again later.</div>";
                            $general_message = str_replace('{$message}', $message, $general_message);
                            return $general_message;
                        }

                        if ($order === false) {
                            return;
                        }

                        $order_data = $order->get_data();
                        $status = $order_data['status'];
							
                        $payment_status = file_get_contents(plugin_dir_path(__FILE__) . 'templates/paymentStatus.tpl');
                        $payment_status = str_replace('{$statusTitle}', _x('Payment Status', 'woocommerce_atomicpay'), $payment_status);
                        $payment_status = str_replace('{$pluginURL}', plugin_dir_url(__FILE__), $payment_status);
//                        $payment_status = str_replace('{$qrImage}', $qr_image, $payment_status);
                        $payment_status = str_replace('{$btcAddress}', $btc_address, $payment_status);
                        $payment_status = str_replace('{$btcAmount}', $btc_amount, $payment_status);
                        $payment_status = str_replace('{$btcRate}', $btc_rate, $payment_status);
                        $payment_status = str_replace('{$orderAmount}', floatval($order->get_total()), $payment_status);
                        $payment_status = str_replace('{$transactionID}', $btc_transaction_id, $payment_status);
                        $payment_status = str_replace('{$orderID}', $order_id, $payment_status);
                        $payment_status = str_replace('{$timeStart}', $formated_remaining_time, $payment_status);
                        $payment_status = str_replace('{$symbol}', strtoupper($symbol), $payment_status);
                        $payment_status = str_replace('{$smallSymbol}', strtolower($symbol), $payment_status);
                        $payment_status = str_replace('{$symbolName}', $symbolName, $payment_status);
                        $payment_status = str_replace('{$wooCurrency}', get_woocommerce_currency(), $payment_status);

                        //display if note available
                        if ($note != "") {
                            $note_html = '<div class="row ps-processsec__row algo__input">
                                            <div class="col-md-12 col-sm-12 col-lg-12 ps-fnp-text-center ps-fnp-input-div">
                                                <label class="ps-fp-txt-address">Note <br/> (Please send note (UTF-8) when you send ALGO)</label>
                                                <div class="pos__input">
                                                <input class="ps-fnp-input ps-fp-address ps-fp-note" type="text" value="' . $note . '" readonly>
                                                <div class="ps-copy-img"><a href="javascript://" class="clip-copy"><img src="' . plugin_dir_url(__FILE__) . 'assets/images/copy1.png"></a></div>
                                                </div>
                                            </div>
                                        </div>';
                        } else {
                            $note_html = "";
                        }

                        $payment_status = str_replace('{$note}', $note_html, $payment_status);

                        $status_description = _x('Waiting for payment', 'woocommerce_atomicpay');
						
						return str_replace('{$paymentStatus}', $status_description, $payment_status);
                    } else {
                        $general_message = file_get_contents(plugin_dir_path(__FILE__) . 'templates/generalMessage.tpl');
                        $message = "<div class='alert alert-danger'>Unable to retrieve proctoken. Unable to proceed.</div>";
                        $general_message = str_replace('{$message}', $message, $general_message);
                        return $general_message;
                    }
                }
            }
            return ob_get_clean();
        } else {
            return $content;
        }
    }
	function create_qr_code(){ ?>
		<script>
			jQuery(document).ready(function() {
				new QRCode(document.getElementById("payscript-qrcode"), document.getElementById("payscript-address").value);
			})
		</script>
	<?php } 

	add_action('wp_footer', 'create_qr_code');

    add_action('wp_ajax_payscript_order_failed', 'payscript_order_failed');
    add_action('wp_ajax_nopriv_payscript_order_failed', 'payscript_order_failed');

    function payscript_order_failed()
    {
        $order_id = isset($_POST['order_id']) ? wc_clean($_POST['order_id']) : null;
        $order = wc_get_order($order_id);
        $payment_gateway_id = 'payscript';
        $payment_gateways = WC_Payment_Gateways::instance();
        $payment_gateway = $payment_gateways->payment_gateways()[$payment_gateway_id];
        $new_order_statuses = $payment_gateway->get_option('order_statuses');
        $order->update_status($new_order_statuses['error']);
        $order->save();
        $thanks_link = $payment_gateway->get_return_url($order);
        $response = array('message' => 'Proceed', 'redirect' => $thanks_link, 'success' => 2);
        die(json_encode($response));
    }

    add_action('wp_ajax_payscript_refresh_amount', 'payscript_refresh_amount');
    add_action('wp_ajax_nopriv_payscript_refresh_amount', 'payscript_refresh_amount');

    //function to refresh amount
    function payscript_refresh_amount()
    {
        $cryptostring = isset($_POST['cryptostring']) ? rtrim(sanitize_text_field($_POST['cryptostring'])) : ' ';
        $cryptoamount = isset($_POST['cryptoamount']) ? rtrim(sanitize_text_field($_POST['cryptoamount'])) : ' ';
        $order_id     = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : false;
		$nonce 		  = isset($_POST['authNonce']) ? wc_clean(wp_unslash($_POST['authNonce'])) : '';
		
		if (!wp_verify_nonce($nonce, 'payscript-authorize-nonce')) {
            die('Unauthorized');
        }
		
        $cryptostring_array = explode(',', rtrim($cryptostring, ","));
        $cryptoamount_array = explode(',', rtrim($cryptoamount, ","));
        $order = wc_get_order($order_id);
		
        $paymentGatewayInfo = verify_payscript_api();
        $endpoint_url       = $paymentGatewayInfo['api_endpoint_url'] . '/wallet';

        $args = array(
            'method'    => 'GET',
            'headers'   => array(
                'content-type'      => 'application/json',
                'PS-API-KEY'        => $paymentGatewayInfo['api_public_key'],
                'PS-API-SIGNATURE'  => $paymentGatewayInfo['get_signature']['signature'],
                'PS-API-TIMESTAMP'  => $paymentGatewayInfo['get_signature']['time'],
            ),
            'sslverify' => false,
        );

        $response   = wp_remote_get($endpoint_url, $args);
        $data       = json_decode(wp_remote_retrieve_body($response));

        $response = array();

        if (isset($data->data) && count($data->data) > 0) {
            foreach ($data->data as $list) {
                if ($list->status == "ENABLED") {
                    $get_exchange_rate = get_exchange_rate($list->currencySymbol);
                    if (!isset($get_exchange_rate['rate']->error)) {
                        $btc_rate = $get_exchange_rate['rate']->price;
                        $calculate_btc_amount = number_format((1 / $btc_rate) * $order->get_total(), $get_exchange_rate['precision'], '.', ',');
                        $response[] = array('symbol' => strtolower($list->currencySymbol), 'amount' => $calculate_btc_amount);
                    }
                }
            }
        }
        die(json_encode($response));
    }

    add_action('wp_ajax_payscript_get_transaction_status', 'payscript_get_transaction_status');
    add_action('wp_ajax_nopriv_payscript_get_transaction_status', 'payscript_get_transaction_status');

    /**
     * Function get transaction status payscript.
     *
     * @return void
     */
    function payscript_get_transaction_status()
    {
		$nonce 			= isset($_POST['authNonce']) ? wc_clean(wp_unslash($_POST['authNonce'])) : '';
		$transaction_id = isset($_POST['transaction_id']) ? sanitize_text_field($_POST['transaction_id']) : null;
        $order_id       = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : false;

        $order = wc_get_order($order_id);

        if (!wp_verify_nonce($nonce, 'payscript-authorize-nonce')) {
            die('Unauthorized');
        }
		
        $paymentGatewayInfo = verify_payscript_api($order);
        $endpoint_url       = $paymentGatewayInfo['api_endpoint_url'] . '/transaction/' . $transaction_id;

        $args = array(
            'method'    => 'GET',
            'headers'   => array(
                'content-type'      => 'application/json',
                'PS-API-KEY'        => $paymentGatewayInfo['api_public_key'],
                'PS-API-SIGNATURE'  => $paymentGatewayInfo['get_signature']['signature'],
                'PS-API-TIMESTAMP'  => $paymentGatewayInfo['get_signature']['time'],
            ),
            'sslverify' => false,
        );

        $response   = wp_remote_get($endpoint_url, $args);
        $data       = json_decode(wp_remote_retrieve_body($response));

        $new_order_statuses = $paymentGatewayInfo['new_order_status'];
        $thanks_link        = $paymentGatewayInfo['thanks_link'];

        if (isset($data->data->status)) {
            if ($data->data->status != "INITIATED") {
                if ($data->data->status == "RECEIVED") {
                    $order->update_status($new_order_statuses['received']);
                    $order->save();
                    $response = array('message' => 'Proceed', 'redirect' => $thanks_link, 'status' => $data->data->status, 'success' => 4, 'detail' => $data->data);
                } else if ($data->data->status == "SENT") {
                    $order->update_status($new_order_statuses['sent']);
                    $order->save();
                    $response = array('message' => 'Proceed', 'redirect' => $thanks_link, 'status' => $data->data->status, 'success' => 2, 'detail' => $data->data);
                } else if ($data->data->status == "CONFIRMED") {
                    $order->update_status($new_order_statuses['confirmed']);
                    $order->save();
                    $response = array('message' => 'Proceed', 'redirect' => $thanks_link, 'status' => $data->data->status, 'success' => 2, 'detail' => $data->data);
                } else if ($data->data->status == "TIMEOUT") {
                    $order->update_status($new_order_statuses['timeout']);
                    $order->save();
                    $response = array('message' => 'Proceed', 'redirect' => $thanks_link, 'status' => $data->data->status, 'success' => 3, 'detail' => $data->data);
                } else if ($data->data->status == "AMOUNT_NOT_MATCH") {
                    $order->update_status($new_order_statuses['amount_not_matched']);
                    $order->save();
                    $response = array('message' => 'Proceed', 'redirect' => $thanks_link, 'status' => $data->data->status, 'success' => 2, 'detail' => $data->data);
                } else if ($data->data->status == "ERROR") {
                    $order->update_status($new_order_statuses['error']);
                    $order->save();
                    $response = array('message' => 'Proceed', 'redirect' => $thanks_link, 'status' => $data->data->status, 'success' => 2, 'detail' => $data->data);
                } else if ($data->data->status == "FAILED") {
                    $order->update_status($new_order_statuses['failed']);
                    $order->save();
                    $response = array('message' => 'Proceed', 'redirect' => $thanks_link, 'status' => $data->data->status, 'success' => 2, 'detail' => $data->data);
                } else {
                    $response = array('message' => '', 'success' => 1, 'status' => $data->data->status, 'detail' => $data->data);
                }
            } else {
                $response = array('message' => '', 'success' => 1, 'status' => $data->data->status, 'detail' => $data->data);
            }
        } else {
            $response = array('message' => "Something went wrong!", 'success' => 0);
        }
        die(json_encode($response));
    }

}

// Activating the plugin
function woocommerce_payscript_activate()
{
    // Check for Requirements
    $failed = false;
    $plugins_url = admin_url('plugins.php');

    // Check Requirements. Activate the plugin
    $plugins = get_plugins();

    foreach ($plugins as $file => $plugin) {
        if ('Payscript WooCommerce' === $plugin['Name'] && true === is_plugin_active($file) && (0 > version_compare($plugin['Version'], PAYSCRIPT_VERSION))) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Payscript for WooCommerce requires the older version of this plugin to be deactivated. <br><a href="' . $plugins_url . '">Return to plugins screen</a>');
        }
    }

    update_option('woocommerce_payscript_version', constant("PAYSCRIPT_VERSION"));
}

//front-end styles
add_action('get_footer', 'payscript_footer_scripts');

function payscript_footer_scripts()
{
    wp_enqueue_style('payscript-style', plugin_dir_url(__FILE__) . 'assets/css/payscript-style.css', array(), PAYSCRIPT_VERSION);
    wp_enqueue_style('payscript-responsive-style', plugin_dir_url(__FILE__) . 'assets/css/theme.responsive.css', array(), PAYSCRIPT_VERSION);
    wp_enqueue_script('qrcode_js', plugin_dir_url(__FILE__) . 'assets/js/qrcode.min.js', array(), PAYSCRIPT_VERSION, true);
    wp_enqueue_script('payscript_js', plugin_dir_url(__FILE__) . 'assets/js/payscript-front.js', array(), PAYSCRIPT_VERSION);
    wp_localize_script('payscript_js', 'PayscriptAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'authNonce' => wp_create_nonce('payscript-authorize-nonce'),
            'revokeNonce' => wp_create_nonce('payscript-revoke-nonce')
        )
    );
}

add_action('get_header', 'payscript_header_scripts');

function payscript_header_scripts()
{
    wp_enqueue_script('qrcode_js', plugin_dir_url(__FILE__) . 'assets/js/qrcode.min.js', array(), '1.0.0');
}
            