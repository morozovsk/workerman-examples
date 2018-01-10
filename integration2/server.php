<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

// create a ws-server. all your users will connect to it
$ws_worker = new Worker("websocket://0.0.0.0:8000");
$ws_worker->count = 4;// it will create 4 process

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
    if(isset($users[$connection->uid]))
    {
        // unset parameter when user is disconnected
        unset($users[$connection->uid]);
    }
};

// it will start once for each of the 4 ws-servers when you start server.php:
$ws_worker->onWorkerStart = function() use (&$users)
{
    //each ws-server connects to the local tcp-server
    $connection = new AsyncTcpConnection("tcp://0.0.0.0:1234");
    $connection->onMessage = function($connection, $data) use (&$users) {
        // you have to use json_decode for $data because send.php uses json_encode
        $data = json_decode($data); // but you can use another protocol for send data send.php to local tcp-server
        // send a message to the user by userId
        if (isset($users[$data->user])) {
            $webconnection = $users[$data->user];
            $webconnection->send($data->message);
        }
    };
    $connection->connect();
};

// create a local tcp-server. it will receive messages from your site code (for example from send.php)
$tcp_worker = new Worker("tcp://127.0.0.1:1234");
// create a handler that will be called when a local tcp-socket receives a message (for example from send.php)
$tcp_worker->onMessage = function($connection, $data) use ($tcp_worker)
{
    // forward message to all other process (you have 4 ws-servers)
    foreach ($tcp_worker->connections as $id => $webconnection) {
        if ($connection->id != $id) {
            $webconnection->send($data);
        }
    }
};

// Run worker
Worker::runAll();
