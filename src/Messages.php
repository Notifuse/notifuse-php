<?php

namespace Notifuse;

class Messages
{
    protected $client;

    public function __construct(NotifuseClient $client)
    {
        $this->client = $client;
    }

    public function send(array $messages)
    {
        $json = array('messages' => $messages);
        return $this->client->makeAPICall('POST', 'messages.send', array(), $json);
    }

    public function info(string $message_id)
    {
        $query = array('message' => $message_id);
        return $this->client->makeAPICall('GET', 'messages.send', $query);
    }
}