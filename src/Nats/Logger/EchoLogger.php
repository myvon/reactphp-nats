<?php

namespace Nats\Logger;

use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class EchoLogger implements LoggerInterface
{

    private ?LoopInterface $loop;
    const DEFAULT_LEVEL = ['alert', 'emergency', 'critical', 'info', 'warning', 'error'];
    const WITH_DEBUG = ['alert', 'emergency', 'critical', 'info', 'warning', 'error', 'debug'];

    /**
     * @var array|string[]
     */
    private array $displayLevels;

    public function __construct(?LoopInterface $loop = null, array $displayLevels = self::DEFAULT_LEVEL)
    {
        if(null === $loop) {
            $loop = Loop::get();
        }
        $this->loop = $loop;
        $this->displayLevels = $displayLevels;
    }
    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {

        if(!in_array($level, $this->displayLevels)) {
            return ;
        }

        $message = trim($this->interpolate($message, $context));
        $this->loop->futureTick(function() use($level, $message) {
            echo sprintf("[%s][%s] %s", $level, date('H:i:s'), $message).PHP_EOL;
            flush();
        });
    }

    function interpolate($message, array $context = array())
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            // check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}