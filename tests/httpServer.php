<?php

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;

$loop = Factory::create();
$server = new Server(function (ServerRequestInterface $request) {
    sleep(1);
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "返回值"
    );
});
$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '127.0.0.1:18888', $loop);
$server->listen($socket);
echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
$loop->run();
