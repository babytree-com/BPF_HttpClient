<?php
namespace BPF\HttpClient;

use BPF\HttpClient\AsyncHttp\AsyncHttpService;
use BPF\HttpClient\SyncHttp\Curl;

/**
 * HttpClient
 * 一次请求对应一个实例
 * @package
 * @version $Id$
 * @author
 */
class HttpClient
{
    
    const MODE_ASYNC = 1;
    const MODE_SYNC  = 2;

    //是否使用异步调用
    private $request_mode = false;

    private $http_url;

    private $http_code;

    private $http_service = null;

    private $sync_result = false;

    public function __construct($url, $request_mode = self::MODE_SYNC)
    {
        $this->http_url   = $url;

        $this->request_mode = $request_mode;

        switch ($this->request_mode) {
            case self::MODE_ASYNC:
                $this->http_service = new AsyncHttpService($url);
                break;
            default:
                $this->http_service = new Curl();
                break;
        }
    }

    public function getUrl()
    {
        return $this->http_url;
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
        $this->http_service->setTimeOut($timeout);
    }

    /**
     * getHttpCode
     *
     * @access public
     * @return void
     */
    public function getHttpCode()
    {
        return $this->http_code;
    }

    /**
     * startRequest
     * http get 请求
     * @param array $options index proxy(array index ip,port),post_data,post_format,headers
     * @access public
     * @return void
     */
    public function startRequest(array $options = array())
    {
        switch ($this->request_mode) {
            case self::MODE_ASYNC:
                $ret = $this->startAsyncRequest($options);
                break;
            default:
                $ret = $this->startSyncRequest($options);
                break;
        }
        return $ret;
    }

    /**
     * getRequestResult
     * http get 请求 结果
     * @access public
     * @return void
     */
    public function getRequestResult()
    {
        switch ($this->request_mode) {
            case self::MODE_ASYNC:
                $ret = $this->getAsyncRequestResult();
                break;
            default:
                $ret = $this->getSyncRequestResult();
                break;
        }
        return $ret;
    }

    /**
     * startSyncRequest
     * 同步http get 请求
     * @param mixed $options
     * @access protected
     * @return void
     */
    protected function startSyncRequest($options)
    {
        try {
            if (isset($options['headers'])) {
                foreach ($options['headers'] as $key => $value) {
                    $this->http_service->setHttpHeader($key, $value);
                }
            }
            if (isset($options['proxy'])) {
                $options['proxy'] = $options['proxy']['ip'] . ':' . $options['proxy']['port'];
                $this->http_service->setHttpProxy($options['proxy']);
            }
            if (isset($options['post_data'])) {
                if ($options['post_format']) {
                    $this->http_service->setPostDataFormat($options['post_format']);
                }
                $this->sync_result = $this->http_service->post($this->http_url, $options['post_data']);
            } else {
                $this->sync_result = $this->http_service->get($this->http_url);
            }
            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * startAsyncRequest
     * 异步http get请求
     * @param mixed $options
     * @access protected
     * @return void
     */
    protected function startAsyncRequest($options)
    {
        try {
            return $this->http_service->getAsync($options);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * getSyncRequestResult
     * 同步http get请求结果
     * @access protected
     * @return void
     */
    protected function getSyncRequestResult()
    {
        $this->http_code = $this->http_service->getHttpCode();
        return $this->sync_result;
    }

    /**
     * getAsyncRequestResult
     * 异步http get请求结果
     * @access protected
     * @return void
     */
    protected function getAsyncRequestResult()
    {
        try {
            return $this->http_service->getAyncRequestResult($this->http_code);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
