<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_Paypal Class.
 */
class NinePayPayment
{
    /**
     * @param array $config
     * @param array $order
     * @param $invoiceNo
     * @param $returnUrl
     * @param $paymentMethod
     * @return string
     */
    public function payment(array $config, array $order, $invoiceNo, $returnUrl, $paymentMethod)
    {
        $configFile = include('config.php');
        $MERCHANT_KEY = $config['merchant_key'];
        $MERCHANT_SECRET_KEY = $config['merchant_secret_key'];
        $END_POINT = $config['is_testing'] == 'yes' ? $configFile['environment']['stg_url'] : $configFile['environment']['production_url'];
        $X_FORWARD = $config['is_testing'] == 'yes' ? $configFile['environment']['x_forward_link_test'] : $configFile['environment']['x_forward_link_production'];
        $lang = $config['ninepay_lang'] ? $config['ninepay_lang'] : NinePayConstance::LANG_VI;
        $amount = $order['total'];
        $description = "Thanh toan cho don hang: orderID" . $order['id'];

        $time = time();
        $data = [
            'merchantKey' => $MERCHANT_KEY,
            'time' => $time,
            'invoice_no' => $invoiceNo,
            'amount' => $amount,
            'description' => $description,
            'back_url' => $returnUrl,
            'return_url' => $returnUrl,
            'lang' => NinePayConstance::LANG_VI,
            'currency' => $order['currency']
        ];

        /*Choice payment method*/
        if(!empty($paymentMethod)) {
            $data['method'] = $paymentMethod;
        }

        /*Language*/
        if($lang === NinePayConstance::LANG_EN) {
            $data['lang'] = NinePayConstance::LANG_EN;
            $data['description'] = "Payment for order number: orderID" . $order['id'];
        }

        $message = MessageBuilder::instance()
            ->with($time, $X_FORWARD, 'POST')
            ->withParams($data)
            ->build();
        $hmacs = new HMACSignature();
        $signature = $hmacs->sign($message, $MERCHANT_SECRET_KEY);
        $httpData = [
            'baseEncode' => base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)),
            'signature' => $signature,
        ];

        return $END_POINT . '?' . http_build_query($httpData);
    }
}