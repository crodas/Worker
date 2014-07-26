Worker
======

Queue-agnostic way of doing asynchronous jobs in PHP.

***This is a work in process, it isn't ready for production.***

Why?
----

To provide an easy way of running things in the backend in PHP. Anything that performs IO is a potential candidate for being run in the background. Sending emails, resize a picture, get the content of a website. 

The idea behind this project is to provide a very easy interface to queue jobs/tasks. 

*All tasks are runs asynchronously by design*

How?
----

It creates a lightweight manager PHP process, which is responsible for creating/checking/killing the "workers". The idea is to run the manager and forget about it.

The manager should scale/decrease the number of workers (up to a maximum).

The are two main classes, the `Client` and the `Server`.

```php
require "vendor/autoload.php";

use crodas\Worker\Config;
use crodas\Worker\Server;
    
$config = new Config;
$config->setEngine("gearman")->addDirectory("my-tasks/");
    
new Server($config)->serve();
```

The `Server` is quite simple, and it is design so it can be run from the console terminal, never from a web server.

```php
require "vendor/autoload.php";

use crodas\Worker\Config;
use crodas\Worker\Client;

$config = new Config;
$config->setEngine("gearman");

$client = new Client;

$client->push("do_something", ['arg1', 'arg2']);
$client->push("do_something", ['arg4', 'arg3']);
```

The `Client` is object is quite simple, they push tasks and forget about it. Notice that the `Config` object for the client doesn't have the `addDirectory()`, this is because a client doesn't need to know where the worker code are located.

Finally, defining workers are deadly simple. They need to be located in a directory where the server can reach them with `addDirectory()`. They need to be callable and that's all it takes.

```php

/** @Worker("do_something") */
function foobar($args) {
    
}
```
