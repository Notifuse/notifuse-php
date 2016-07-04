# PHP library for the Notifuse API

[Notifuse](https://notifuse.com) connects all your notification channels (SenGrid, Mailgun, Twilio SMS, Slack, push...) to a powerful API/platform that handles templating, contacts segmentation and smart campaigns.

We recommend you to read the [API Reference](https://notifuse.com/docs/api) to understand the behavior and results of every methods.

## Installation

This library is available as a Composer package.

```bashp
composer require notifuse/notifuse-php
```

## Usage

You need your project API key to init the Notifuse client.

```php
require 'vendor/autoload.php';

use Notifuse\NotifuseClient;

$client = new NotifuseClient($api_key, $options);
```

### Client options

| Key              | Expected value.                                       |
|------------------|-------------------------------------------------------|
| logger           | Logger compatible with LoggerInterface. Default: NULL |
| request_timeout  | Timeout for the API connection in secs. Default: 3    |
| response_timeout | Timeout for the API response in secs. Default: 3      |
| max_attempts     | Max retry attempts. Default: 3                        |
| retry_delay      | Delay between each retry attemps in ms. Default: 500  |
| proxy            | Guzzle6 proxy settings. Default: NULL                 |
| verify_ssl       | Verify SSL cert. Default: true                        |

### Upsert contacts
```php

// upsert an array of contacts

$myContact = array(
  'id' => '123',
  'profile' => array(
    '$set' => array(
      'firstName' => 'John',
      'lastName' => 'Doe',
      'email' => 'john@yopmail.com'
    )
  )
);

try {
  $results = $client->contacts->upsert(array($myContact));
} catch (Exception $e) {
  // handle exception
}

// $results example:
// array( 
//   'statusCode' => 200,
//   'success' => true,
//   'inserted' => [],
//   'updated' => ['123'],
//   'failed' => []
// )

```

### Send messages
```php

$myMessage = array(
  'notification' => 'welcome',
  'channel' => 'sendgrid-acme',
  'template' => 'v1',
  'contact' => '123',
  'contactProfile' => array(
    '$set' => array(
      'firstName' => 'John',
      'lastName' => 'Doe'
    )
  ),
  'templateData' => array(
    '_verificationToken' => 'xxx'
  ) 
);

try {
  $results = $client->messages->send(array($myMessage));
} catch (Exception $e) {
  // handle exception
}

// $results example:
// array( 
//   'statusCode' => 200,
//   'success' => true,
//   'queued' => [array(...)],
//   'failed' => []
// )

```

### Retrieve a message
```php

$myMessageId = 'xxxxxxxxxxxxxxxx';

try {
  $results = $client->messages->info($myMessageId);
} catch (Exception $e) {
  // handle exception
}

// $results is a message object defined in the API Reference
```

## Exceptions

The Notifuse client will throw exceptions for connection errors and API errors (400 Bad Request, 401 Unauthorized, 500 Internal Server Error...).

## Support

Feel free to create a new Github issue if it concerns this library, otherwise use our [contact form](https://notifuse.com/contact).

## Copyright

Copyright &copy; Notifuse, Inc. MIT License; see LICENSE for further details.
