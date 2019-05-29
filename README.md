# PHP异步http/https客户端
大部分php-fpm都跑在单进程单线程模式下，常规的curl和curl_multi都是阻塞调用。在复杂的业务场景下，比如需要请求外部多个接口，会导致程序性能下降。

babytree-com/httpclient是宝宝树在高并发场景下积累的php http客户端。支持异步访问外部http接口。

## 安装
```sh
composer install babytree-com/httpclient 1.0.0
```

## 使用方法
### 业务逻辑和http请求异步
```php
use BPF\HttpClient\Psr\RequestOptions;
use BPF\HttpClient\RequestClient;

$options = array(
    //RequestOptions::DEBUG  => null,
);
$request_uniq = $request_client->addRequest(self::$serverUrl, $options, RequestClient::MODE_ASYNC);

// 这里使用sleep模拟可以和请求异步的业务操作
sleep(1);

$ret = $request_client->getResponse($request_uniq);

// 对请求结果进行业务操作
// ...
```
上例中，业务操作(sleep)和http请求是异步的，与简单的curl相比，运行时间变快了。

### 多个请求异步
```php
TODO:
```

