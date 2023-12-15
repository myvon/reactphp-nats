<?php

require('../vendor/autoload.php');

$loop = \React\EventLoop\Loop::get();
$connection = new \Nats\Connection(null, new \Nats\Logger\EchoLogger($loop, \Nats\Logger\EchoLogger::WITH_DEBUG), $loop);

$loop->futureTick(function() use($connection, $loop) {
    $connection->connect(null, $loop)
        ->then(function(\Nats\Connection $connection) {
            $connection->subscribe("hello", function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
                $connection->publish($message->respond(new \Nats\Message\PlainMessage(sprintf("Hello %s", $message->getBody()))));
            });

            $connection->subscribe('test', function(\Nats\Message\MessageInterface $message) {
            });
        });
});

$loop->run();