<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_Paypal Class.
 */
class NinePayGateway extends NinePay {

	public function __construct() {
//		parent::__construct();

        $this->id                 = 'ninepay-gateway';
        $this->icon = sprintf("%s/public/images/logo.png",NINEPAY_MC_9PAY_PLUGIN_URL);
        $this->has_fields         = false;
        $this->order_button_text  = $this->get_option( 'order_button_text', 'Thanh toán' );
        $this->method_title       = __( 'Cổng thanh toán 9Pay', 'woocommerce' );
        $this->supports           = array(
            'products',
        );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title          = $this->get_option( 'title' );
        
        $this->description    = $this->get_option( 'description' );
        $this->method_description    = 'Thanh toán qua cổng 9PAY';
        $this->is_testing       = 'yes' === $this->get_option( 'is_testing', 'no' );
        $this->debug          = 'yes' === $this->get_option( 'debug', 'no' );

        $this->finish_notify_text          = $this->get_option( 'finish_notify_text' );
        $this->fullname          = $this->get_option( 'fullname' );
        $this->phone          = $this->get_option( 'phone' );

        self::$log_enabled    = $this->debug;

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_view_order', array( $this, 'thankyou_page' ), 1, 1 );
        add_action('admin_notices', array($this,'show_notify'));
    }

    /*Config connect*/
    public function init_form_fields() {
//        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-accordion', false, array('jquery') );

        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Bật/Tắt', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Bật cổng thanh toán này', 'woocommerce' ),
                'default' => 'yes'
            ),
            'is_testing' => array(
                'label' => __( 'Chạy trên hệ thống test', 'woocommerce' ),
                'title' => __( 'Chế độ Test', 'woocommerce' ),
                'type' => 'checkbox',
                'description' => __( 'Khi chọn sẽ chạy trên hệ thống test', 'woocommerce' ),
            ),
            'merchant_key' => array(
                'title' => __( 'Mã đại lý', 'woocommerce' ),
                'type' => 'text',
                'description' => __( '9Pay sẽ cung cấp thông tin này', 'woocommerce' ),
                'default' => '',
                'placeholder' => 'Thông tin định danh Đối tác',
                'desc_tip' => true,
            ),
            'merchant_secret_key' => array(
                'title' => __( 'Mã bảo mật đại lý', 'woocommerce' ),
                'type' => 'text',
                'description' => __( '9Pay sẽ cung cấp thông tin này', 'woocommerce' ),
                'default' => '',
                'placeholder' => 'Thông tin dùng để tạo chữ ký điện tử',
                'desc_tip' => true,
            ),
            'checksum_secret_key' => array(
                'title' => __( 'Mã Checksum', 'woocommerce' ),
                'type' => 'text',
                'description' => __( '9Pay sẽ cung cấp thông tin này', 'woocommerce' ),
                'default' => '',
                'placeholder' => 'Thông tin dùng để kiểm tra toàn vẹn dữ liệu',
                'desc_tip' => true,
            ),
            'ninepay_lang' => array(
                'title' => __( 'Ngôn ngữ', 'woocommerce' ),
                'type' => 'select',
                'default' => 'vi',
                'description' => __( 'Ngôn ngữ được lựa chọn sẽ là ngôn ngữ trên cổng thanh toán', 'woocommerce' ),
                'options' => array(
                    NinePayConstance::LANG_VI => 'Tiếng Việt',
                    NinePayConstance::LANG_EN => 'Tiếng Anh',
                )
            ),
            'order_button_text' => array(
                'title' => __( 'Nút checkout', 'woocommerce' ),
                'type' => 'text',
                'description' => __( 'Text hiển thị nút check out', 'woocommerce' ),
                'default' => 'Thanh toán',
                'placeholder' => '',
                'desc_tip' => true,
            ),
            'title' => array(
                'title' => __( 'Tên Cổng Thanh Toán', 'woocommerce' ),
                'type' => 'text',
                'description' => __( 'Tên cổng thanh toán mà người dùng sẽ thấy khi thanh toán', 'woocommerce' ),
                'default' => 'Cổng thanh toán điện tử 9PAY',
                'placeholder' => 'Hiển thị tên phương thức thanh toán',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __( 'Mô tả về cổng thanh toán', 'woocommerce' ),
                'type' => 'textarea',
                'description' => __( 'Mô tả người dùng sẽ thấy khi chọn phương thức thanh toán', 'woocommerce' ),
                'default' => 'Cổng thanh toán điện tử 9Pay cung cấp dịch vụ thanh toán điện tử nội địa, quốc tế, ví điện tự. Được liên kết với nhiều ngân hàng trong nước',
                'placeholder' => 'Thanh toán qua cổng thanh toán 9PAY',
                'desc_tip' => true,
            ),

            /*Payment method*/
            array(
                'title' => __( 'Phương thức thanh toán', 'woocommerce' ),
                'description' => __( 'Mặc định không tính phí người mua nếu để trống (Giá trị gửi sang 9Pay = giá bán gốc). Nếu nhập phí người mua, phí sẽ được cộng vào giá bán gốc và gửi sang 9Pay giá trị tổng (Giá trị gửi sang 9Pay = giá bán gốc + phí người mua)', 'woocommerce' ),
                'type' => 'hidden',
//                'desc_tip' => true,
            ),

            /*Wallet*/
            'ninepay_payment_method_wallet' => array(
                'desc_tip' => true,
                'class' => 'pb-0',
                'type' => 'checkbox',
                'label' => __( 'Ví điện tử 9PAY', 'woocommerce' ),
                'default' => 'no',
            ),
            'ninepay_payment_method_wallet_fee_percent' => array(
                'class' => 'ninepay-element-wallet ninepay-percent pt-0',
                'text' => 'text',
                'description' => __( '%   +', 'woocommerce' ),
                'placeholder' => 'Phí % giá bán (Ví dụ: 1.8)',
                'default' => 0,
                'custom_attributes' => array(
                    'onkeypress' => 'return nineMethodPercent(event, this)',
                )
            ),
            'ninepay_payment_method_wallet_fee_fixed' => array(
                'class' => 'ninepay-element-wallet ninepay-fixed pt-0',
                'text' => 'text',
                'placeholder' => 'Phí cố định (Ví dụ: 2000)',
                'default' => 0,
                'custom_attributes' => array(
                    'onkeypress' => 'return nineMethodPercent(event, this)',
                )
            ),

            /*ATM*/
            'ninepay_payment_method_atm' => array(
                'desc_tip' => true,
                'class' => 'pb-0',
                'type' => 'checkbox',
                'label' => __( 'Thẻ nội địa', 'woocommerce' ),
                'default' => 'no'
            ),
            'ninepay_payment_method_atm_fee_percent' => array(
                'class' => 'ninepay-element-atm ninepay-percent pt-0',
                'text' => 'text',
                'description' => __( '%   +', 'woocommerce' ),
                'placeholder' => 'Phí % giá bán (Ví dụ: 1.8)',
                'default' => 0,
                'custom_attributes' => array(
                    'onkeypress' => 'return nineMethodPercent(event, this)',
                )
            ),
            'ninepay_payment_method_atm_fee_fixed' => array(
                'class' => 'ninepay-element-atm ninepay-fixed pt-0',
                'text' => 'text',
                'placeholder' => 'Phí cố định (Ví dụ: 2000)',
                'default' => 0,
                'custom_attributes' => array(
                    'onkeypress' => 'return nineMethodPercent(event, this)',
                )
            ),

            /*Credit*/
            'ninepay_payment_method_credit' => array(
                'desc_tip' => true,
                'class' => 'pb-0',
                'type' => 'checkbox',
                'label' => __( 'Thẻ quốc tế', 'woocommerce' ),
                'default' => 'no'
            ),
            'ninepay_payment_method_credit_fee_percent' => array(
                'class' => 'ninepay-element-credit ninepay-percent pt-0',
                'text' => 'text',
                'description' => __( '%   +', 'woocommerce' ),
                'placeholder' => 'Phí % giá bán (Ví dụ: 1.8)',
                'default' => 0,
                'custom_attributes' => array(
                    'onkeypress' => 'return nineMethodPercent(event, this)',
                )
            ),
            'ninepay_payment_method_credit_fee_fixed' => array(
                'class' => 'ninepay-element-credit ninepay-fixed pt-0',
                'text' => 'text',
                'placeholder' => 'Phí cố định (Ví dụ: 2000)',
                'default' => 0,
                'custom_attributes' => array(
                    'onkeypress' => 'return nineMethodPercent(event, this)',
                )
            ),

            /*Collection*/
            'ninepay_payment_method_collection' => array(
                'desc_tip' => true,
                'class' => 'pb-0',
                'type' => 'checkbox',
                'label' => __( 'Chuyển khoản ngân hàng', 'woocommerce' ),
                'default' => 'no'
            ),
            'ninepay_payment_method_collection_fee_percent' => array(
                'class' => 'ninepay-element-collection ninepay-percent pt-0',
                'text' => 'text',
                'description' => __( '%   +', 'woocommerce' ),
                'placeholder' => 'Phí % giá bán (Ví dụ: 1.8)',
                'default' => 0,
                'custom_attributes' => array(
                    'onkeypress' => 'return nineMethodPercent(event, this)',
                )
            ),
            'ninepay_payment_method_collection_fee_fixed' => array(
                'class' => 'ninepay-element-collection ninepay-fixed pt-0',
                'text' => 'text',
                'placeholder' => 'Phí cố định (Ví dụ: 2000)',
                'default' => 0,
                'custom_attributes' => array(
                    'onkeypress' => 'return nineMethodPercent(event, this)',
                )
            ),
        );
    }

    /**
     * @param $order_id
     */
    public function thankyou_page( $order_id ) {
        global $woocommerce;

        $configFile = include('core/config.php');
        $order = new WC_Order($order_id);
        $lang = $this->get_option('ninepay_lang');

//        if($order->get_status() == 'completed' || $order->get_payment_method() != $this->id) return;
        if($order->get_payment_method() != $this->id) return;

        /*Check isset params*/
        if(empty($_GET['result']) || empty($_GET['checksum'])) {
            $mess = $this->genMess(null, $configFile, $lang);
            $this->paymentFail($lang, $mess);
            return;
        }

        $secretKeyCheckSum = $this->get_option('checksum_secret_key');
        if (is_null($_GET['result'])) {
            return;
        }
        $result = $_GET['result'];

        $hashChecksum = strtoupper(hash('sha256', $result . $secretKeyCheckSum));

        if ($hashChecksum !== $_GET['checksum']) {
            return;
        }


        // Payment info
        $arrayParams = json_decode(ninepay_url_safe_b64_decode($result), true);

        /*Check valid invoice_no*/
        if($order->get_meta('_invoice_no') != $arrayParams['invoice_no']) {
            // update status order
            if($order->get_status() != 'completed') {
                $order->update_status('failed', __( 'Giao dịch thất bại', 'woocommerce' ));
            }

            $mess = $this->genMess($arrayParams['status'], $configFile, $lang);
            $this->paymentFail($lang, $mess);

            return;
        }

        /*Check payment success*/
        if(in_array($arrayParams['status'], $configFile['status']['PAYMENT_SUCCESS'])) {
            $this->paymentSuccess($lang);
            // update status order
            if($order->get_status() != 'completed') {
                $order->update_status('processing', __( 'Đơn hàng đang xử lý', 'woocommerce' ));
            }

            if(in_array($arrayParams['card_brand'], $configFile['NOT_HAS_IPN'])) {
                $order->update_status('completed', __( 'Đã hoàn thành', 'woocommerce' ));
            }

            // Remove cart
            $woocommerce->cart->empty_cart();
            return;
        }

        /*Check payment review*/
        if($arrayParams['status'] === $configFile['PAYMENT_REVIEW']) {
            $this->paymentReview($lang);

            // update status order
            if($order->get_status() != 'completed') {
                $order->update_status('on-hold', __( 'Đơn hàng đang tạm giữ', 'woocommerce' ));
            }

            /*Add note*/
            $order->add_order_note('Ngân hàng đang kiểm tra giao dịch', 1);

            // Remove cart
            $woocommerce->cart->empty_cart();
            return;
        }

        /*Check payment failed*/
        if(in_array($arrayParams['status'], $configFile['status']['PAYMENT_FAILED'])) {
            // update status order
            if($order->get_status() != 'completed') {
                $order->update_status('failed', __( 'Giao dịch thất bại', 'woocommerce' ));
            }

            $mess = $this->genMess($arrayParams['status'], $configFile, $lang);
            $this->paymentFail($lang, $mess);

            return;
        }

        return;
    }

    /**
     * @param $lang
     * @param string $mess
     */
    private function paymentFail($lang, $mess = 'Xảy ra lỗi trong quá trình thanh toán') {
        if($lang === NinePayConstance::LANG_VI) {
            ?>
            <div id="frame-thanhtoan">
                <hr>
                <h3>Thanh toán thất bại</h3>
                <p><?php echo(esc_html($mess)) ?></p>
                <hr>
            </div>
            <?php
        } else {
            ?>
            <div id="frame-thanhtoan">
                <hr>
                <h3>Payment failed</h3>
                <p><?php echo(esc_html($mess)) ?></p>
                <hr>
            </div>
            <?php
        }
    }

    /**
     * @param $lang
     */
    private function paymentSuccess($lang)
    {
        if($lang === NinePayConstance::LANG_VI) {
            ?>
            <div id="frame-thanhtoan">
                <hr>
                <h3>Thanh toán thành công</h3>
                <p>Hệ thống đang tự động xử lý đơn hàng của bạn</p>
                <hr>
            </div>
            <?php
        }else {
            ?>
            <div id="frame-thanhtoan">
                <hr>
                <h3>Payment successful</h3>
                <p>The system is automatically processing your order</p>
                <hr>
            </div>
            <?php
        }
    }

    /**
     * @param $lang
     */
    private function paymentReview($lang)
    {
        if($lang === NinePayConstance::LANG_VI) {
            ?>
            <div id="frame-thanhtoan">
                <hr>
                <h3>Giao dịch chờ kiểm tra</h3>
                <p>Vui lòng chờ, giao dịch đang được kiểm tra</p>
                <hr>
            </div>
            <?php
        }else {
            ?>
            <div id="frame-thanhtoan">
                <hr>
                <h3>Transaction on review</h3>
                <p>Please wait, transaction is being reviewed</p>
                <hr>
            </div>
            <?php
        }
    }

    /**
     * @param $status
     * @param $configFile
     * @param $lang
     * @return string
     */
    private function genMess($status, $configFile, $lang)
    {
        if($lang === NinePayConstance::LANG_VI) {
            switch ($status) {
                case $configFile['PAYMENT_CANCEL']:
                    $mess = 'Giao dịch đã bị hủy';
                    break;

                case $configFile['PAYMENT_TIMEOUT']:
                    $mess = 'Giao dịch quá thời gian xử lý';
                    break;

                default:
                    $mess = 'Xảy ra lỗi trong quá trình thanh toán';
            }

            return $mess;
        } else {
            switch ($status) {
                case $configFile['PAYMENT_CANCEL']:
                    $mess = 'Transaction canceled';
                    break;

                case $configFile['PAYMENT_TIMEOUT']:
                    $mess = 'Payment in progress';
                    break;

                default:
                    $mess = 'An error occurred during payment';
            }

            return $mess;
        }
    }
}

