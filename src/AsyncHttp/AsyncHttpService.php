<?php
namespace BPF\HttpClient\AsyncHttp;

/**
 * AsyncHttpService
 * Http异步调用服务类
 */
class AsyncHttpService
{
    
    const EINTR       = 4;
    const EAGAIN      = 11;
    const EINPROGRESS = 115;
    const TIMEOUT     = 8888;

    const CONNECT = 'connect';
    const SELECT  = 'connect_select';
    const WRITE   = 'write';
    const READ    = 'read';

    private $socket = null;

    private $url = '';

    private $http_request_message = null;

    private $send_time_out = array('sec' => 3, 'usec' => 0);
    private $recv_time_out = array('sec' => 3, 'usec' => 0);

    private $timeout = 3;
    //剩余超时间
    private $surplus_timeout;

    private $async_request_ret = false;

    private $times = array(
            self::CONNECT => 0,
            self::SELECT   => 0,
            self::WRITE    => 0,
            self::READ     => 0
            );

    public function __construct($url, $timeout = 0)
    {
        $this->url = $url;

        if ($timeout > 0) {
            $this->timeout = $timeout;
        }
        $this->surplus_timeout = $this->timeout;
    }

    /**
     * setTimeOut
     * 设置超时时间
     * @param mixed $timeout
     * @access public
     * @return void
     */
    public function setTimeOut($timeout)
    {
        $this->timeout = $timeout;
        $this->surplus_timeout = $this->timeout;
    }

    /**
     * setSocketTimeOut
     * 设置超时间
     * @access protected
     * @return void
     */
    protected function setSocketTimeOut($socket, $type)
    {
        $timeout = $this->getSocketTimeOut();
        if ($timeout['sec'] <= 0 && $timeout['usec'] <= 0) {
            $timeout['sec'] = 1;
        }
        socket_set_option($socket, SOL_SOCKET, $type, $timeout);
    }

    /**
     * getSocketTimeOut
     * 获取超时间
     * @access protected
     * @return void
     */
    protected function getSocketTimeOut()
    {
        $sec = (int) $this->surplus_timeout;
        $usec = ((int)($this->surplus_timeout * 1000000)) % 1000000;
        $timeout = array('sec' => $sec, 'usec' => $usec);
        return $timeout;
    }

    /**
     * recordTimes
     *
     * @param mixed $key
     * @access protected
     * @return void
     */
    protected function recordTimes($key)
    {
        static $stat_array = array();

        $now_time_line = microtime(true);
        if (!isset($stat_array[$key])) {
            $stat_array[$key] = $now_time_line;
            return ;
        }

        $run_setime = round($now_time_line - $stat_array[$key], 3);

        unset($stat_array[$key]);

        $this->times[$key] += $run_setime;

        $this->surplus_timeout -= $run_setime;
    }
    
    /**
     * getAsync
     *
     * @param mixed $url
     * @param array $options
     * @access public
     * @return void
     */
    public function getAsync(array $options = array())
    {
        try {
            $this->http_request_message = $this->createHttpRequestMessage($this->url, $options);

            $ip              = $this->http_request_message->getIp();
            $port            = $this->http_request_message->getPort();
            $request_message = $this->http_request_message->getToString();

            $this->socket = $this->connectSocket($ip, $port);
            $this->sendRequestMessage($this->socket, $request_message);
            $this->async_request_ret = true;
            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * getAyncRequestResult
     *
     * @access public
     * @return void
     */
    public function getAyncRequestResult(&$http_code)
    {
        if (!is_resource($this->socket) || !$this->async_request_ret) {
            return false;
        }
        try {
            $http_response_message = $this->recvResponseMessage($this->socket);
            $http_code = $http_response_message->getHttpCode();
            return $http_response_message->gzDecode();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * createHttpRequestMessage
     * 创建http request 报文
     * @param mixed $url
     * @param mixed $options
     * @static
     * @access public
     * @return void
     */
    public function createHttpRequestMessage($url, $options)
    {
        $http_request_message = new HttpRequestMessage($url);
        
        if (isset($options['proxy'])) {
            $http_request_message->setProxy($options['proxy']['ip'], $options['proxy']['port']);
        }
        
        if (isset($options['cookie'])) {
            $http_request_message->setCookie($options['cookie']);
        }
        
        if (isset($options['headers'])) {
            $http_request_message->setHeaders($options['headers']);
        }

        if (isset($options['post_format'])) {
            $http_request_message->setPostDataFormat($options['post_format']);
        }

        if (isset($options['post_data'])) {
            $http_request_message->setPostData($options['post_data']);
        }
        return $http_request_message;
    }

    /**
     * connectSocket
     * 创建socket 连接 设置非阻塞
     * @param mixed $ip
     * @param mixed $port
     * @param int $send_out_time
     * @param int $recv_out_time
     * @access protected
     * @return void
     */
    protected function connectSocket($ip, $port)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!is_resource($socket)) {
            throw new \Exception('create socket fail');
        }

        $this->setSocketTimeOut($socket, SO_SNDTIMEO);
        $this->recordTimes(self::CONNECT);
        $connect_ret  = socket_connect($socket, $ip, $port);
        $this->recordTimes(self::CONNECT);
        if (!$connect_ret) {
            $error_msg = $this->getExceptionMessage($socket, $error_code);
            switch ($error_code) {
                case self::EINTR:
                case self::EINPROGRESS:
                    $readfs  = array($socket);
                    $writefs = array($socket);
                    $excepts = null;
                    $this->recordTimes(self::SELECT);
                    $timeout = $this->getSocketTimeOut();
                    $rt = socket_select($readfs, $writefs, $excepts, $timeout['sec'], $timeout['usec']);
                    $this->recordTimes(self::SELECT);
                    if ($rt === false) {
                        $error_msg = $this->getExceptionMessage(null, $error_code);
                    } elseif ($rt > 0) {
                        break;
                    } else {
                        $error_code = self::TIMEOUT;
                        $error_msg = 'connect time out';
                    }
                default:
                    $exception = new \Exception($error_msg, $error_code);
                    throw $exception;
            }
        }

        //不阻塞
        if (!socket_set_nonblock($socket)) {
            $error_msg = $this->getExceptionMessage($socket, $error_code);
            $exception = new \Exception($error_msg, $error_code);
            throw $exception;
        }

        return $socket;
    }

    /**
     * sendRequestMessage
     * 将http request报文发送到服务器
     * @param mixed $socket
     * @param mixed $request_message
     * @access protected
     * @return void
     */
    protected function sendRequestMessage($socket, $request_message)
    {
        $this->setSocketTimeOut($socket, SO_SNDTIMEO);
        $this->recordTimes(self::WRITE);
        $write_ret = socket_write($socket, $request_message, strlen($request_message));
        $this->recordTimes(self::WRITE);
        if (false === $write_ret) {
            $error_msg = $this->getExceptionMessage($socket, $error_code);
            $exception = new \Exception($error_msg, $error_code);
            throw $exception;
        }
        return true;
    }

    /**
     * recvResponseMessage
     * 接受response
     * @param mixed $socket
     * @access protected
     * @return void
     */
    protected function recvResponseMessage($socket)
    {
        socket_set_block($socket);
        $this->setSocketTimeOut($socket, SO_RCVTIMEO);
        $http_response_message = null;
        do {
            $this->recordTimes(self::READ);
            $response_message = socket_read($socket, 1048576);
            $this->recordTimes(self::READ);
            if ($response_message === false) {
                $error_msg = $this->getExceptionMessage($socket, $error_code);
                //Interrupted system call 错误 重启
                if ($error_code == self::EINTR) {
                    continue;
                }
                $exception = new \Exception($error_msg, $error_code);
                throw $exception;
            }
            if (is_null($http_response_message)) {
                $http_response_message = new HttpResponseMessage($response_message);
            } else {
                $http_response_message->appendBody($response_message);
            }
            if ($http_response_message->isFinish()) {
                break;
            }
        } while (true);
        return $http_response_message;
    }

    /**
     * getExceptionMessage
     *
     * @param mixed $socket
     * @access public
     * @return void
     */
    public function getExceptionMessage($socket, &$error_code)
    {
        if (is_resource($socket)) {
            $error_code = socket_last_error($socket);
        } else {
            $error_code = socket_last_error();
        }
        $error_msg = socket_strerror($error_code) . '(error code: '.$error_code.')';
        return $error_msg;
    }

    /**
     * __destruct
     *
     * @access public
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->socket)) {
            socket_shutdown($this->socket);
            socket_clear_error($this->socket);
            socket_close($this->socket);
        }
    }

    /**
     * recordException
     *
     * @param mixed $exception
     * @access protected
     * @return void
     */
    public function getExceptionLog($exception)
    {
        $log = $exception->__toString() . PHP_EOL . $this->http_request_message->getToString().PHP_EOL;
        $time = array();
        foreach ($this->times as $key => $value) {
            $time[] = $key . ': ' . $value;
        }
        $time[] = 'timeout: ' . $this->timeout;
        $log .= implode(', ', $time);
        return $log;
    }
}
