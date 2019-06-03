<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use BPF\HttpClient\Psr\Request;
use BPF\HttpClient\Psr\RequestOptions;
use BPF\HttpClient\RequestClient;

final class HttpClientTest extends TestCase {

    protected static $serverPid;
    protected static $serverUrl = "http://127.0.0.1:18888/sleep";

    public static function setUpBeforeClass() {
        echo "编译测试服务器，请稍等5秒\n";
	    $command = sprintf(
            'cd %s && go build -o test_server && %s/test_server >/dev/null 2>&1 & echo $!',
            __DIR__, __DIR__
        );

        $output = array();
        exec($command, $output);
        sleep(5);
        self::$serverPid = (int) $output[0];
    }

    public static function tearDownAfterClass() {
        exec('kill ' . self::$serverPid);
	}

    public function testAsyncRequest() {
        //等待服务器准备好
        sleep(1);
        $startTime = microtime(true);
        $request_client = new RequestClient();
        $options = array(
            //RequestOptions::DEBUG  => null,
        );
        $request_uniq = $request_client->addRequest(self::$serverUrl, $options, RequestClient::MODE_ASYNC);

        // 这里使用sleep模拟业务操作 
        sleep(1);
        $ret = $request_client->getResponse($request_uniq);
        $this->assertEquals("返回值", $ret, "返回结果错误");
        $costTime = microtime(true) - $startTime;
        //如果是异步的，那么总时间应该是1秒多点
        $this->assertLessThan(1.2, $costTime, "耗费的时间为{$costTime}, 超过了1.2秒，异步测试失败");
    }

    public function testAsyncMultiRequest() {
        //等待服务器准备好
        sleep(1);
        $startTime = microtime(true);
        $request_client = new RequestClient();
        $options = array(
            //RequestOptions::DEBUG  => null,
        );
        $multiUrls = array(
            self::$serverUrl,
            self::$serverUrl,
            self::$serverUrl,
            self::$serverUrl,
            self::$serverUrl,
        );
        $request_list = array();
        foreach ($multiUrls as $url) {
            $options[RequestOptions::TIMEOUT] = 8;
            $request_uniq = $request_client->addRequest(self::$serverUrl, $options, RequestClient::MODE_ASYNC);
            $request_list[$request_uniq] = $request_uniq;
        }

        // 这里使用sleep模拟业务操作 
        sleep(1);
        
        do {
            $request_uniq = null;
            try {
                $body = $request_client->selectGetAsyncResponse($request_uniq, null);
                $this->assertEquals("返回值", $body, "返回结果错误");
            } catch (\Exception $e) {
                $this->assertTrue(false, sprintf('返回异常 request_uniq:%s, body:%s', $request_uniq, $e->getMessage()) . PHP_EOL);
            }
            if ($request_uniq && isset($request_list[$request_uniq])) {
                unset($request_list[$request_uniq]);
            }
            if (!$request_list) {
                break;
            }
        } while (true);

        $costTime = microtime(true) - $startTime;
        //如果是异步的，那么总时间应该是1秒多点
        $this->assertLessThan(1.2, $costTime, "耗费的时间为{$costTime}, 超过了1.2秒，异步测试失败");
    }
}
