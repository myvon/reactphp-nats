<?php

namespace Nats;

use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class Handshake
{
    const SERVER_INFO = "serverInfo";
    const PING = "ping";

    private string $nextEvent;
    private Connection $connection;
    private ServerInfo $serverInfo;
    private Deferred $handshakePromise;


    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->nextEvent = self::SERVER_INFO;

        $this->handshakePromise = new Deferred();
    }

    public function getServerInfo(): ServerInfo
    {
        return $this->serverInfo;
    }

    public function doHandshake(): PromiseInterface
    {
        return $this->handshakePromise->promise();
    }

    public function handle($payload)
    {
        if($this->nextEvent === self::SERVER_INFO) {
            $this->processServerInfo($payload);
        } elseif($this->nextEvent === self::PING) {
            if($payload === "PONG") {
                $this->nextEvent = "none";
                $this->handshakePromise->resolve(null);
            } else {
                $this->handshakePromise->reject(new \Exception($payload));
            }
        }
    }

    protected function processServerInfo($response)
    {
        $this->serverInfo = new ServerInfo($response);
        if ($this->serverInfo->isTLSRequired()) {
            set_error_handler(
                function ($errno, $errstr, $errfile, $errline) {
                    restore_error_handler();
                    throw Exception::forFailedConnection($errstr);
                });

            if (!stream_socket_enable_crypto(
                $this->connection->getStreamSocket(), true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw Exception::forFailedConnection('Error negotiating crypto');
            }

            restore_error_handler();
        }

        $this->nextEvent = self::PING;
        $msg = 'CONNECT '.$this->connection->getOptions()->__toString();
        $this->connection->send($msg);
        $this->connection->ping();
    }
}