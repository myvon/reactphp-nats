myvon/reactphp-nats
=======


Introduction
------------

A PHP client for the [NATS messaging system](https://nats.io) with [ReactPHP](https://reactphp.org/). 
This library is made from [Repejota's PHPNATS](https://github.com/repejota/phpnats) with a lot of modification.

Requirements
------------

* php 8.0+
* [nats-server](https://github.com/nats-io/nats-server)
* [ReactPHP EventLoop](https://reactphp.org/event-loop/)
* [ReactPHP Stream](https://reactphp.org/stream/)
* [ReactPHP Promise](https://reactphp.org/promise/)
* [PSR3 LoggerInterface](https://packagist.org/packages/psr/log)

Usage
-----

### Installation

Work In Progress

### Basic Usage

Start by initializing the connection :
```php
$client = new \Nats\Connection();
$client->connect()->then(function(\Nats\Connection $connection) {
    // Connected and handshake is done
});
```


Subscribe to a subject :
```php
$connection->subscribe("mySubject", function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
    $content = $message->getBody();
});
```

Subscribe to a subject in queue (see [Queue Groups](https://docs.nats.io/nats-concepts/core-nats/queue) for more information):
```php
$connection->queueSubscribe("mySubject", "myQueue", function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
    $content = $message->getBody();
});
```

Publish to a subject :
```php
$connection->publish(new \Nats\Message\PlainMessage("my message", "mySubject"));
```

Make a [request](https://docs.nats.io/nats-concepts/core-nats/reqreply/reqreply_walkthrough)  :
```php
    $connection->request(new \Nats\Message\PlainMessage("my message", "mySubject"))
    ->then(function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
        $response = $message->getBody();
    });
```

Respond to a [request](https://docs.nats.io/nats-concepts/core-nats/reqreply/reqreply_walkthrough)  :
```php
    $connection->subscribe("mySubject", function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
        $connection->publish($message->respond(new \Nats\Message\PlainMessage("Hello !")));
    });


    $connection->queueSubscribe("mySubject", "myQueue", function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
        $connection->publish($message->respond(new \Nats\Message\PlainMessage("Hello !")));
    });
```

### Structured data

For now only plain text and array are supported through `\Nats\Message\PlainMessage` and `\Nats\Message\ArrayMessage`, both implementing `\Nats\Message\MessageInterface`.
Content can be accessed via the `getBody()` method.

Example: 
```php
    $connection->subscribe("mySubject", function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
        if($message instanceof \Nats\Message\ArrayMessage) {
            $array =  $message->getBody();
            $name = $array['name'];
        } else {
            $name = $message->getBody();
        }
        $connection->publish($message->respond(new \Nats\Message\PlainMessage(sprintf('Hello %s', $name))))
    });

    $connection->request(new \Nats\Message\PlainMessage("morgan", "hello.world"))
        ->then(function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
            $response = $message->getBody(); // Hello Morgan
        });

    $connection->request(new \Nats\Message\ArrayMessage(['name' => 'Morgan'], "hello.world"))
    ->then(function(\Nats\Message\MessageInterface $message, \Nats\Connection $connection) {
        $response = $message->getBody(); // Hello Morgan
    });
```

### ReactPHP Loop

By default the library retreive the ReactPHP loop by calling `Loop::get()`. You can pass the loop to utilize as third argument of the constructor off `\Nats\Connection`

```php
$loop = /* your loop */;

$client = new \Nats\Connection(null, null, $loop);
$client->connect()->then(function(\Nats\Connection $connection) {
    // Connected and handshake is done
});
```

Closing the connection with the `close()` method will also close all stream.

### Logging

You can see what's happening in the hood by passing an object implentin the psr3 `\Psr\Log\LoggerInterface` interface through 2nd arguments of the connection : 

```php
$client = new \Nats\Connection(null, new \Nats\Logger\EchoLogger());
```

The `\Nats\Logger\EchoLogger` will simply echo every log passed if the format `[level][H:i:s] log text`. 

### Encoded Connections

Work In Progress


### TODO

- Finish refactoring of the code. 
- Reduce the number of lines in the `connect()` by splitting the logic
- Make code more easily readable (by adding comments for exemple ...)
- Add unit tests !!!
- Ensure code quality

Developer's Information
-----------------------

### Releases

Work In Progress 

### Tests


Work In Progress

### Code Quality

Work In Progress

Credits
--------

- Thanks to [Raül Pérez](https://github.com/repejota) for the original [phpnats](https://github.com/repejota/phpnats) library and all of it's [contributors](https://github.com/repejota/phpnats/blob/develop/CONTRIBUTORS)

License
-------

MIT, see [LICENSE](LICENSE)
