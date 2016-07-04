<?php

namespace Notifuse;

class Contacts
{
    protected $client;

    public function __construct(NotifuseClient $client)
    {
        $this->client = $client;
    }

    public function upsert(array $contacts)
    {
        $json = array('contacts' => $contacts);
        return $this->client->makeAPICall('POST', 'contacts.upsert', array(), $json);
    }
}