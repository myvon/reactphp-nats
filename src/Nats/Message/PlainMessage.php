<?php
namespace Nats\Message;

use Nats\Connection;

/**
 * Message Class.
 *
 * @package Nats
 */
class PlainMessage implements MessageInterface
{

    /**
     * Message Subject.
     */
    protected string $subject;

    /**
     * Message Body.
     */
    private string $body;

    /**
     * Message Ssid.
     */
    protected string $sid;


    /**
     * Message constructor.
     *
     * @param string     $subject Message subject.
     * @param string     $body    Message body.
     * @param string     $sid     Message Sid.
     */
    public function __construct(string $body, string $subject = "", string $sid = "")
    {
        $this->setSubject($subject);
        $this->setBody($body);
        $this->setSid($sid);
    }


    public function respond(MessageInterface $message): MessageInterface
    {
        $message->setSid($this->sid);
        $message->setSubject($this->getSubject());

        return $message;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function appendRaw(string $content)
    {
        $this->body .= $content;
    }

    public function getType(): string
    {
        return "plain";
    }

    public function getLength()
    {
        return strlen($this->serialize());
    }

    /**
     * Set subject.
     *
     * @param string $subject Subject.
     *
     * @return $this
     */
    public function setSubject(string $subject): PlainMessage
    {
        $this->subject = $subject;

        return $this;
    }


    /**
     * Get subject.
     *
     * @return string
     */
    public function getSubject(): string
    {
        return str_replace(' ', '_', $this->subject);
    }


    /**
     * Set body.
     *
     * @param string $body Body.
     *
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }


    /**
     * Get body.
     *
     * @return string
     */
    public function serialize(): string
    {
        return $this->body;
    }


    /**
     * Set Ssid.
     *
     * @param string $sid Ssid.
     *
     * @return $this
     */
    public function setSid(string $sid): PlainMessage
    {
        $this->sid = $sid;
        return $this;
    }


    /**
     * Get Ssid.
     *
     * @return string
     */
    public function getSid(): string
    {
        return $this->sid;
    }
}
