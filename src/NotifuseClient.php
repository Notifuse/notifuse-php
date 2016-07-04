<?php

/*
 * Author:   Pierre Bazoge - Notifuse Inc (https://notifuse.com)
 * License:  http://creativecommons.org/licenses/MIT/ MIT
 * Link:     https://github.com/Notifuse/notifuse-php
 */

namespace Notifuse;

use Notifuse\Contacts;
use Notifuse\Messages;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;

class NotifuseClient
{
    public static $VERSION = '2.0.1';

    protected $api_key = NULL;

    protected $base_uri = 'https://api.notifuse.com/v2/';

    protected $logger = NULL;

    protected $verify_ssl = true;

    protected $request_timeout = 3.0; // secs

    protected $response_timeout = 3.0; // secs

    protected $max_attempts = 3;

    protected $retry_delay = 500; // ms

    protected $proxy = NULL;

    protected $guzzleClient;

    public $contacts;

    public $messages;

    public function __construct($api_key, $options = array()) {

        if(!$api_key) throw new \Exception("Your project API_KEY is required!");

        $this->api_key = (string) $api_key;
        
        // overwrite default settings

        if(isset($options['base_uri'])) {
            $this->base_uri = (string) $options['base_uri'];
        }

        if(isset($options['logger']) && $options['logger'] instanceof LoggerInterface) {
            $this->logger = $options['logger'];
        }

        if(isset($options['verify_ssl'])) {
            $this->verify_ssl = (bool) $options['verify_ssl'];
        }

        if(isset($options['request_timeout'])) {
            $this->request_timeout = (float) $options['request_timeout'];
        }

        if(isset($options['response_timeout'])) {
            $this->response_timeout = (float) $options['response_timeout'];
        }

        if(isset($options['max_attempts'])) {
            $this->max_attempts = (int) $options['max_attempts'];
        }

        if(isset($options['retry_delay'])) {
            $this->retry_delay = (float) $options['retry_delay'];
        }

        if(isset($options['proxy'])) {
            $this->proxy = (string) $options['proxy'];
        }
        
        $handlerStack = HandlerStack::create(new CurlHandler());

        $retryMiddleware = \GuzzleHttp\Middleware::retry($this->retryHandler(), $this->retryDelay());

        $handlerStack->push($retryMiddleware);

        $guzzleOptions = [
            'base_uri' => $this->base_uri,
            'timeout' => $this->response_timeout,
            'connect_timeout' => $this->request_timeout,
            'headers' => [
                'User-Agent' => 'php-client v'.static::$VERSION,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$this->api_key
            ],
            'verify' => $this->verify_ssl,
            'synchronous' => true, // inform HTTP handlers that you intend on waiting on the response.
            'http_errors' => false // disable throwing exceptions on an HTTP protocol errors (i.e., 4xx and 5xx responses)
        ];

        if($this->proxy !== NULL) {
            $guzzleOptions['proxy'] = $this->proxy;
        }

        $this->guzzleClient = new Client(array_merge($guzzleOptions, array('handler'=>$handlerStack)));

        if($this->logger !== NULL) {
            $this->logger->info('Notifuse client instantiated', array(
                'api_key' => $this->api_key,
                'base_uri' => $this->base_uri,
                'verify_ssl' => $this->verify_ssl,
                'request_timeout' => $this->request_timeout, // secs
                'response_timeout' => $this->response_timeout, // secs
                'max_attempts' => $this->max_attempts,
                'retry_delay' => $this->retry_delay, // ms
                'proxy' => $this->proxy
            ));
        }

        if($this->logger !== NULL) {
            $this->logger->info('Notifuse client Guzzle options', $guzzleOptions);
        }

        $this->contacts = new Contacts($this);
        $this->messages = new Messages($this);
    }

    private function retryHandler()
    {
        $max_attempts = $this->max_attempts;
        $logger = $this->logger;

        return function (
                $retries,
                Request $request,
                Response $response = null,
                RequestException $exception = null
            ) use ($max_attempts, $logger) {

            // no retry if it's not a server error
            if ($response && $response->getStatusCode() < 500) {
                return false;
            }

            // stop retry if max attempts reached
            if ($retries+1 >= $max_attempts) {
                return false;
            }

            if($logger !== NULL) {
                $logger->warning(sprintf(
                    'Retrying %s %s %s/%s, %s',
                    $request->getMethod(),
                    $request->getUri(),
                    $retries + 1,
                    $max_attempts,
                    $response ? 'status code: ' . $response->getStatusCode() : $exception->getMessage()
                ), [$request->getHeader('Host')[0]]);
            }

            return true;
        };
    }

    private function retryDelay()
    {
        $retry_delay = $this->retry_delay;

        return function($numberOfRetries) use ($retry_delay) {
            return $retry_delay;
        };
    }

    public function makeAPICall($method, $path, array $query = array(), array $json = array())
    {
        $options = array();

        if(count($query) > 0) {
            $options['query'] = array_filter($query, function($value) {
                return $value !== null;
            });
        }

        if(count($json) > 0) {
            $options['json'] = array_filter($json, function($value) {
                return $value !== null;
            });
        }

        if($this->logger !== NULL) {
            $this->logger->info('Notifuse client API call', array(
                'method' => $method,
                'path' => $path,
                'query' => $query,
                'json' => $json
            ));
        }

        try {
            $response = $this->guzzleClient->request($method, $path, $options);
        } catch (ConnectException $e) {
            // convert guzzle exception to general exceptions
            throw new \Exception($e->getMessage());
        }
        
        try {
            $json = json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw new \Exception('Notifuse: 500 - Internal Server Error: Cannot parse response from API!');
        }

        if($response->getStatusCode() >= 400) {
            throw new \Exception('Notifuse: '.$response->getStatusCode().' - '.$json['error'].': '.$json['message']);
        }

        return $json;
    }
}
