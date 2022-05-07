<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_Paypal Class.
 */
class NinePay extends WC_Payment_Gateway {

    /** @var bool Whether or not logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;

    /**
     * NinePay constructor.
     */
    public function __construct() {
    }

    public function show_notify(){
        $screen = get_current_screen();
        $valid = $screen->id == 'woocommerce_page_wc-settings' && isset($_GET['section']) && $_GET['section'] ==$this->id;
        if(!$valid) return;
        ?>
        <div class="notice notice-info is-dismissible">
            <p><span class="dashicons dashicons-megaphone"></span> Cổng thanh toán <strong>9PAY</strong> cung cấp dịch vụ thanh toán điện tử nhanh chóng, tiện lợi, đa dạng. <a href="https://9pay.vn/" target="_blank">Tìm hiểu thêm</a></p>
        </div>
        <?php
    }


    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log( $message, $level = 'info' ) {
        if ( self::$log_enabled ) {
            if ( empty( self::$log ) ) {
                self::$log = wc_get_logger();
            }
            self::$log->log( $level, $message, array( 'source' => 'qrscan' ) );
        }
    }

    /**
     * Check if this gateway is enabled and available in the user's country.
     * @return bool
     */
    public function is_valid_for_use() {
        return true;
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        
    }

    function process_payment( $order_id ) {
        $order = new WC_Order( $order_id );
        if (is_null($_POST['ninepay_payment_method'])) {
            return;
        }
        $ninePayPaymentMethod = $_POST['ninepay_payment_method'];
        $paymentMethod = $ninePayPaymentMethod;
        $configFile = include('core/config.php');
        $configLang = include('core/lang.php');

        /*Check valid transaction*/
        $currency = $this->checkCurrency($order);
        $paymentConfig = $this->getSettingPayment();

        if($paymentConfig === false || !$currency) {
            // Mark as failed
            $order->update_status('on-hold', __( 'Đơn hàng tạm giữ', 'woocommerce' ));

            return array(
                'result' => 'error',
                'redirect' => $this->get_return_url()
            );
        }

        /*ReCalculator total order*/
        $fee = ninepay_add_fee($paymentMethod, $order->get_total(), $paymentConfig);
        $this->addFeeOrder($order, $fee);

        if($this->checkMinAmount($order->get_data(), $configFile, $configLang, $paymentConfig)
            || $this->checkMaxAmount($order->get_data(), $configFile, $configLang, $paymentConfig)) {
            // Mark as failed
            $order->update_status('failed', __( 'Đơn hàng tạm giữ', 'woocommerce' ));

            return array(
                'result' => 'error',
                'redirect' => $this->get_return_url()
            );
        }

        /*Invoice no*/
        $invoiceNo = time() + rand(0,999999);
        $order->update_meta_data( '_invoice_no', $invoiceNo );

        /*Return url*/
        $returnUrl = $this->get_return_url( $order );

        /*Link payment*/
        $ninePayPayment = new NinePayPayment;
        $result = $ninePayPayment->payment($paymentConfig, $order->get_data(), $invoiceNo, $returnUrl, $paymentMethod);

        // Mark as pending
        $order->update_status('pending', __( 'Đơn hàng chờ thanh toán', 'woocommerce' ));

        // Return redirect to payment page
        return array(
            'result' => 'success',
            'redirect' => $result
        );
    }


    /**
     * @param $order
     * @param $fee
     */
    private function addFeeOrder($order, $fee)
    {
        // Get the customer country code

        // Set the array for tax calculations
        $calculate_tax_for = array(
            'country' => '',
            'state' => '',
            'postcode' => '',
            'city' => ''
        );

        // Get a new instance of the WC_Order_Item_Fee Object
        $item_fee = new WC_Order_Item_Fee();

        $item_fee->set_name( "Fee payment method" ); // Generic fee name
        $item_fee->set_amount( $fee ); // Fee amount
        $item_fee->set_tax_class( '' ); // default for ''
        $item_fee->set_tax_status( 'none' ); // or 'none'
        $item_fee->set_total( $fee ); // Fee amount

        // Calculating Fee taxes
        $item_fee->calculate_taxes( $calculate_tax_for );

        // Add Fee item to the order
        $order->add_item( $item_fee );
        $order->calculate_totals();
        $order->save();
    }

    /**
     * @return array|bool
     */
    private function getSettingPayment()
    {
        $paymentConfig = $this->settings;

        if(empty($paymentConfig)) {
            return false;
        }

        if(empty($paymentConfig['merchant_key']) || empty($paymentConfig['merchant_secret_key'])) {
            return false;
        }

        return $paymentConfig;
    }

    /**
     * @param $order
     * @return bool
     */
    private function checkCurrency($order)
    {
        $configFile = include('core/config.php');

        return in_array($order->get_data()['currency'], $configFile['CURRENCY']);
//        return $order->get_data()['currency'] === 'VND';
    }

    /**
     * @param $order
     * @param $configFile
     * @param $configLang
     * @param $paymentConfig
     * @return bool
     */
    private function checkMinAmount($order, $configFile, $configLang, $paymentConfig)
    {
        if($order['currency'] == NinePayConstance::CURRENCY_VND && $order['total'] < $configFile['min_amount']) {
            $lang = $paymentConfig['ninepay_lang'];
            wc_add_notice($configLang[$lang]['message_min_value'], 'error');

            return true;
        }

        return false;
    }

    /**
     * @param $order
     * @param $configFile
     * @param $configLang
     * @param $paymentConfig
     * @return bool
     */
    private function checkMaxAmount($order, $configFile, $configLang, $paymentConfig)
    {
        if($order['currency'] == NinePayConstance::CURRENCY_VND && $order['total'] > $configFile['max_amount']) {
            $lang = $paymentConfig['ninepay_lang'];
            wc_add_notice($configLang[$lang]['message_max_value'], 'error');

            return true;
        }

        return false;
    }
}