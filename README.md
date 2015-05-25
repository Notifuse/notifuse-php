Notifuse-php
===========

This is the official Notifuse PHP SDK.

Notifuse is a multi-channel notification platform (email, sms, push...) https://www.notifuse.com


Installation
------------
To install the SDK, you will need to be using [Composer](http://getcomposer.org/) 
in your project. 
If you aren't using Composer yet, it's really simple! Here's how to install 
composer and the Mailgun SDK.

```PHP
# Install Composer
curl -sS https://getcomposer.org/installer | php

# Add Notifuse as a dependency
php composer.phar require notifuse/notifuse-php:~1.1
``` 


Then, require Composer's autoloader in your application to automatically 
load the Notifuse SDK in your project:
```PHP
require 'vendor/autoload.php';
use Notifuse\Notifuse;
```

Usage
-----
Here's how to send a message using the SDK:

```php
# Default options
$options = array(
    host         => 'https://api.notifuse.com',
    debug        => false,
    ssl_check    => false,
    timeout      => 10,
    max_retry    => 1,
    max_parallel => 10
);

# First, instantiate the SDK with your API key. 
$notifuse = new Notifuse("my-api-key", $options);

# You can also attach a logger (i.e winston)
$notifuse->setLogger($winston);

# Add a message to send
$notifuse->addMessage(array(
  'key' => 'template_key', // template_key available in your Notifuse backend
  'contactId' => '123',
  'contactData' => array(
    'firstName' => 'John',
    'lastName' => 'Rambo',
    'age' => 26,
    'newsletter' => true,
    'signedUp' => 'date_2015-06-26' // prefix date fields value with date_
  ),
  'mailgun' => array(
    'email' => 'john@rambo.com'
  ),
  'templateData' => array(
    'key' => 'value',
    'number' => 20,
    'posts' => array()
  )
));

# Send messages (in parallel batches of 10 messages)
$results = $notifuse->sendMessages();
```

Response
--------

The results are formatted as following:

```
array(5) {
  'success' => bool(true)
  'queued' => string(3) "1/1"
  'max_parallel' => int(10)
  'send_took' => string(4) "79ms"
  'result_per_batch' => array(1) {
    [0] => array(4) {
      'code' => string(3) "200"
      'success' => bool(true)
      'queued' => array(1) {
        ...
      }
      'failed' => array(0) {
        ...
      }
    }
  }
}
```

It provides the number of successfully queued messages, and the response of each batch in case you sent loads of messages.

