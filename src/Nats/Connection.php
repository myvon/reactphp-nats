<?php
namespace Nats;

use Nats\Logger\EchoLogger;
use Nats\Message\MessageInterface;
use Nats\Parser\NatsParser;
use Psr\Log\LoggerInterface;
use RandomLib\Factory;
use RandomLib\Generator;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Stream\DuplexResourceStream;
use React\Stream\DuplexStreamInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
use React\Stream\WritableResourceStream;
use React\Stream\WritableStreamInterface;

/**
 * Connection Class.
 *
 * Handles the connection to a NATS server or cluster of servers.
 *
 * @package Nats
 */
class Connection
{
    private bool $isHandshakeDone = false;
    private ReadableStreamInterface $reader;
    private WritableStreamInterface $writer;
    private Handshake $handshake;
    private LoopInterface $loop;

    /**
     * List of available subscriptions.
     *
     * @var array list of subscriptions
     */
    private array $subscriptions = [];

    /**
     * Connection options object.
     *
     * @var ConnectionOptions|null
     */
    private ?ConnectionOptions $options = null;
    private ?LoggerInterface $logger;

    /**
     * @return ConnectionOptions|null
     */
    public function getOptions(): ?ConnectionOptions
    {
        return $this->options;
    }

    /**
     * Connection timeout
     *
     * @var float
     */
    private ?float $timeout = null;

    /**
     * Stream File Pointer.
     *
     * @var mixed Socket file pointer
     */
    private mixed $streamSocket;

    private Deferred $onConnected;

    /**
     * Generator object.
     *
     * @var Generator|Php71RandomGenerator
     */
    private $randomGenerator;

    private NatsParser $parser;


    /**
     * Set Stream Timeout.
     *
     * @param float $seconds Before timeout on stream.
     *
     * @return boolean
     */
    public function setStreamTimeout($seconds)
    {
        if ($this->isConnected() === true) {
            if (is_numeric($seconds) === true) {
                try {
                    $timeout      = number_format($seconds, 3);
                    $seconds      = floor($timeout);
                    $microseconds = (($timeout - $seconds) * 1000);
                    return stream_set_timeout($this->streamSocket, $seconds, $microseconds);
                } catch (\Exception $e) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Returns an stream socket for this connection.
     *
     * @return resource
     */
    public function getStreamSocket()
    {
        return $this->streamSocket;
    }

    /**
     * Checks if the client is connected to a server.
     *
     * @return boolean
     */
    public function isConnected()
    {
        return isset($this->streamSocket);
    }

    /**
     * Returns an stream socket to the desired server.
     *
     * @param string $address Server url string.
     * @param float  $timeout Number of seconds until the connect() system call should timeout.
     *
     * @throws \Exception Exception raised if connection fails.
     * @return resource
     */
    private function getStream($address, $timeout, $context)
    {
        $errno  = null;
        $errstr = null;

        $fp = stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($fp === false) {
            throw Exception::forStreamSocketClientError($errstr, $errno);
        }

        $timeout      = number_format($timeout, 3);
        $seconds      = floor($timeout);
        $microseconds = (($timeout - $seconds) * 1000);
        stream_set_timeout($fp, $seconds, $microseconds);

        $this->reader = new ReadableResourceStream($fp, $this->loop);
        $this->writer = new ThroughStream();

        return $fp;
    }


    /**
     * Constructor.
     *
     * @param ConnectionOptions|null $options Connection options object.
     */
    public function __construct(ConnectionOptions $options = null, ?LoggerInterface $logger = null, ?LoopInterface $loop = null)
    {
        $this->subscriptions = [];
        $this->options       = $options;
        if (version_compare(phpversion(), '7.0', '>') === true) {
            $this->randomGenerator = new Php71RandomGenerator();
        } else {
            $randomFactory         = new Factory();
            $this->randomGenerator = $randomFactory->getLowStrengthGenerator();
        }

        if ($options === null) {
            $this->options = new ConnectionOptions();
        }

        if(null === $loop) {
            $loop = Loop::get();
        }

        $this->loop = $loop;

        if(!$logger instanceof LoggerInterface) {
            $logger = new EchoLogger($this->loop, []);
        }
        $this->logger = $logger;

    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Sends data thought the stream.
     *
     * @param string $payload Message data.
     *
     * @throws \Exception Raises if fails sending data.
     * @return void
     */
    public function send(string $payload): void
    {
        //$this->stream->write($payload);
        if(!str_ends_with($payload, "\r\n")) {
            $payload = $payload."\r\n";
        }
        $this->logger->debug(sprintf("Sending : %s", $payload));
        $this->writer->write($payload);
    }

    public function getParser(): NatsParser
    {
        return $this->parser;
    }

    public function setParser(NatsParser $parser): void
    {
        $this->parser = $parser;
    }

    /**
     * Handles PING command.
     *
     * @return void
     */
    private function handlePING()
    {
        $this->send('PONG');
    }

    /**
     * Handles MSG command.
     *
     * @param string $line Message command from Nats.
     *
     * @throws             Exception If subscription not found.
     * @return             void
     * @codeCoverageIgnore
     */
    private function handleMSG(MessageInterface $message)
    {
        if (isset($this->subscriptions[$message->getSid()]) === false) {
            throw Exception::forSubscriptionNotFound($message->getSid());
        }

        $this->logger->info(sprintf("Handling message from %s (%s)", $message->getSubject(), $message->getSid()));
        $func = $this->subscriptions[$message->getSid()];
        if (is_callable($func) === true) {
            $func($message, $this);
        } else {
            throw Exception::forSubscriptionCallbackInvalid($message->getSid());
        }
    }

    /**
     * Connect to server.
     *
     * @param float $timeout Number of seconds until the connect() system call should timeout.
     *
     * @throws \Exception Exception raised if connection fails.
     * @return void
     */
    public function connect($timeout = null): PromiseInterface
    {
        if ($timeout === null) {
            $timeout = intval(ini_get('default_socket_timeout'));
        }

        $this->isHandshakeDone = false;

        $this->onConnected = new Deferred();
        $this->handshake = new Handshake($this);
        $this->parser = new NatsParser();

        $this->handshake->doHandshake()->then(function() {
            $this->isHandshakeDone = true;
            $this->logger->info("Handshake done");
            $this->onConnected->resolve($this);
        })->catch(function(\Exception $error) {
            $this->logger->error(sprintf('Handshake failed, received : %s', $error->getMessage()));
            $this->onConnected->reject($error);
        });

        $this->logger->info(sprintf("Connecting to %s", $this->options->getAddress()));
        $this->timeout      = $timeout;
        $this->streamSocket = $this->getStream(
            $this->options->getAddress(), $timeout, $this->options->getStreamContext(), );
        $this->setStreamTimeout($timeout);

        $writer = new BufferedWriter($this->loop, $this->streamSocket);

        $this->writer->on("data", function($payload) use($writer) {
            $writer->write($payload);
        });

        $this->reader->on("data", function($line) {
            $debugText = substr($line, 0, 80);
            if(strlen($line > 80)) {
                $debugText .= '...'.substr($line, -10);
            }
            $this->logger->debug(sprintf("Received : %s", $debugText));

            if ($line === false) {
                return null;
            }

            $this->parser->parse($line, $this)
                ->then(function($result) {
                    $type = $result['type'];
                    $parsed = $result['parsed'];

                    switch($type) {
                        case 'unknown':
                            if(!$this->isHandshakeDone) {
                                $this->handshake->handle($parsed);
                            }
                            break;
                        case 'msg':
                            $this->handleMSG($parsed);
                            break;
                        case 'ping':
                            $this->handlePING();
                            break;
                    }

                })->catch(function(\Exception $exception) {
                    $this->logger->error($exception->getMessage());
                });
            $info = stream_get_meta_data($this->streamSocket);
            if ($info['timed_out'] === true) {
                $this->close();
            }
        });

        return $this->onConnected->promise();
    }

    /**
     * Sends PING message.
     *
     * @return void
     */
    public function ping()
    {
        $this->send('PING');
    }

    /**
     * Request does a request and executes a callback with the response.
     *
     * @param string   $subject  Message topic.
     * @param string   $payload  Message data.
     * @param \Closure $callback Closure to be executed as callback.
     *
     */
    public function request(MessageInterface $message): PromiseInterface
    {
        return new Promise(function(callable $resolver) use($message) {
            $inbox = uniqid('_INBOX.');
            $this->subscribe(
                $inbox,
                function(MessageInterface $message) use($resolver) {
                    $this->unsubscribe($message->getSid(), 1);
                    $resolver($message, $this);
                }
            );

            $this->publish($message, $inbox);
        });
    }

    /**
     * Subscribes to an specific event given a subject.
     *
     * @param string   $subject  Message topic.
     * @param \Closure $callback Closure to be executed as callback.
     *
     * @return string
     */
    public function subscribe($subject, \Closure $callback)
    {
        $sid = $this->randomGenerator->generateString(16);
        $this->logger->info(sprintf('Subscribing to %s (%s)', $subject, $sid));
        $msg = 'SUB '.$subject.' '.$sid;
        $this->send($msg);
        $this->subscriptions[$sid] = $callback;
        return $sid;
    }

    /**
     * Subscribes to an specific event given a subject and a queue.
     *
     * @param string   $subject  Message topic.
     * @param string   $queue    Queue name.
     * @param \Closure $callback Closure to be executed as callback.
     *
     * @return string
     */
    public function queueSubscribe($subject, $queue, \Closure $callback)
    {
        $sid = $this->randomGenerator->generateString(16);
        $this->logger->info(sprintf('Subscribing to %s in queue %s (%s)', $subject, $queue, $sid));
        $msg = 'SUB '.$subject.' '.$queue.' '.$sid;
        $this->send($msg);
        $this->subscriptions[$sid] = $callback;
        return $sid;
    }

    /**
     * Unsubscribe from a event given a subject.
     *
     * @param string  $sid      Subscription ID.
     * @param integer $quantity Quantity of messages.
     *
     * @return void
     */
    public function unsubscribe($sid, $quantity = null)
    {
        $this->logger->info(sprintf('Unsubscribing from %s', $sid));
        $msg = 'UNSUB '.$sid;
        if ($quantity !== null) {
            $msg = $msg.' '.$quantity;
        }

        $this->send($msg);
        if ($quantity === null) {
            unset($this->subscriptions[$sid]);
        }
    }

    /**
     * Publish publishes the data argument to the given subject.
     *
     * @param string $subject Message topic.
     * @param string $payload Message data.
     * @param string $inbox   Message inbox.
     *
     * @throws Exception If subscription not found.
     * @return void
     *
     */
    public function publish(MessageInterface $message, $inbox = null)
    {
        $this->logger->info(sprintf('Publishing to %s', $message->getSubject()));
        $msg = 'PUB '.$message->getSubject();
        if ($inbox !== null) {
            $msg = $msg.' '.$inbox;
        }

        $payload = sprintf('%s:!:%s', $message->getType(), $message->serialize());

        $msg = $msg.' '.strlen($payload);
        $this->send($msg."\r\n".$payload);
    }

    /**
     * Reconnects to the server.
     *
     * @return void
     */
    public function reconnect()
    {
        $this->logger->warning('Reconnecting');
        $this->close();
        $this->connect($this->timeout);
    }

    /**
     * Close will close the connection to the server.
     *
     * @return void
     */
    public function close()
    {
        $this->logger->info('Closing');
        if ($this->streamSocket === null) {
            return;
        }

        $this->reader->close();
        $this->writer->close();
        unset($this->buffer);

        $this->streamSocket = null;
    }
}
