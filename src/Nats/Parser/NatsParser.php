<?php

namespace Nats\Parser;

use Nats\Connection;
use Nats\Message\ArrayMessage;
use Nats\Message\MessageInterface;
use Nats\Message\PlainMessage;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class NatsParser
{
    private function isErrorResponse($response)
    {
        return substr($response, 0, 4) === '-ERR';
    }

    public function parse($line, Connection $connection): PromiseInterface
    {
        return new Promise(function(callable $resolver, callable $catcher) use($line, $connection) {
            $line = trim($line);

            if ($this->isErrorResponse($line)) {
                $catcher(new \Exception($line));
                return null;
            }

            if (strpos($line, 'PING') === 0) {
                $resolver(['type' => 'ping', 'parsed' => $line]);
                return null;
            }

            if (strpos($line, 'MSG') === 0) {
                $this->handle($line, $connection)->then(function(MessageInterface $message) use($resolver) {
                    $resolver(['type' => 'msg', 'parsed' => $message]);
                })->catch($catcher);
                return ;
            }

            $resolver(['type' => 'unknown', 'parsed' => $line]);
            return null;

        });
    }

    private function handle(string $line, Connection $connection)
    {
        return new Promise(function(callable $resolver, callable $catcher) use($line, $connection) {
            $lines = explode(PHP_EOL, $line);
            $parts = explode(' ', $lines[0]);
            $subject = null;
            $length = trim($parts[3]);
            $sid = $parts[2];

            if (count($parts) === 5) {
                $length = trim($parts[4]);
                $subject = $parts[3];
            } elseif (count($parts) === 4) {
                $length = trim($parts[3]);
                $subject = $parts[1];
            }

            $payload = $lines[1];

            $msg = new PlainMessage($payload, $subject, $sid);

            if ($length > strlen($payload)) {

                $buffer = new BufferParser($msg, $length, $this, $connection);
                $connection->setParser($buffer);

                $buffer->getPromise()->then(function(MessageInterface $message) use($resolver) {
                   $resolver($this->messageFactory($message->getSubject(), $message->getBody(), $message->getSid()));
                })->catch($catcher);

            } else {
                $resolver($this->messageFactory($msg->getSubject(), $msg->getBody(), $msg->getSid()));
            }
        });
    }

    public function messageFactory(string $subject, string $payload, string $sid): MessageInterface
    {
        $payloadParts = explode(':!:', $payload);

        if(count($payloadParts) === 1) {
            return new PlainMessage($payload, $subject, $sid);
        } else {
            return match($payloadParts[0]) {
                'array' => new ArrayMessage($payloadParts[1], $subject, $sid),
                default => new PlainMessage($payloadParts[1], $subject, $sid),
            };
        }
    }
}