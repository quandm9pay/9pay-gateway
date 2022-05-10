<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link
 * @since             1.0.0
 * @package           9pay-payment-method
 *
 * @wordpress-plugin
 * Plugin Name:       9Pay Gateway
 * Plugin URI:
 * Description:       Tích hợp cổng thanh toán 9PAY vào phương thức thanh toán của woocomerce
 * Version:           1.0.0
 * Author:            9Pay
 * Author URI:
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       nine-pay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'NINEPAY_MC_9PAY_VERSION', '1.0.0' );
define( 'NINEPAY_MC_9PAY_PLUGIN_URL', esc_url( plugins_url( '', __FILE__ ) ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mc-quetma-activator.php
 */
if (!function_exists('activate_mc_9pay')) {
    function activate_mc_9pay() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-mc-quetma-activator.php';
        Ninepay_Activator::activate();
    }
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-mc-quetma-deactivator.php
 */
if (!function_exists('deactivate_mc_9pay')) {
    function deactivate_mc_9pay() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-mc-quetma-deactivator.php';
        Ninepay_Deactivator::deactivate();
    }
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
if (!function_exists('run_mc_9pay')) {
    function run_mc_9pay() {
        $plugin = new Mc_9pay();
        $plugin->run();
    }
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    
	register_activation_hook( __FILE__, 'activate_mc_9pay' );
	register_deactivation_hook( __FILE__, 'deactivate_mc_9pay' );
	require plugin_dir_path( __FILE__ ) . 'includes/class-mc-quetma.php';

	run_mc_9pay();
}

if (!function_exists('mc_9pay_installed_notice')) {
    function mc_9pay_installed_notice() {
        if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
            $class = 'notice notice-error';
            $message = __( 'Plugin cổng thanh toán 9PAY cần Woocommerce kích hoạt trước khi sử dụng. Vui lòng kiểm tra Woocommerce', 'qr_auto' );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }
    }
}
add_action( 'admin_notices', 'mc_9pay_installed_notice' );


/*[CORE] Handle IPN*/
add_filter('rest_url_prefix', function(){
    return 'api';
});

add_action('rest_api_init', function(){

    if (!is_string($_REQUEST['result'])) {
        return;
    }
    $result = sanitize_text_field($_REQUEST['result']);

    if (!is_string($_REQUEST['checksum'])) {
        return;
    }
    $checksum = sanitize_text_field($_REQUEST['checksum']);

    register_rest_route('nine-pay/v1', '/result-ipn', array(
        'methods' => 'POST',
        'callback' => function() use ($result, $checksum){
            $handleIPN = new NinePayIPN;
            $handleIPN->handleIPN([
                'result' => $result,
                'checksum' => $checksum
            ]);
        }
    ));
});


/**
 * [CORE]
 * @param $input
 * @return false|string
 */
if (!function_exists('ninepay_url_safe_b64_decode')) {
    function ninepay_url_safe_b64_decode($input)
    {
        $remainder = \strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= \str_repeat('=', $padlen);
        }
        return \base64_decode(\strtr($input, '-_', '+/'));
    }
}


/*[ADMIN] Handle show invoice_no*/
add_action( 'woocommerce_admin_order_data_after_billing_address', function($order){
    $invoiceNo = get_post_meta( $order->get_id(), '_invoice_no', true );

    if($invoiceNo) {
        echo '<p><strong>'.__('Mã yêu cầu').':</strong> <br/>';
        echo esc_html($invoiceNo);
        echo '</p>';
    }
}, 10, 1 );


/*[THEME] handle show payment method*/
add_filter( 'woocommerce_gateway_description', 'ninepay_gateway_custom_fields', 20, 2 );
if (!function_exists('ninepay_gateway_custom_fields')) {
    function ninepay_gateway_custom_fields( $description, $payment_id ){
        if( 'ninepay-gateway' === $payment_id ){
            $payment = new WC_Payment_Gateways();
            $settings = $payment->get_available_payment_gateways()['ninepay-gateway']->settings;
            $configLang = include('includes/gateways/core/lang.php');
            $lang = $settings['ninepay_lang'];

            $paymentMethod = ninepay_get_payment_method($settings);
            ob_start(); // Start buffering

            echo '<div  class="ninepay-gateway-fields" style="padding:10px 0; width: 100%">';

            woocommerce_form_field( 'ninepay_payment_method', array(
                'type'          => 'select',
                'label'         => __($configLang[$lang]['description_payment_method'], "woocommerce"),
                'class'         => array('form-row-wide'),
                'required'      => true,
                'options'       => $paymentMethod
            ), '');

            echo '<div>';

            $description .= ob_get_clean(); // Append buffered content
        }

        return $description;
    }
}

/**
 * @param array $settings
 * @return array
 */
if (!function_exists('ninepay_get_payment_method')) {
    function ninepay_get_payment_method(array $settings)
    {
        $configLang = include('includes/gateways/core/lang.php');
        $lang = $settings['ninepay_lang'];
        $result = [];

        /*wallet*/
        if($settings['ninepay_payment_method_wallet'] === 'yes') {
            $result[NinePayConstance::METHOD_WALLET] = __($configLang[$lang][NinePayConstance::METHOD_WALLET], "woocommerce");
        }

        /*atm*/
        if($settings['ninepay_payment_method_atm'] === 'yes') {
            $result[NinePayConstance::METHOD_ATM] = __($configLang[$lang][NinePayConstance::METHOD_ATM], "woocommerce");
        }

        /*credit*/
        if($settings['ninepay_payment_method_credit'] === 'yes') {
            $result[NinePayConstance::METHOD_CREDIT] = __($configLang[$lang][NinePayConstance::METHOD_CREDIT], "woocommerce");
        }

        /*collection*/
        if($settings['ninepay_payment_method_collection'] === 'yes') {
            $result[NinePayConstance::METHOD_COLLECTION] = __($configLang[$lang][NinePayConstance::METHOD_COLLECTION], "woocommerce");
        }

        return $result;
    }
}

if (!function_exists('ninepay_add_checkout_script')) {
    function ninepay_add_checkout_script() {
        global $woocommerce;
        $configFile = include('includes/gateways/core/config.php');
        $totalCart = $woocommerce->cart->total;
        $currency = get_option('woocommerce_currency');
        $minAmount = $configFile['min_amount_wallet_vnd'];
        ?>
        <script type="text/javascript">
            let totalCart = parseFloat("<?php echo esc_html($totalCart); ?>");
            let currency = "<?php echo esc_html($currency); ?>";
            let minAmount = parseFloat("<?php echo esc_html($minAmount); ?>");
            let paymentMethod = jQuery('#ninepay_payment_method').val();
            jQuery(document).on("updated_checkout", function(){
                jQuery('#place_order').click(function (e) {
                    if (paymentMethod === "WALLET") {
                        if (currency === "VND" && totalCart < minAmount) {
                            let error = `<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
                                    <ul class="woocommerce-error" role="alert">
			                            <li>Giá trị giao dịch không được nhỏ hơn ${minAmount} VNĐ</li>
                                    </ul>
                                </div>`;
                            jQuery('form.woocommerce-checkout').prepend(error);
                            jQuery(jQuery('form.woocommerce-checkout .woocommerce-NoticeGroup')[0]).focus();
                            jQuery('html, body').animate({ scrollTop: jQuery(jQuery('form.woocommerce-checkout .woocommerce-NoticeGroup')[0]).offset().top - 200 }, 'slow');
                            e.preventDefault();
                        }
                    }
                });
            });
        </script type="text/javascript">
        <?php
    }
}
add_action('woocommerce_after_checkout_form', 'ninepay_add_checkout_script');


/*[THEME] Handle add fee*/
add_action('woocommerce_cart_calculate_fees','ninepay_custom_handling_fee',10,1);
if (!function_exists('ninepay_custom_handling_fee')) {
    function ninepay_custom_handling_fee($cart){
        if(is_admin() && ! defined('DOING_AJAX'))
            return;

        if('ninepay-gateway' === WC()->session->get('chosen_payment_method')){
            $payment = new WC_Payment_Gateways();
            $config = $payment->get_available_payment_gateways()['ninepay-gateway']->settings;
            $configLang = include('includes/gateways/core/lang.php');
            $lang = $config['ninepay_lang'];

            if (is_null($_POST['post_data'])) {
                return;
            }
            $postData = sanitize_text_field($_POST['post_data']);

            parse_str($postData, $result);

            $totalAmount = $cart->cart_contents_total;

            $fee = ninepay_add_fee($ninepay_payment_method, $totalAmount, $config);

            if($fee != 0) {
                if($lang === NinePayConstance::LANG_VI) {
                    $cart->add_fee( __('Phí thanh toán qua '. $configLang[$lang][$ninepay_payment_method], "woocommerce"), $fee, true);
                } else {
                    $cart->add_fee( __('Payment fee via '. $configLang[$lang][$ninepay_payment_method], "woocommerce"), $fee, true);
                }
            }
        }
    }
}


/**
 * [THEME] [CORE]
 * @param $paymentMethod
 * @param $amount
 * @param $config
 * @return mixed
 */
if (!function_exists('ninepay_add_fee')) {
    function ninepay_add_fee($paymentMethod, $amount, $config)
    {
        switch ($paymentMethod) {
            case NinePayConstance::METHOD_WALLET:
                return ninepay_handle_charge($amount, $config['ninepay_payment_method_wallet_fee_percent'], $config['ninepay_payment_method_wallet_fee_fixed']);
                break;

            case NinePayConstance::METHOD_ATM:
                return ninepay_handle_charge($amount, $config['ninepay_payment_method_atm_fee_percent'], $config['ninepay_payment_method_atm_fee_fixed']);
                break;

            case NinePayConstance::METHOD_CREDIT:
                return ninepay_handle_charge($amount, $config['ninepay_payment_method_credit_fee_percent'], $config['ninepay_payment_method_credit_fee_fixed']);
                break;

            case NinePayConstance::METHOD_COLLECTION:
                return ninepay_handle_charge($amount, $config['ninepay_payment_method_collection_fee_percent'], $config['ninepay_payment_method_collection_fee_fixed']);
                break;

            default:
                return 0;
        }
    }
}

/**
 * @param $amount
 * @param $feePercent
 * @param $feeFixed
 * @return float
 */
if (!function_exists('ninepay_handle_charge')) {
    function ninepay_handle_charge($amount, $feePercent, $feeFixed)
    {
        $feePercent = empty($feePercent) || !is_numeric($feePercent) ? 0 : $feePercent;
        $feeFixed = empty($feeFixed) || !is_numeric($feeFixed) ? 0 : $feeFixed;

        $result = $feeFixed + ($feePercent * $amount/100);

        return round($result, 2);
    }
}


/*[THEME] Reload cart when choice payment gateway or payment method*/
add_action( 'wp_footer', function(){
    if(is_checkout() && ! is_wc_endpoint_url()):
        ?>
        <script type="text/javascript">
            jQuery( function($){
                let checkoutForm = $('form.checkout');

                /*Reset when choose payment gateway*/
                checkoutForm.on('change', 'input[name="payment_method"]', function(){
                    $(document.body).trigger('update_checkout');
                });

                /*Reset when choose payment method*/
                checkoutForm.on('change', 'select[name="ninepay_payment_method"]', function(){
                    $(document.body).trigger('update_checkout');
                });
            });
        </script>
    <?php
    endif;
});
