<?php
$localsocket = 'tcp://127.0.0.1:1234';
$user = 'tester01';
$message = 'test';

// connect to a local tcp-server
$instance = stream_socket_client($localsocket);
// send message
fwrite($instance, json_encode(['user' => $user, 'message' => $message])  . "\n");
