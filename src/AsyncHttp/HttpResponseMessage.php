<?php
namespace BPF\HttpClient\AsyncHttp;

/**
 * HttpResponseMessage
 * Http Response 报文类
 */
class HttpResponseMessage
{
    const EOL = "\r\n";
    
    protected $response_message;

    protected $version;
    protected $http_code;
    protected $http_status;
    protected $header = array();
    protected $is_finish = false;
    
    protected $body = '';
    protected $trunk_length = 0;
    protected $buffer = '';

    public function __construct($response_message)
    {
        $this->response_message = $response_message;
        list($header, $boby) = explode(self::EOL . self::EOL, $this->response_message, 2);
        $this->parseHeader($header);
        $this->parseBody($boby);
    }

    public function getHttpCode()
    {
        return $this->http_code;
    }

    protected function parseHeader($header)
    {
        $header_array = explode(self::EOL, $header);
        if (count(explode(' ', $header_array[0], 3)) != 3) {
            return false;
        }
        //版本 状态码 状态
        list($this->version, $this->http_code, $this->http_status) = explode(' ', $header_array[0], 3);
        if (!preg_match('/^HTTP/', $this->version) || !preg_match('/^\d+$/', $this->http_code)) {
            $this->version = '';
            $this->http_code = '';
            $this->http_status = '';
            return false;
        }

        $len = count($header_array);
        //header信息
        for ($i = 1; $i < $len; $i++) {
            $string = trim($header_array[$i], self::EOL);
            if (empty($string)) {
                $i++;
                break;
            }
            list($key, $value) = explode(': ', $string);
            $this->header[$key] = $value;
        }
        return true;
    }

    protected function parseBody($boby)
    {
        $this->buffer .= $boby;
        //解析trunk
        if (isset($this->header['Transfer-Encoding']) and $this->header['Transfer-Encoding'] == 'chunked') {
            do {
                if ($this->trunk_length == 0) {
                    $_len = strstr($this->buffer, self::EOL, true);
                    if ($_len === false) {
                        return false;
                    }
                    $length = hexdec($_len);
                    if ($length == 0) {
                        $this->is_finish = true;
                        return true;
                    }
                    $this->trunk_length = $length;
                    $this->buffer = substr($this->buffer, strlen($_len . self::EOL));
                } else {
                    //数据量不足，需要等待数据
                    if (strlen($this->buffer) < $this->trunk_length) {
                        return true;
                    }
                    $this->body .= substr($this->buffer, 0, $this->trunk_length);
                    $this->buffer = substr($this->buffer, $this->trunk_length + strlen(self::EOL));
                    $this->trunk_length = 0;
                }
            } while (true);
            return true;
        } else {
            if (strlen($this->buffer) < $this->header['Content-Length']) {
                return true;
            } else {
                $this->body = $this->buffer;
                $this->is_finish = true;
                return true;
            }
        }
    }

    public function appendBody($body)
    {
        $this->parseBody($body);
    }
    
    public function gzDecode()
    {
        if (!isset($this->header['Content-Encoding'])) {
            return $this->body;
        }
        switch ($this->header['Content-Encoding']) {
            case 'gzip':
                return gzdecode($this->body);
            case 'deflate':
                return gzinflate($this->body);
            case 'compress':
                return gzinflate(substr($this->body, 2, -4));
        }
        return $this->body;
    }

    public function isFinish()
    {
        return $this->is_finish;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getHeaders()
    {
        return $this->header;
    }
    
    public function getResponseMessage()
    {
        return $this->response_message;
    }
}
