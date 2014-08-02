<?php

require __DIR__ . "/config.php";

$config->addDirectory(__DIR__);

/* extra configuration */
$config['foo'] = 'bar';

$server = new crodas\Worker\Server($config);
$server->serve();
