<?php

require 'vendor/autoload.php';

use Notifuse\NotifuseClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$logger = new Logger('debug');
$logger->pushHandler(new StreamHandler('debug.log', Logger::INFO));

$api_key = '';

$client = new NotifuseClient($api_key, array(
  'base_uri' => 'https://localapi.notifuse.com/v2/',
  'logger' => $logger
));

try {
  $results = $client->contacts->upsert(array(array(
    'id' => '123',
    'profile' => array(
      '$set' => array('email'=>'john@yopmail.com')
    )
  )));
  
} catch (Exception $e) {
  echo $e;
}

var_dump($results);

// todo rewrite with phpunit