<?php

namespace Nats\Message;

class ArrayMessage extends PlainMessage
{
    private array $body;

    public function __construct(mixed $array,string $subject = "", string $sid = "")
    {
        parent::__construct($subject, "", $sid);
        if(!is_array($array)) {
            $array = json_decode($array, true);
        }
        $this->body = $array;
    }


    public function serialize(): string
    {
        return json_encode($this->body);
    }

    public function setBody(mixed $body)
    {
        if(!is_array($body)) {
            $body = json_decode($body, true);
        }
        $this->body = $body;
    }

    public function getType(): string
    {
        return "array";
    }
}