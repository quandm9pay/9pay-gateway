<?php

class HMACSignature
{
    public function sign($message, $key)
    {
        $signature = hash_hmac('sha256', $message, $key, true);
        return base64_encode($signature);
    }

    public function verify($signature, $message, $key)
    {
        $valid_signature = $this->sign($message, $key);
        return !strcmp($valid_signature, $signature);
    }

}