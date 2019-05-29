<?php
namespace BPF\HttpClient\AsyncHttp;

/**
 * HttpRequestMessage
 * Http Request报文类
 */
class HttpRequestMessage
{
    
    const EOL = "\r\n";
    
    private $method = 'GET';
    private $http_version = 'HTTP/1.1';
    private $scheme;
    private $path;
    private $url;
    private $headers;
    private $post_data;
    private $host;
    private $ip;
    private $port;
    
    public function __construct($url, $method = 'GET')
    {
        $this->url = $url;
        $this->method = strtoupper($method);
        
        $this->parseUrl();
        
        $this->setDefaultHeader();
    }
    
    protected function parseUrl()
    {
        $url_info = parse_url($this->url);
        
        $this->scheme = $url_info['scheme'];
        $this->host   = $url_info['host'];
        $this->port   = isset($url_info['port']) ? $url_info['port'] : 80;
        $this->ip     = gethostbyname($this->host);
        $this->path   = $url_info['path'];
        if ($url_info['query']) {
            $this->path .= '?' . $url_info['query'];
        }
    }
    
    protected function setDefaultHeader()
    {
        $this->headers['Host'] = $this->host;
        $this->headers['Accept'] = '*/*';
        $this->headers['Accept-Language'] = 'zh-Hans;q=1, en-US;q=0.9';
        $this->headers['Accept-Encoding'] = 'gzip, deflate';
        $this->headers['Connection'] = 'keep-alive';
        $this->headers['User-Agent'] = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
    }
    
    public function setProxy($ip, $port)
    {
        if ($ip != $this->ip || $port != $this->port) {
            $this->ip = $ip;
            $this->port = $port;
            $this->headers['Proxy-Connection'] = 'keep-alive';
            $this->path = $this->scheme . '://' . $this->host . $this->path;
        }
    }

    public function setCookie($cookie)
    {
        $this->headers['Cookie'] = $cookie;
    }
    
    public function setHeaders($headers)
    {
        foreach ($headers as $key => $val) {
            $this->headers[$key] = $val;
        }
    }

    public function setPostDataFormat($post_format)
    {
        $this->post_format = $post_format;
    }
    
    public function setPostData($post_data)
    {
        if ($this->post_format == 'json') {
            $this->post_data = json_encode($post_data);
            if (!isset($this->headers['Content-Type'])) {
                $this->headers['Content-Type'] = 'application/json;charset=UTF-8';
            }
        } else {
            $this->post_data = http_build_query($post_data);
            if (!isset($this->headers['Content-Type'])) {
                $this->headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
            }
        }
        $this->method = 'POST';
        $this->headers['Content-Length'] = strlen($this->post_data);
    }
    
    public function getIp()
    {
        return $this->ip;
    }
    
    public function getPort()
    {
        return $this->port;
    }
    
    public function getToString()
    {
        $message = sprintf('%s %s %s', $this->method, $this->path, $this->http_version) . self::EOL;
        foreach ($this->headers as $key => $val) {
            $message .= sprintf('%s: %s', $key, $val) . self::EOL;
        }
        
        $message .= self::EOL;
        
        if ($this->method == 'POST') {
            $message .= $this->post_data;
        }
        return $message;
    }
}
