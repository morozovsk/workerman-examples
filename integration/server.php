<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;

// create a ws-server. all your users will connect to it
$ws_worker = new Worker("websocket://0.0.0.0:8000");

// storage of user-connection link
$users = [];

$ws_worker->onConnect = function($connection) use (&$users)
{
    $connection->onWebSocketConnect = function($connection) use (&$users)
    {
        // put get-parameter into $users collection when a new user is connected
        // you can set any parameter on site page. for example client.html: ws = new WebSocket("ws://127.0.0.1:8000/?user=tester01");
        $users[$_GET['user']] = $connection;
        // or you can use another parameter for user identification, for example $_COOKIE['PHPSESSID']
    };
};

$ws_worker->onClose = function($connection) use(&$users)
{
    // unset parameter when user is disconnected
    $user = array_search($connection, $users);
    unset($users[$user]);
};

// it starts once when you start server.php:
$ws_worker->onWorkerStart = function() use (&$users)
{
    // create a local tcp-server. it will receive messages from your site code (for example from send.php)
    $inner_tcp_worker = new Worker("tcp://127.0.0.1:1234");
    // create a handler that will be called when a local tcp-socket receives a message (for example from send.php)
    $inner_tcp_worker->onMessage = function($connection, $data) use (&$users) {
        // you have to use for $data json_decode because send.php uses json_encode
        $data = json_decode($data); // but you can use another protocol for send data send.php to local tcp-server
        // send a message to the user by userId
        if (isset($users[$data->user])) {
            $webconnection = $users[$data->user];
            $webconnection->send($data->message);
        }
    };
    $inner_tcp_worker->listen();
};

// Run worker
Worker::runAll();
