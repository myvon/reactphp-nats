<?php

namespace Nats;

use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Stream\WritableStreamInterface;

class BufferedWriter
{

    private LoopInterface $loop;
    private mixed $stream;

    private array $buffer = [];

    public function __construct(LoopInterface $loop, mixed $stream)
    {
        $this->loop = $loop;
        $this->stream = $stream;
        $this->nextTick();
    }

    private function nextTick()
    {
        $this->loop->futureTick(function() {
            if(empty($this->buffer)) {
                $this->nextTick();
                return ;
            }

            $message = array_shift($this->buffer);
            $this->doWrite($message)->then(function() {
                $this->nextTick();
            });
        });
    }

    private function doWrite(string $payload)
    {
        return new Promise(function(callable $resolver, callable $catcher) use($payload) {
            $len = strlen($payload);
            $written = @fwrite($this->stream, $payload);
            if ($written === false) {
                throw new \Exception('Error sending data');
            }

            if ($written === 0) {
                throw new \Exception('Broken pipe or closed connection');
            }

            $len = ($len - $written);
            if ($len > 0) {
                $this->loop->futureTick(function() use($payload, $len, $resolver) {
                    $this->doWrite(substr($payload, (0 - $len)))->then($resolver);
                });
            } else {
                $resolver(null);
            }
        });
    }

    public function write(string $payload)
    {
        $this->buffer[] = $payload;
    }
}