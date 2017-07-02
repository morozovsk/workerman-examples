<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

// массив для связи соединения пользователя и необходимого нам параметра
$users = [];

// создаём локальный tcp-сервер, чтобы отправлять на него сообщения из кода нашего сайта
$tcp_worker = new Worker("tcp://127.0.0.1:1234");
// создаём обработчик сообщений, который будет срабатывать,
// когда на локальный tcp-сокет приходит сообщение
$tcp_worker->onMessage = function($connection, $data) use ($tcp_worker)
{
    // пересылаем сообщение во все остальные соединения - это 4 ws-сервера, код которых будет ниже
    foreach ($tcp_worker->connections as $id => $webconnection) {
        if ($connection->id != $id) {
            $webconnection->send($data);
        }
    }
};

// создаём ws-сервер, к которому будут подключаться все наши пользователи
$ws_worker = new Worker("websocket://0.0.0.0:8000");
$ws_worker->count = 4;
// создаём обработчик, который будет выполняться при запуске каждого из 4-х ws-серверов
$ws_worker->onWorkerStart = function() use (&$users)
{
    //подключаемся из каждого экземпляра ws-сервера к локальному tcp-серверу
    $connection = new AsyncTcpConnection("tcp://0.0.0.0:1234");
    $connection->onMessage = function($connection, $data) use (&$users) {
        $data = json_decode($data);
        // отправляем сообщение пользователю по userId
        if (isset($users[$data->user])) {
            $webconnection = $users[$data->user];
            $webconnection->send($data->message);
        }
    };
    $connection->connect();
};

$ws_worker->onConnect = function($connection) use (&$users)
{
    $connection->onWebSocketConnect = function($connection) use (&$users)
    {
        // при подключении нового пользователя сохраняем get-параметр, который же сами и передали со страницы сайта
        $users[$_GET['user']] = $connection;
        // вместо get-параметра можно также использовать параметр из cookie, например $_COOKIE['PHPSESSID']
    };
};

$ws_worker->onClose = function($connection) use(&$users)
{
    if(isset($users[$connection->uid]))
    {
        // удаляем параметр при отключении пользователя
        unset($users[$connection->uid]);
    }
};

// Run worker
Worker::runAll();
