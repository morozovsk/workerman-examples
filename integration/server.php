<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;

// массив для связи соединения пользователя и необходимого нам параметра
$users = [];

// создаём ws-сервер, к которому будут подключаться все наши пользователи
$ws_worker = new Worker("websocket://0.0.0.0:8000");
// создаём обработчик, который будет выполняться при запуске ws-сервера
$ws_worker->onWorkerStart = function() use (&$users)
{
    // создаём локальный tcp-сервер, чтобы отправлять на него сообщения из кода нашего сайта
    $inner_tcp_worker = new Worker("tcp://127.0.0.1:1234");
    // создаём обработчик сообщений, который будет срабатывать,
    // когда на локальный tcp-сокет приходит сообщение
    $inner_tcp_worker->onMessage = function($connection, $data) use (&$users) {
        $data = json_decode($data);
        // отправляем сообщение пользователю по userId
        if (isset($users[$data->user])) {
            $webconnection = $users[$data->user];
            $webconnection->send($data->message);
        }
    };
    $inner_tcp_worker->listen();
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
    // удаляем параметр при отключении пользователя
    $user = array_search($connection, $users);
    unset($users[$user]);
};

// Run worker
Worker::runAll();
