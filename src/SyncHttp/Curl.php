<?php

namespace BPF\HttpClient\SyncHttp;

/**
 * Curl Curl类的封装
 *
 */
final class Curl
{

    //请求头信息
    private $headers = array(
        'Connection' => 'Keep-Alive',
        'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
    );
    private $return_headers = array();

    //模拟客服端信息
    private $user_agent = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)";

    //数据压缩格式
    private $compression = 'gzip, deflate, sdch';

    //cookie文件, 格式: "/usr/cookie.txt"
    private $cookie_file = null;

    //用户名 + 密码，格式: "user_name:password"
    private $user_pwd = null;

    //cookie字符串信息
    private $cookie_str = null;

    //请求超时时间，单位为秒(s)
    private $time_out = 3;

    //请求超时时间，单位为秒(ms)
    private $time_out_ms = 0;

    //代理服务器, 格式: "host:port"
    private $http_proxy = null;

    //代理的服务器用户和密码设置, 格式: "user_name:password"
    private $proxy_user_pwd = null;

    //curl请求返回的http code
    private $http_code = 200;

    //上传文件
    private $upload_files = array();

    //特殊配置
    private $extra_opt = array();

    // post 参数格式化 默认是 http_build_query，传入json 则改为json_encode
    private $post_data_format = null;

    /**
     * __construct
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * get 通过get方式请求数据
     *
     * @param mixed $url
     * @param array $parameters
     * @access public
     * @return void
     */
    public function get($url, array $parameters = array(), bool $with_header = false)
    {
        if (!empty($parameters)) {
            $parameter_str = http_build_query($parameters);
            if (strpos($url, '?') !== false) {
                //避免出现 "?&" 的情况
                if (substr($url, -1) !== '?') {
                    $url .= "&";
                }
                $url .= $parameter_str;
            } else {
                $url .= "?" . $parameter_str;
            }
        }

        return $this->execute($url, 'get', array(), $with_header);
    }

    /**
     * post 通过post的方式请求
     *
     * @param mixed $url
     * @param array $parameters
     * @access public
     * @return void
     */
    public function post($url, array $parameters = array(), bool $with_header = false)
    {
        return $this->execute($url, 'post', $parameters, $with_header);
    }

    /**
     * parseStr
     * 解析http_build_query生成的字符串到数组
     * @param mixed $string
     * @static
     * @access public
     * @return void
     */
    public static function parseStr($string)
    {
        parse_str($string, $params);
        if (get_magic_quotes_gpc()) {
            $params = array_map('stripslashes', $params);
        }
        return $params;
    }

    /**
     * setHttpProxy 设置代理
     *
     * @param mixed $ip
     * @access public
     * @return void
     */
    public function setHttpProxy($proxy)
    {
        $this->http_proxy = $proxy;
    }

    /**
     * setHttpProxyUserPwd 设置代理服务器的用户名和密码，格式: 'user:password'
     *
     * @param mixed $proxy_user_pwd
     * @access public
     * @return void
     */
    public function setHttpProxyUserPwd($proxy_user_pwd)
    {
        $this->proxy_user_pwd = $proxy_user_pwd;
    }

    /**
     * setCompression 设置压缩方式
     *
     * @param string $compression
     * @access public
     * @return void
     */
    public function setCompression($compression = 'gzip')
    {
        $this->compression = $compression;
    }

    /**
     * setUserAgent 设置请求是模拟浏览器类型
     *
     * @param mixed $user_agent
     * @access public
     * @return void
     */
    public function setUserAgent($user_agent)
    {
        if (!empty($user_agent)) {
            $this->user_agent = $user_agent;
        }

        return $this;
    }

    /**
     * setTimeOut 设置执行超时的时间
     *
     * @param int $time_out
     * @access public
     * @return void
     */
    public function setTimeOut($time_out = 3)
    {
        $time_out = intval($time_out);
        if ($time_out > 0) {
            $this->time_out = $time_out;
        }
    }

    /**
     * setTimeOutMS 设置执行超时的时间
     *
     * @param int $time_out_ms
     * @access public
     * @return void
     */
    public function setTimeOutMS($time_out_ms = 3000)
    {
        $time_out_ms = intval($time_out_ms);
        if ($time_out_ms > 0) {
            $this->time_out_ms = $time_out_ms;
        }
    }

    /**
     * setUserPwd 设置用户的密码信息
     *
     * @param string $user_pwd 用户密码信息
     * @access public
     * @return void
     */
    public function setUserPwd($user_pwd)
    {
        $this->user_pwd = $user_pwd;

        return $this;
    }

    /**
     * setCookies 将当前登录用户的cookie信息传入过去
     *
     * @access public
     * @return void
     */
    public function setCookies(array $cookie_list = array())
    {
        $cookie_parts = array();
        foreach ($cookie_list as $key => $val) {
            $cookie_parts[] = sprintf("%s=%s;", $key, $val);
        }
        $this->cookie_str = implode("", $cookie_parts);
    }

    /**
     * setCookieFile 设置cookie文件
     *
     * @param mixed $cookie_file
     * @access public
     * @return void
     */
    public function setCookieFile($cookie_file)
    {
        if (empty($cookie_file) || !file_exists($cookie_file)) {
            throw new \Exception("Cookie文件文件不存在！");
        }

        $this->cookie_file = $cookie_file;
    }

    /**
     * setUploadFile
     * 设置上传的文件
     * @param mixed $name 文件索引 类似post参数的name
     * @param CURLFile $upload_file  上传文件的CURLFile类 类似post参数的value
     * @access public
     * @return void
     */
    public function setUploadFile($name, \CURLFile $upload_file)
    {
        $this->upload_files[$name] = $upload_file;
    }

    /**
     * setExtraOpt
     * 设置特殊的CURL配置
     * @param mixed $name CURL设置名字 例如：CURLOPT_CUSTOMREQUEST
     * @param mixed $value CURL设置的值 例如：GET
     * @access public
     * @return void
     */
    public function setExtraOpt($name, $value)
    {
        $this->extra_opt[$name] = $value;
    }

    /**
     * setPostDataFormat 设置post数据是params的格式，默认为http_build_query
     *
     * @param string $format 如果传入json，则json_encode
     * @access public
     * @return void
     */
    public function setPostDataFormat($format = 'json')
    {
        $this->post_data_format = $format;
        if ($format == 'json') {
            $this->setHttpHeader('Content-Type', 'application/json;charset=utf-8');
        }
    }

    /**
     * getHttpCode
     * 获取curl请求返回的HTTP CODE
     * @access public
     * @return void
     */
    public function getHttpCode()
    {
        return $this->http_code;
    }

    /**
     * setHeader
     * 设置一个HTTP 头信息，这个会覆盖已设置的header和默认header
     * @param mixed $name  'Referer'
     * @param mixed $value  'http://abc.com'
     * @access public
     * @return void
     */
    public function setHttpHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * setHttpHeaders
     * 设置HTTP的头信息，这里会清除所有原来的header和默认header
     * @param mixed $headers  array('Referer'=>'http://abc.com', 'Cache-Control'=>'no-cache')
     * @access public
     * @return void
     */
    public function setHttpHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * getHttpReturnHeaders
     * 获取Curl返回设置的Header，比如设置Cookie等
     * @access public
     * @return void
     */
    public function getHttpReturnHeaders()
    {
        return $this->return_headers;
    }

    /**
     * execute 创建Curl句柄
     *
     * @param string $url
     * @param string $request_method
     * @param array $parameters
     * @access private
     * @return void
     */
    private function execute($url, $request_method = 'get', array $parameters = array(), bool $with_header = false)
    {
        $ch = curl_init($url);

        foreach ($this->headers as $name => $value) {
            $headers[] = "{$name}: $value";
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($with_header) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        } else {
            curl_setopt($ch, CURLOPT_HEADER, false);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);

        curl_setopt($ch, CURLOPT_ENCODING, $this->compression);
        if ($this->time_out_ms) {
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->time_out_ms);
        } else {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->time_out);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($this->cookie_file) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        } elseif ($this->cookie_str) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookie_str);
        }

        if ($this->user_pwd) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->user_pwd);
        }

        //开启代理服务
        if ($this->http_proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->http_proxy);
            //代理服务器用户名密码设置
            if ($this->proxy_user_pwd) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy_user_pwd);
            }
        }

        if ($request_method == 'post' || count($this->upload_files) > 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            if (count($this->upload_files) > 0) {
                $parameters = array_merge($parameters, $this->upload_files);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
            } else {
                if ($this->post_data_format == 'json') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
                } elseif (($this->post_data_format == 'string')) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters[0]);
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
                }
            }
        }

        //设置特殊配置
        foreach ($this->extra_opt as $name => $value) {
            curl_setopt($ch, $name, $value);
        }

        $curl_ret = curl_exec($ch);

        $this->http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        //处理异常情况
        if ($errno) {
            $error_message = sprintf("%s(error code: %d) url:%s", $error, $errno, $url);
            throw new \Exception($error_message, $errno);
        }

        if ($with_header) {
            return $this->getCurlContentWithHeader($curl_ret);
        } else {
            $this->return_headers = array();
            return $curl_ret;
        }
    }

    /**
     * getCurlContentWithHeader
     * 分解Http头信息和body信息
     * @param mixed $data
     * @access private
     * @return void
     */
    private function getCurlContentWithHeader($data)
    {
        $resp    = explode("\r\n\r\n", $data, 2);
        $headers = explode("\n", trim($resp[0]));
        $content = trim($resp[count($resp)-1]);

        foreach ($headers as $v) {
            $header = explode(":", $v, 2);
            if (count($header) == 2) {
                if ($header[0] == 'Set-Cookie') {
                    $this->return_headers['Set-Cookie'][] = trim($header[1]);
                } else {
                    $this->return_headers[$header[0]] = trim($header[1]);
                }
            }
        }

        return $content;
    }
}
