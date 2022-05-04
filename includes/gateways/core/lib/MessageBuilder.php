<?php
require 'SignatureException.php';

class MessageBuilder
{
    private $method = 'GET';
    private $uri;
    private $headers;
    private $date;
    private $params;
    private $body;


    public function with($date, $uri, $method = 'GET', $headers = [])
    {
        $this->date = $date;
        $this->uri = $uri;
        $this->method = $method;
        $this->headers = $headers;
        return $this;
    }

    public function withBody($body)
    {
        if (!is_string($body)) {
            $body = json_encode($body);
        }
        $this->body = $body;

        return $this;
    }

    public function withParams(array $params = [])
    {
        $this->params = $params;

        return $this;
    }

    public function build()
    {
        try {
            $this->validate();
        } catch (SignatureException $e) {
            echo esc_html($e);
        }

        $canonicalHeaders = $this->canonicalHeaders();

        if ($this->method == 'POST' && $this->body) {
            $canonicalPayload = $this->canonicalBody();         
        } else {
            $canonicalPayload = $this->canonicalParams();         
        }
        $components = [$this->method, $this->uri, $this->date];
        if ($canonicalHeaders) {
            $components[] = $canonicalHeaders;
        }
        if ($canonicalPayload) {
            $components[] = $canonicalPayload;
        }

        return implode("\n", $components);
    }

    public static function instance()
    {
        return new MessageBuilder();
    }

    public function __toString()
    {
        return $this->build();
    }

    protected function validate()
    {
        if (empty($this->uri) || empty($this->date)) {
            throw new SignatureException('Please pass properties by with function first');
        }
    }

    protected function canonicalHeaders()
    {
        if (!empty($this->headers)) {
            ksort($this->headers);
            return http_build_query($this->headers);
        }
    }

    protected function canonicalParams()
    {
        $str = '';
        if (!empty($this->params)) {
            ksort($this->params);
            foreach ($this->params as $key => $val) {
                $str .= urlencode($key) . '=' . urlencode($val) . '&';
            }
            $str = substr($str, 0, -1);
        }

        return $str;
    }

    protected function canonicalBody()
    {
        if (!empty($this->body)) {
            return base64_encode(hash('sha256', $this->body, true));
        }
    }
}