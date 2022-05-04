<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class NinePayIPN
 */
class NinePayIPN {

    /**
     * @param array $data
     */
    public function handleIPN(array $data)
    {
        $configFile = include('core/config.php');
        $payments = new WC_Payment_Gateways();
        $ninePayGateWay = $payments->payment_gateways()['ninepay-gateway'];
        $secretKeyCheckSum = $ninePayGateWay->settings['checksum_secret_key'];

        $hashChecksum = strtoupper(hash('sha256', $data['result'] . $secretKeyCheckSum));

        if ($hashChecksum !== $data['checksum']) {
            return;
        }

        // Payment info
        $arrayParams = json_decode(urlsafeB64Decode($data['result']), true);

        $str = $arrayParams['description'];
        $findStr = 'orderID';
        $orderPos = strpos($str, $findStr);
        $orderID = substr($str, $orderPos + strlen($findStr));

        /*Get Order*/
        $order = new WC_Order($orderID);

        /*Check valid invoice_no*/
        if($order->get_meta('_invoice_no') != $arrayParams['invoice_no']) {
            return;
        }

        /*Update status order*/
        if(in_array($arrayParams['status'], $configFile['status']['PAYMENT_SUCCESS'])) {
            $order->update_status('completed', __( 'Đã hoàn thành', 'woocommerce' ));
            return;
        }

        if(in_array($arrayParams['status'], $configFile['status']['PAYMENT_FAILED'])) {
            $order->update_status('failed', __( 'Giao dịch thất bại', 'woocommerce' ));
            return;
        }

        return;
    }
}