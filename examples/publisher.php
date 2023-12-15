<?php

require('../vendor/autoload.php');

$loop = \React\EventLoop\Loop::get();
$connection = new \Nats\Connection(null, new \Nats\Logger\EchoLogger($loop, array_merge(\Nats\Logger\EchoLogger::WITH_DEBUG, [''])), $loop);

$loop->futureTick(function() use($connection, $loop) {
    $connection->connect(null, $loop)
        ->then(function(\Nats\Connection $connection) use($loop) {
            $loop->addPeriodicTimer(5, function() use($connection) {
                $connection->publish(new \Nats\Message\PlainMessage("test", bin2hex(random_bytes(70000))));
            });
        });
});

$loop->run();