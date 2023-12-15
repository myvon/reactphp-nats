<?php

namespace Nats\Message;

interface MessageInterface
{
    public function getSid(): string;
    public function getSubject(): string;
    public function serialize(): string;
    public function getBody(): mixed;
    public function appendRaw(string $content);
    public function getType(): string;

    public function setSubject(string $subject): MessageInterface;

    public function setSid(string $sid): MessageInterface;
    public function respond(MessageInterface $message): MessageInterface;
}