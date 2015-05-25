<?php
namespace Notifuse;

use GuzzleHttp\Client,
    GuzzleHttp\Pool,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Event\AbstractTransferEvent,
    GuzzleHttp\Subscriber\Retry\RetrySubscriber;

class Notifuse
{
  public static $VERSION = '1.0';

  public static $API_VERSION = 'v1';

  private $settings = array();

  private $messages = array();

  private $logger = null;

  private $guzzleClient;

  private $guzzleLogFile;


  public function __construct($key, $options = array()) {

    if(!$key) throw new Exception("api_key is required in notifuse options");
    
    // Setup defaults

    $this->settings['api_key']   = (string) $key;
    $this->settings['host']      = isset($options['host']) ? (string) $options['host'] : 'https://api.notifuse.com';
    $this->settings['debug']     = isset($options['debug']) ? (bool) $options['debug'] : false;
    $this->settings['ssl_check'] = isset($options['ssl_check']) ? (bool) $options['ssl_check'] : false;
    $this->settings['timeout']   = isset($options['timeout']) ? (int) $options['timeout'] : 10;
    $this->settings['max_retry'] = isset($options['max_retry']) ? (int) $options['max_retry'] : 1;
    $this->settings['max_parallel'] = isset($options['max_parallel']) ? (int) $options['max_parallel'] : 10;

    // setup retry subscriber for requests

    $retry = new RetrySubscriber([
      'filter' => function ($retries, AbstractTransferEvent $event) {

        // if no response (timeout...)
        if(!($res = $event->getResponse())) return true;
        
        // if not an acceptable code
        if((int) $event->getResponse()->getStatusCode() >= 500) return true;

        return false;
      },
      'max'    => $this->settings['max_retry'],
      'delay'  => function ($number, $event) { return rand(20,80); } // wait few ms before retry
    ]);

    $this->guzzleClient = new Client();
    $this->guzzleClient->getEmitter()->attach($retry);

    if($this->settings['debug']) $this->guzzleLogFile = fopen('notifuse_client_requests.log', 'w+');
  }



  public function setLogger($logger) {
    $this->logger = $logger;

    $this->log('Logger set');
    $this->log('Settings are: '.json_encode($this->settings));
  }




  private function log($msg) {
    if($this->logger) $this->logger->log('NotifuseClient - '.$msg);
  }




  public function addMessage($message)
  {
    $this->log('Add message to the queue: '.json_encode($message));

    $this->messages[] = $message;
  }


  // Send messages per batch of 10 in parallel
  // returns curl responses

  public function sendMessages()
  {
    if(count($this->messages) == 0) {
      $this->log('Nothing to send.');
      return array('success'=>true, 'message'=>'Nothing to send.');
    }

    // var_dump($this->messages); die();
    
    $startsAt = (int) round(microtime(true)*1000);


    // make a request for each batch of messages
    $batchCount = 0;
    $batchData = array();


    // array of requests
    $requests = array();
    $errors = [];


    $url = $this->settings['host'].'/'.self::$API_VERSION.'/messages';

    $this->log('Sending messages to '.$url.'.');

    $totalMessages = count($this->messages);

    foreach($this->messages as $i => $message)
    {
      // 10 per batch max

      if($batchCount < 10)
      {
        $batchData[] = $message;
        $batchCount++;
      }

      // if batch is full or it is the last message -> go POST

      if($batchCount == 10 || $i+1 == $totalMessages)
      {
        $this->log('Preparing batch with '.($batchCount+1).' messages.');

        // add request to the parallel batch
        $request = $this->guzzleClient->createRequest('POST', $url, [
          'headers' => array(
            'Authorization' => 'Bearer '.$this->settings['api_key']
          ),
          'json' => array('messages'=>$batchData),
          'connect_timeout' => 4, // connection to server
          'timeout' => $this->settings['timeout'], // waiting for response
          'verify' => $this->settings['ssl_check'], // ssl verification
          'debug' => $this->settings['debug'] ? $this->guzzleLogFile : false
        ]);

        $requests[] = $request;

        // reset batch
        $batchData = array();
        $batchCount = 0;
      }
    }

    $this->log('Sending '.count($requests).' batch.');


    // execute the batch of requests
    $batchResults = Pool::batch($this->guzzleClient, $requests, ['pool_size'=>$this->settings['max_parallel']]);

    $return = array();
    $totalSuccessfullyQueued = 0;

    // Retrieve all successful responses
    foreach($batchResults->getSuccessful() as $response) {
      try {
        $json = $response->json();
        if(isset($json['queued'])) $totalSuccessfullyQueued += (int) $json['queued'];
        $return[] = $json;
      } 
      catch (Exception $e) {
        $return[] = array(
          'error'=>$e->getMessage()
        );
      }
    }

    // Retrieve all failures.
    foreach($batchResults->getFailures() as $requestException) {
      $return[] = array(
        'error'=>$requestException->getMessage()
      );
    }

    // // Results is an SplObjectStorage object where each request is a key
    // foreach($batchResults as $request) 
    // {
    //   // Get the result (either a ResponseInterface or RequestException)
    //   $result = $batchResults[$request];

    //   if($result instanceof Response) 
    //   {

    //   } 
    //   else 
    //   {

    //   }
    // }


    $this->log('All batch sent.');

    // reset messages to send
    $this->messages = array();

    return array(
      'success' => $totalSuccessfullyQueued == $totalMessages ? true : false,
      'queued' => $totalSuccessfullyQueued.'/'.$totalMessages,
      'max_parallel' => $this->settings['max_parallel'],
      'send_took'=> round((int) round(microtime(true)*1000) - $startsAt, 2).'ms',
      'result_per_batch' => $return,
    );
  }

}
