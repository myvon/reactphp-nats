<?php

namespace Nats\Parser;

use Nats\Connection;
use Nats\Exception;
use Nats\Message\MessageInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class BufferParser extends NatsParser
{
    private MessageInterface $message;
    private Connection $connection;
    private int $length;
    private NatsParser $parser;

    private Deferred $promise;
    public function __construct(MessageInterface $message, int $length, NatsParser $parser, Connection $connection)
    {
        $this->message = $message;
        $this->connection = $connection;
        $this->length = $length;
        $this->parser = $parser;
        $this->promise = new Deferred();
    }

    public function getPromise(): PromiseInterface
    {
        return $this->promise->promise();
    }

    public function parse($line, Connection $connection): PromiseInterface
    {
        return new Promise(function(callable $resolver) use($line, $connection) {

            $this->message->appendRaw(trim($line));

            if ($this->message->getLength() === $this->length) {
                $this->connection->setParser($this->parser);
                $this->promise->resolve($this->message);
            }

            if($this->message->getLength() > $this->length) {
                $this->connection->setParser($this->parser);
                $this->promise->reject(new Exception(sprintf('Expected message length %s, got %s', $this->length, $this->message->getLength())));
            }

            $resolver(['type' => '', 'parsed' => '']);
        });
    }
}