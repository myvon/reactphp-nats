<?php

require('../vendor/autoload.php');

$loop = \React\EventLoop\Loop::get();
$connection = new \Nats\Connection(null, new \Nats\Logger\EchoLogger($loop, array_merge(\Nats\Logger\EchoLogger::WITH_DEBUG, [''])), $loop);

$loop->futureTick(function() use($connection, $loop) {
    $connection->connect(null)
        ->then(function(\Nats\Connection $connection) use($loop) {

            $loop->addPeriodicTimer(1, function() use($connection) {
                $connection->request(new \Nats\Message\PlainMessage(mt_rand(0,100), "hello"))->then(function(\Nats\Message\MessageInterface $message) {
                   var_dump($message->getBody());
                });
            });
        });

});

$loop->run();